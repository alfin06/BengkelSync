<?php

namespace App\Services\Invoicing;

use App\Enums\DocumentType;
use App\Enums\InvoiceLineType;
use App\Enums\InvoiceStatus;
use App\Enums\StockMovementType;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoiceRevision;
use App\Models\Part;
use App\Models\ServiceItem;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderServiceLine;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\StockService;
use App\Support\DocumentNumber;
use App\Support\Money;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Making and cancelling invoices. An invoice from a work order copies its lines (the
 * parts already left the stock with the work order) and locks it; a walk-in sale takes
 * its parts from the stock itself. Editing issued invoices is revise() (SPEC section 6).
 */
class InvoiceService
{
    /** Filters of the list; "open" = not fully paid. */
    public const FILTERS = ['open', 'unpaid', 'partial', 'paid', 'void', 'all'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly StockService $stock,
        private readonly InvoiceSettlement $settlement,
        private readonly AuditLogger $audit,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @return Builder<Invoice>
     */
    public function search(?string $term, string $filter = 'all'): Builder
    {
        $term = trim((string) $term);

        return Invoice::query()
            ->with(['customer', 'workOrder.vehicle'])
            ->when($filter === 'open', fn (Builder $query) => $query->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial]))
            ->when(! in_array($filter, ['open', 'all'], true), fn (Builder $query) => $query->where('status', $filter))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('number', 'like', '%'.$term.'%')
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$term.'%'))
                ->orWhereHas('workOrder.vehicle', fn (Builder $vehicle) => $vehicle->whereRaw("REPLACE(plate_number, ' ', '') LIKE ?", ['%'.preg_replace('/\s+/', '', strtoupper($term)).'%']))))
            ->latest('issue_date')
            ->latest('id');
    }

    /**
     * Invoice a finished work order. Quantities come from the work order; prices and
     * discounts may be adjusted for every line.
     *
     * @param  array{issue_date: string, due_date?: string|null, notes?: string|null, discount_amount?: int, tax_enabled?: bool|null}  $header
     * @param  list<array{source: string, id: int, unit_price: int, discount_amount?: int}>  $prices  one entry per work order line
     */
    public function createFromWorkOrder(WorkOrder $workOrder, array $header, array $prices): Invoice
    {
        return DB::transaction(function () use ($workOrder, $header, $prices): Invoice {
            $locked = WorkOrder::query()->with(['services', 'parts.part'])->whereKey($workOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WorkOrderStatus::Completed) {
                throw new BusinessRuleViolation(__('invoices.work_order_not_completed'));
            }

            if (Invoice::query()->where('work_order_id', $locked->id)->where('status', '!=', InvoiceStatus::Void)->exists()) {
                throw new BusinessRuleViolation(__('invoices.work_order_invoiced'));
            }

            $lines = $this->linesFromWorkOrder($locked, $prices);
            $invoice = $this->store($header, $lines, $locked->customer_id, $locked->id);

            $locked->forceFill(['status' => $invoice->status === InvoiceStatus::Paid ? WorkOrderStatus::Closed : WorkOrderStatus::Invoiced])->save();

            return $invoice;
        });
    }

    /**
     * A sale at the counter without a work order. Parts leave the stock now.
     *
     * @param  array{issue_date: string, due_date?: string|null, notes?: string|null, discount_amount?: int, tax_enabled?: bool|null}  $header
     * @param  list<array{type: string, part_id?: int|null, service_item_id?: int|null, description?: string|null, qty: int, unit_price: int, discount_amount?: int}>  $input  qty in hundredths
     */
    public function createWalkIn(?Customer $customer, array $header, array $input): Invoice
    {
        $lines = $this->walkInLines($input);

        return DB::transaction(function () use ($customer, $header, $lines): Invoice {
            $invoice = $this->store($header, $lines, $customer?->id, null);

            foreach ($lines as $line) {
                if ($line['part'] !== null) {
                    $this->stock->move($line['part'], StockMovementType::Sale, -$line['qty'], $line['unit_cost'], $invoice, $invoice->number);
                }
            }

            return $invoice;
        });
    }

    /**
     * Change an issued invoice (SPEC section 6): always with a reason. The previous version
     * is saved as a revision, totals are recalculated with the tax rate and mode of the
     * invoice, parts move in or out of stock by the difference ("invoice_edit"), and a total
     * below what is already paid is refused. The number never changes.
     *
     * @param  array{due_date?: string|null, notes?: string|null, discount_amount?: int, tax_enabled?: bool|null}  $header
     * @param  list<array{id?: int|null, type: string, part_id?: int|null, service_item_id?: int|null, description?: string|null, qty: int, unit_price: int, discount_amount?: int}>  $input  qty in hundredths
     */
    public function revise(Invoice $invoice, array $header, array $input, string $reason): Invoice
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        return DB::transaction(function () use ($invoice, $header, $input, $reason): Invoice {
            $locked = Invoice::query()->with('lines.part')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Void) {
                throw new BusinessRuleViolation(__('invoices.is_void'));
            }

            $lines = $this->walkInLines($input, $locked->lines->keyBy('id')->all());
            if ($lines === []) {
                throw new BusinessRuleViolation(__('invoices.no_lines'));
            }

            $taxEnabled = $header['tax_enabled'] ?? $locked->tax_enabled;
            $totals = InvoiceCalculator::totals(array_column($lines, 'line_total'), $header['discount_amount'] ?? 0, $taxEnabled, $locked->tax_rate, $locked->tax_inclusive);

            if ($totals['total'] < $locked->amount_paid) {
                throw new BusinessRuleViolation(__('invoices.total_below_paid', ['paid' => Money::format($locked->amount_paid)]));
            }

            $before = $this->snapshot($locked);
            InvoiceRevision::query()->create([
                'invoice_id' => $locked->id,
                'revision_no' => $locked->revision_no,
                'snapshot' => $before,
                'reason' => trim($reason),
                'edited_by' => $this->userId(),
            ]);

            // Stock follows the difference per part: more sold leaves the shelf, less comes back.
            $oldQty = $this->qtyPerPart($locked->lines->map(fn (InvoiceLine $line) => ['part_id' => $line->part_id, 'qty' => $line->qtyHundredths()])->all());
            $newQty = $this->qtyPerPart(array_map(fn (array $line) => ['part_id' => $line['part_id'], 'qty' => $line['qty']], $lines));
            $parts = Part::withTrashed()->whereKey(array_keys($oldQty + $newQty))->get()->keyBy('id');

            foreach (array_keys($oldQty + $newQty) as $partId) {
                $delta = ($newQty[$partId] ?? 0) - ($oldQty[$partId] ?? 0);
                if ($delta !== 0) {
                    $this->stock->move($parts->get($partId), StockMovementType::InvoiceEdit, -$delta, null, $locked, __('invoices.revision_note', ['number' => $locked->number]));
                }
            }

            $locked->lines()->delete();
            $this->storeLines($locked, $lines);

            $locked->fill([
                'due_date' => $header['due_date'] ?? null,
                'notes' => $this->nullIfBlank($header['notes'] ?? null),
            ]);
            if ($locked->isDirty('due_date')) {
                $locked->forceFill(['due_soon_notified_at' => null, 'overdue_notified_at' => null]);
            }
            $locked->forceFill([...$totals, 'tax_enabled' => $taxEnabled, 'revision_no' => $locked->revision_no + 1])->save();
            $this->settlement->apply($locked, $locked->amount_paid);

            $this->audit->record('invoice_revised', $locked, ['total' => $before['total']], ['total' => $locked->total], trim($reason));

            return $locked;
        });
    }

    /**
     * Cancel an invoice made by mistake (only while nothing is paid). A work order goes
     * back to "completed" so it can be corrected and invoiced again; the parts of a
     * walk-in sale go back on the shelf.
     */
    public function void(Invoice $invoice, string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        DB::transaction(function () use ($invoice, $reason): void {
            $locked = Invoice::query()->with('lines.part')->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === InvoiceStatus::Void) {
                throw new BusinessRuleViolation(__('invoices.is_void'));
            }

            if ($locked->amount_paid > 0) {
                throw new BusinessRuleViolation(__('invoices.void_has_payments'));
            }

            if ($locked->work_order_id !== null) {
                $workOrder = WorkOrder::query()->with('parts.part')->whereKey($locked->work_order_id)->lockForUpdate()->firstOrFail();
                $this->restoreWorkOrderQuantities($locked, $workOrder);
                $workOrder->forceFill(['status' => WorkOrderStatus::Completed])->save();
            } else {
                foreach ($locked->lines as $line) {
                    if ($line->part !== null) {
                        $this->stock->move($line->part, StockMovementType::Sale, $line->qtyHundredths(), $line->unit_cost, $locked, __('invoices.void_note', ['number' => $locked->number]));
                    }
                }
            }

            $locked->forceFill([
                'status' => InvoiceStatus::Void,
                'void_reason' => trim($reason),
                'voided_at' => now(),
                'voided_by' => $this->userId(),
            ])->save();

            $this->audit->record('invoice_voided', $locked, reason: trim($reason));
        });

        $invoice->refresh();
    }

    /**
     * @param  array{issue_date: string, due_date?: string|null, notes?: string|null, discount_amount?: int, tax_enabled?: bool|null}  $header
     * @param  list<array{type: InvoiceLineType, part: Part|null, part_id: int|null, service_item_id: int|null, description: string, qty: int, unit_price: int, unit_cost: int|null, discount_amount: int, line_total: int, mechanic_id: int|null}>  $lines
     */
    private function store(array $header, array $lines, ?int $customerId, ?int $workOrderId): Invoice
    {
        if ($lines === []) {
            throw new BusinessRuleViolation(__('invoices.no_lines'));
        }

        $branch = $this->context->requireBranch();
        // The branch decides whether invoices carry tax; a user with invoice.toggle_tax may
        // change it for one invoice. Rate and mode are copied so later changes never alter it.
        $taxEnabled = $header['tax_enabled'] ?? $branch->tax_enabled;
        $totals = InvoiceCalculator::totals(
            array_column($lines, 'line_total'),
            $header['discount_amount'] ?? 0,
            $taxEnabled,
            $branch->tax_rate,
            $branch->tax_inclusive,
        );
        $issueDate = Carbon::parse($header['issue_date']);

        $invoice = new Invoice([
            'issue_date' => $issueDate->toDateString(),
            'due_date' => $header['due_date'] ?? null,
            'notes' => $this->nullIfBlank($header['notes'] ?? null),
        ]);
        $invoice->forceFill([
            'number' => DocumentNumber::next($branch, DocumentType::Invoice, $issueDate),
            'work_order_id' => $workOrderId,
            'customer_id' => $customerId,
            'tax_enabled' => $taxEnabled,
            'tax_name' => $branch->tax_name,
            'tax_rate' => $branch->tax_rate,
            'tax_inclusive' => $branch->tax_inclusive,
            ...$totals,
            'status' => InvoiceStatus::for($totals['total'], 0),
            'created_by' => $this->userId(),
        ])->save();

        $this->storeLines($invoice, $lines);

        return $invoice;
    }

    /**
     * @param  list<array{type: InvoiceLineType, part: Part|null, part_id: int|null, service_item_id: int|null, description: string, qty: int, unit_price: int, unit_cost: int|null, discount_amount: int, line_total: int, mechanic_id: int|null}>  $lines
     */
    private function storeLines(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $sort => $line) {
            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'type' => $line['type'],
                'part_id' => $line['part_id'],
                'service_item_id' => $line['service_item_id'],
                'description' => $line['description'],
                'qty' => Quantity::toDecimal($line['qty']),
                'unit_price' => $line['unit_price'],
                'unit_cost' => $line['unit_cost'],
                'discount_amount' => $line['discount_amount'],
                'line_total' => $line['line_total'],
                'mechanic_id' => $line['mechanic_id'],
                'sort' => $sort,
            ]);
        }
    }

    /**
     * The invoice as it is now, saved before an edit (header and lines).
     *
     * @return array<string, mixed>
     */
    public function snapshot(Invoice $invoice): array
    {
        return [
            'revision_no' => $invoice->revision_no,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'subtotal' => $invoice->subtotal,
            'discount_amount' => $invoice->discount_amount,
            'tax_enabled' => $invoice->tax_enabled,
            'tax_name' => $invoice->tax_name,
            'tax_rate' => $invoice->tax_rate,
            'tax_inclusive' => $invoice->tax_inclusive,
            'tax_amount' => $invoice->tax_amount,
            'total' => $invoice->total,
            'notes' => $invoice->notes,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line) => [
                'type' => $line->type->value,
                'part_id' => $line->part_id,
                'description' => $line->description,
                'qty' => $line->qty,
                'unit_label' => $line->part?->unit->label(),
                'unit_price' => $line->unit_price,
                'discount_amount' => $line->discount_amount,
                'line_total' => $line->line_total,
            ])->all(),
        ];
    }

    /**
     * Cancelling the invoice of a work order: the work order's own part lines are what
     * left the stock for the job; any difference made by invoice edits goes back.
     */
    private function restoreWorkOrderQuantities(Invoice $invoice, WorkOrder $workOrder): void
    {
        $onInvoice = $this->qtyPerPart($invoice->lines->map(fn (InvoiceLine $line) => ['part_id' => $line->part_id, 'qty' => $line->qtyHundredths()])->all());
        $onWorkOrder = $this->qtyPerPart($workOrder->parts->map(fn (WorkOrderPart $line) => ['part_id' => $line->part_id, 'qty' => $line->qtyHundredths()])->all());
        $parts = Part::withTrashed()->whereKey(array_keys($onInvoice + $onWorkOrder))->get()->keyBy('id');

        foreach (array_keys($onInvoice + $onWorkOrder) as $partId) {
            $difference = ($onInvoice[$partId] ?? 0) - ($onWorkOrder[$partId] ?? 0);
            if ($difference !== 0) {
                $this->stock->move($parts->get($partId), StockMovementType::InvoiceEdit, $difference, null, $invoice, __('invoices.void_note', ['number' => $invoice->number]));
            }
        }
    }

    /**
     * @param  list<array{part_id: int|null, qty: int}>  $lines
     * @return array<int, int> part id => total qty in hundredths (part lines only)
     */
    private function qtyPerPart(array $lines): array
    {
        $totals = [];
        foreach ($lines as $line) {
            if ($line['part_id'] !== null) {
                $totals[$line['part_id']] = ($totals[$line['part_id']] ?? 0) + $line['qty'];
            }
        }

        return $totals;
    }

    /**
     * Every line of the work order exactly once, with the price and discount chosen.
     *
     * @param  list<array{source: string, id: int, unit_price: int, discount_amount?: int}>  $prices
     * @return list<array{type: InvoiceLineType, part: Part|null, part_id: int|null, service_item_id: int|null, description: string, qty: int, unit_price: int, unit_cost: int|null, discount_amount: int, line_total: int, mechanic_id: int|null}>
     */
    private function linesFromWorkOrder(WorkOrder $workOrder, array $prices): array
    {
        $chosen = [];
        foreach ($prices as $price) {
            $chosen[$price['source'].':'.$price['id']] = $price;
        }

        $expected = [
            ...$workOrder->services->map(fn (WorkOrderServiceLine $line) => 'service:'.$line->id)->all(),
            ...$workOrder->parts->map(fn (WorkOrderPart $line) => 'part:'.$line->id)->all(),
        ];

        if ($expected === [] || count($chosen) !== count($prices) || array_diff($expected, array_keys($chosen)) !== [] || array_diff(array_keys($chosen), $expected) !== []) {
            throw new BusinessRuleViolation(__('invoices.work_order_changed'));
        }

        $lines = [];

        foreach ($workOrder->services as $service) {
            $price = $chosen['service:'.$service->id];
            $qty = $service->qty * Quantity::SCALE;
            $discount = $price['discount_amount'] ?? 0;
            $lines[] = [
                'type' => InvoiceLineType::Service,
                'part' => null,
                'part_id' => null,
                'service_item_id' => $service->service_item_id,
                'description' => $service->description,
                'qty' => $qty,
                'unit_price' => $price['unit_price'],
                'unit_cost' => null,
                'discount_amount' => $discount,
                'line_total' => InvoiceCalculator::lineTotal($qty, $price['unit_price'], $discount),
                'mechanic_id' => $service->mechanic_id,
            ];
        }

        foreach ($workOrder->parts as $partLine) {
            $price = $chosen['part:'.$partLine->id];
            $discount = $price['discount_amount'] ?? 0;
            $lines[] = [
                'type' => InvoiceLineType::Part,
                'part' => null, // already taken from the stock by the work order
                'part_id' => $partLine->part_id,
                'service_item_id' => null,
                'description' => $partLine->part->name,
                'qty' => $partLine->qtyHundredths(),
                'unit_price' => $price['unit_price'],
                'unit_cost' => $partLine->unit_cost,
                'discount_amount' => $discount,
                'line_total' => InvoiceCalculator::lineTotal($partLine->qtyHundredths(), $price['unit_price'], $discount),
                'mechanic_id' => null,
            ];
        }

        return $lines;
    }

    /**
     * Lines typed on a form (a walk-in sale, or an invoice being edited). Edited lines keep
     * the purchase cost and mechanic they had, so reports stay right.
     *
     * @param  list<array{id?: int|null, type: string, part_id?: int|null, service_item_id?: int|null, description?: string|null, qty: int, unit_price: int, discount_amount?: int}>  $input
     * @param  array<int, InvoiceLine>  $existing  current lines of the invoice being edited, by id
     * @return list<array{type: InvoiceLineType, part: Part|null, part_id: int|null, service_item_id: int|null, description: string, qty: int, unit_price: int, unit_cost: int|null, discount_amount: int, line_total: int, mechanic_id: int|null}>
     */
    private function walkInLines(array $input, array $existing = []): array
    {
        $parts = Part::query()->whereKey(array_filter(array_column($input, 'part_id')))->get()->keyBy('id');
        $lines = [];

        foreach ($input as $row) {
            $type = InvoiceLineType::from($row['type']);
            $qty = $row['qty'];
            $discount = $row['discount_amount'] ?? 0;
            $part = null;
            $previous = isset($row['id']) ? ($existing[(int) $row['id']] ?? null) : null;

            if ($qty <= 0) {
                throw new BusinessRuleViolation(__('stock.zero_change'));
            }

            if ($type === InvoiceLineType::Part) {
                $part = $parts->get((int) ($row['part_id'] ?? 0)) ?? throw new BusinessRuleViolation(__('purchases.unknown_part'));

                // An inactive part may stay on an invoice it is already on, but not be added.
                if (! $part->is_active && $previous?->part_id !== $part->id) {
                    throw new BusinessRuleViolation(__('work_orders.part_inactive', ['part' => $part->name]));
                }

                $this->stock->ensureAllowedQuantity($part, $qty);
            } elseif (! Quantity::isWhole($qty)) {
                throw new BusinessRuleViolation(__('invoices.whole_qty_only'));
            }

            $serviceItemId = $type === InvoiceLineType::Service ? ($row['service_item_id'] ?? null) : null;
            if ($serviceItemId !== null && $serviceItemId !== $previous?->service_item_id && ! ServiceItem::query()->whereKey($serviceItemId)->where('is_active', true)->exists()) {
                throw new BusinessRuleViolation(__('work_orders.service_unavailable'));
            }

            $description = $part !== null ? $part->name : trim((string) ($row['description'] ?? ''));
            if ($description === '') {
                throw new BusinessRuleViolation(__('invoices.description_required'));
            }

            $lines[] = [
                'type' => $type,
                'part' => $part,
                'part_id' => $part?->id,
                'service_item_id' => $serviceItemId,
                'description' => $description,
                'qty' => $qty,
                'unit_price' => $row['unit_price'],
                'unit_cost' => $part === null ? null : ($previous?->part_id === $part->id ? $previous->unit_cost : $part->cost_price),
                'discount_amount' => $discount,
                'line_total' => InvoiceCalculator::lineTotal($qty, $row['unit_price'], $discount),
                'mechanic_id' => $previous?->type === $type ? $previous->mechanic_id : null,
            ];
        }

        return $lines;
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
