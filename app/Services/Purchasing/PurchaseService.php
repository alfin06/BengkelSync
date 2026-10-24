<?php

namespace App\Services\Purchasing;

use App\Enums\DocumentType;
use App\Enums\PurchaseStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CashAccount;
use App\Models\Part;
use App\Models\PartBranchStock;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\Supplier;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockService;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Receiving goods from suppliers (SPEC section 4, purchases). Saving a purchase puts the
 * stock in through StockService and updates each part's weighted average cost, all in one
 * transaction; it can be paid at once, partly, or later.
 *
 * Lines are passed as list<array{part_id: int, qty: int, unit_cost: int}> with qty in
 * hundredths (App\Support\Quantity).
 */
class PurchaseService
{
    public const STATUSES = ['open', 'unpaid', 'partial', 'paid', 'void', 'all'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly StockService $stock,
        private readonly SupplierPaymentService $payments,
        private readonly AuditLogger $audit,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @return Builder<Purchase>
     */
    public function search(?string $term, string $status = 'all'): Builder
    {
        $term = trim((string) $term);

        return Purchase::query()
            ->with('supplier')
            ->when($status === 'open', fn (Builder $query) => $query->whereIn('status', [PurchaseStatus::Unpaid, PurchaseStatus::Partial]))
            ->when(in_array($status, ['unpaid', 'partial', 'paid', 'void'], true), fn (Builder $query) => $query->where('status', $status))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('number', 'like', '%'.$term.'%')
                ->orWhere('supplier_ref', 'like', '%'.$term.'%')
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', '%'.$term.'%'))))
            ->latest('purchase_date')
            ->latest('id');
    }

    /**
     * Unpaid and partly paid purchases, oldest due date first ("Hutang Supplier").
     *
     * @return Builder<Purchase>
     */
    public function debts(): Builder
    {
        return Purchase::query()
            ->with('supplier')
            ->whereIn('status', [PurchaseStatus::Unpaid, PurchaseStatus::Partial])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('purchase_date');
    }

    /**
     * @param  array{supplier_ref?: string|null, purchase_date: string, due_date?: string|null, notes?: string|null}  $data
     * @param  list<array{part_id: int, qty: int, unit_cost: int}>  $lines
     * @param  array{amount: int, account: CashAccount, reference?: string|null}|null  $payNow
     */
    public function create(Supplier $supplier, array $data, array $lines, ?array $payNow = null): Purchase
    {
        $parts = $this->loadParts($lines);

        return DB::transaction(function () use ($supplier, $data, $lines, $parts, $payNow): Purchase {
            $branch = $this->context->requireBranch();
            $purchaseDate = Carbon::parse($data['purchase_date']);

            $purchase = new Purchase([
                'supplier_id' => $supplier->id,
                'supplier_ref' => $this->nullIfBlank($data['supplier_ref'] ?? null),
                'purchase_date' => $purchaseDate->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'notes' => $this->nullIfBlank($data['notes'] ?? null),
            ]);
            $purchase->forceFill([
                'number' => DocumentNumber::next($branch, DocumentType::Purchase, $purchaseDate),
                'total' => self::totalOf($lines),
                'status' => PurchaseStatus::for(self::totalOf($lines), 0),
                'created_by' => $this->userId(),
            ])->save();

            foreach ($lines as $line) {
                $part = $parts->get($line['part_id']);
                $this->updateAverageCost($part, $line['qty'], $line['unit_cost']);
                $this->stock->move($part, StockMovementType::Purchase, $line['qty'], $line['unit_cost'], $purchase, $purchase->number);
                $this->storeLine($purchase, $line);
            }

            if ($payNow !== null && $payNow['amount'] > 0) {
                $this->payments->pay($purchase, $payNow['amount'], $payNow['account'], $purchaseDate, $payNow['reference'] ?? null);
                $purchase->refresh();
            }

            return $purchase;
        });
    }

    /**
     * Change a saved purchase (needs a reason). Stock moves by the difference per part;
     * the change is refused when that would take stock below zero or the total below what
     * was already paid. Purchase costs of earlier receipts are not recalculated.
     *
     * @param  array{supplier_ref?: string|null, due_date?: string|null, notes?: string|null}  $data
     * @param  list<array{part_id: int, qty: int, unit_cost: int}>  $lines
     */
    public function revise(Purchase $purchase, array $data, array $lines, string $reason): Purchase
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        $parts = $this->loadParts($lines);

        return DB::transaction(function () use ($purchase, $data, $lines, $parts, $reason): Purchase {
            $locked = Purchase::query()->with('lines')->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseStatus::Void) {
                throw new BusinessRuleViolation(__('purchases.is_void'));
            }

            $newTotal = self::totalOf($lines);
            if ($newTotal < $locked->amount_paid) {
                throw new BusinessRuleViolation(__('purchases.total_below_paid', ['paid' => Money::format($locked->amount_paid)]));
            }

            $before = $this->snapshot($locked);
            $oldQty = $this->qtyPerPart($locked->lines->map(fn (PurchaseLine $line) => ['part_id' => $line->part_id, 'qty' => $line->qtyHundredths()])->all());
            $newQty = $this->qtyPerPart($lines);
            $allParts = $parts->union(Part::withTrashed()->whereKey(array_keys($oldQty))->get()->keyBy('id'));

            foreach (array_unique([...array_keys($oldQty), ...array_keys($newQty)]) as $partId) {
                $delta = ($newQty[$partId] ?? 0) - ($oldQty[$partId] ?? 0);

                if ($delta !== 0) {
                    $this->stock->move($allParts->get($partId), StockMovementType::Purchase, $delta, null, $locked, __('purchases.revision_note', ['number' => $locked->number]));
                }
            }

            $locked->lines()->delete();
            foreach ($lines as $line) {
                $this->storeLine($locked, $line);
            }

            $locked->fill([
                'supplier_ref' => $this->nullIfBlank($data['supplier_ref'] ?? null),
                'due_date' => $data['due_date'] ?? null,
                'notes' => $this->nullIfBlank($data['notes'] ?? null),
            ]);

            // A new due date gets its own reminders.
            if ($locked->isDirty('due_date')) {
                $locked->forceFill(['due_soon_notified_at' => null, 'overdue_notified_at' => null]);
            }
            $locked->forceFill(['total' => $newTotal, 'status' => PurchaseStatus::for($newTotal, $locked->amount_paid)])->save();

            $this->audit->record('purchase_revised', $locked, $before, $this->snapshot($locked->load('lines')), trim($reason));

            return $locked;
        });
    }

    /**
     * Cancel a purchase entered by mistake: only while nothing is paid, and only if its
     * stock is still there to take back out.
     */
    public function void(Purchase $purchase, string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        DB::transaction(function () use ($purchase, $reason): void {
            $locked = Purchase::query()->with('lines.part')->whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PurchaseStatus::Void) {
                throw new BusinessRuleViolation(__('purchases.is_void'));
            }

            if ($locked->amount_paid > 0) {
                throw new BusinessRuleViolation(__('purchases.void_has_payments'));
            }

            foreach ($locked->lines as $line) {
                $this->stock->move($line->part, StockMovementType::Purchase, -$line->qtyHundredths(), null, $locked, __('purchases.void_note', ['number' => $locked->number]));
            }

            $locked->forceFill(['status' => PurchaseStatus::Void, 'void_reason' => trim($reason)])->save();
            $this->audit->record('purchase_voided', $locked, reason: trim($reason));
        });

        $purchase->refresh();
    }

    /**
     * Weighted average cost over the stock of all branches (prices are tenant-wide):
     * (stock × old cost + received × unit cost) / (stock + received), rounded half-up.
     */
    private function updateAverageCost(Part $part, int $receivedQty, int $unitCost): void
    {
        // Every branch of this tenant: only the branch scope is lifted, never the tenant scope.
        $stockBefore = Quantity::fromDecimal((string) (PartBranchStock::query()
            ->withoutGlobalScope(BranchScope::class)
            ->where('part_id', $part->id)
            ->sum('qty')));
        $stockBefore = max($stockBefore, 0);
        $totalQty = $stockBefore + $receivedQty;

        $average = $totalQty <= 0
            ? $unitCost
            : Money::divideHalfUp($stockBefore * $part->cost_price + $receivedQty * $unitCost, $totalQty);

        if ($average !== $part->cost_price) {
            $part->forceFill(['cost_price' => $average])->save();
        }
    }

    /**
     * @param  list<array{part_id: int, qty: int, unit_cost: int}>  $lines
     */
    public static function totalOf(array $lines): int
    {
        return array_sum(array_map(fn (array $line) => Quantity::priceOf($line['qty'], $line['unit_cost']), $lines));
    }

    /**
     * @param  array{part_id: int, qty: int, unit_cost: int}  $line
     */
    private function storeLine(Purchase $purchase, array $line): void
    {
        PurchaseLine::query()->create([
            'purchase_id' => $purchase->id,
            'part_id' => $line['part_id'],
            'qty' => Quantity::toDecimal($line['qty']),
            'unit_cost' => $line['unit_cost'],
            'line_total' => Quantity::priceOf($line['qty'], $line['unit_cost']),
        ]);
    }

    /**
     * Parts of the lines (tenant-scoped, so other tenants' parts are never found), checked
     * for quantities their unit allows.
     *
     * @param  list<array{part_id: int, qty: int, unit_cost: int}>  $lines
     * @return Collection<int, Part>
     */
    private function loadParts(array $lines): Collection
    {
        if ($lines === []) {
            throw new BusinessRuleViolation(__('purchases.no_lines'));
        }

        $parts = Part::query()->whereKey(array_column($lines, 'part_id'))->get()->keyBy('id');

        foreach ($lines as $line) {
            $part = $parts->get($line['part_id']) ?? throw new BusinessRuleViolation(__('purchases.unknown_part'));

            if ($line['qty'] <= 0) {
                throw new BusinessRuleViolation(__('stock.zero_change'));
            }

            $this->stock->ensureAllowedQuantity($part, $line['qty']);
        }

        return $parts->toBase();
    }

    /**
     * @param  list<array{part_id: int, qty: int}>  $lines
     * @return array<int, int> part id => total qty in hundredths
     */
    private function qtyPerPart(array $lines): array
    {
        $totals = [];
        foreach ($lines as $line) {
            $totals[$line['part_id']] = ($totals[$line['part_id']] ?? 0) + $line['qty'];
        }

        return $totals;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Purchase $purchase): array
    {
        return [
            'total' => $purchase->total,
            'due_date' => $purchase->due_date?->toDateString(),
            'supplier_ref' => $purchase->supplier_ref,
            'lines' => $purchase->lines->map(fn (PurchaseLine $line) => [
                'part_id' => $line->part_id,
                'qty' => $line->qty,
                'unit_cost' => $line->unit_cost,
            ])->all(),
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
