<?php

namespace App\Services\Inventory;

use App\Enums\Permission;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\Part;
use App\Models\PartBranchStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\LowStockNotification;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The only way stock changes (CLAUDE.md rule 6). Every change locks the branch stock row,
 * writes a stock_movements row and updates part_branch_stock.qty in one transaction.
 *
 * Stock can never go below zero: a part that is out of stock cannot be used (pilot
 * decision). When a change brings stock down to or below the part's threshold, the
 * branch's staff get a low-stock notification.
 */
class StockService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * Add (positive) or remove (negative) stock in the active branch.
     *
     * @param  int  $delta  hundredths, e.g. -420 for 4.2 L used
     */
    public function move(
        Part $part,
        StockMovementType $type,
        int $delta,
        ?int $unitCost = null,
        ?Model $reference = null,
        ?string $note = null,
    ): StockMovement {
        if ($delta === 0) {
            throw new BusinessRuleViolation(__('stock.zero_change'));
        }

        $this->ensureAllowedQuantity($part, $delta);

        return DB::transaction(function () use ($part, $type, $delta, $unitCost, $reference, $note): StockMovement {
            $stock = $this->lockedStock($part);
            $before = $stock->qtyHundredths();
            $after = $before + $delta;

            if ($after < 0) {
                throw new BusinessRuleViolation(__('stock.insufficient', [
                    'part' => $part->name,
                    'available' => Quantity::format($before),
                    'unit' => $part->unit->label(),
                ]));
            }

            $stock->forceFill(['qty' => Quantity::toDecimal($after)])->save();

            $movement = StockMovement::query()->create([
                'part_id' => $part->id,
                'type' => $type,
                'qty' => Quantity::toDecimal($delta),
                'qty_after' => Quantity::toDecimal($after),
                'unit_cost' => $unitCost,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'note' => $note,
                'created_by' => $this->userId(),
            ]);

            $this->notifyIfCrossedThreshold($part, $stock, $before, $after);

            return $movement;
        });
    }

    /**
     * Correct the stock to what was counted on the shelf (stock opname), with a reason.
     * Also used for the opening balance ("saldo awal").
     *
     * @param  int  $counted  hundredths
     */
    public function adjustTo(Part $part, int $counted, string $reason): ?StockMovement
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('stock.reason_required'));
        }

        if ($counted < 0) {
            throw new BusinessRuleViolation(__('stock.negative_count'));
        }

        return DB::transaction(function () use ($part, $counted, $reason): ?StockMovement {
            $current = $this->lockedStock($part)->qtyHundredths();

            if ($counted === $current) {
                return null;
            }

            return $this->move($part, StockMovementType::Adjustment, $counted - $current, note: trim($reason));
        });
    }

    /**
     * Low-stock threshold and shelf location of a part in the active branch.
     *
     * @param  int  $minStock  hundredths
     */
    public function configure(Part $part, int $minStock, ?string $location): PartBranchStock
    {
        $this->ensureAllowedQuantity($part, $minStock);

        if ($minStock < 0) {
            throw new BusinessRuleViolation(__('stock.negative_count'));
        }

        return DB::transaction(function () use ($part, $minStock, $location): PartBranchStock {
            $stock = $this->lockedStock($part);
            $stock->fill([
                'min_stock' => Quantity::toDecimal($minStock),
                'location' => $location === null || trim($location) === '' ? null : trim($location),
            ])->save();

            return $stock;
        });
    }

    /**
     * Quantity on the shelf in the active branch, in hundredths.
     */
    public function available(Part $part): int
    {
        return PartBranchStock::query()->where('part_id', $part->id)->first()?->qtyHundredths() ?? 0;
    }

    /**
     * Counted units (pcs, set) only move in whole numbers.
     */
    public function ensureAllowedQuantity(Part $part, int $hundredths): void
    {
        if (! $part->unit->allowsDecimals() && ! Quantity::isWhole($hundredths)) {
            throw new BusinessRuleViolation(__('stock.whole_units_only', ['unit' => $part->unit->label()]));
        }
    }

    /**
     * The branch stock row, created on first use and locked until the transaction ends,
     * so two simultaneous changes cannot both read the old quantity.
     */
    private function lockedStock(Part $part): PartBranchStock
    {
        $branch = $this->context->requireBranch();

        PartBranchStock::query()->insertOrIgnore([
            'tenant_id' => $branch->tenant_id,
            'branch_id' => $branch->id,
            'part_id' => $part->id,
            'qty' => '0.00',
            'min_stock' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return PartBranchStock::query()->where('part_id', $part->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Notify once, when stock goes from above the threshold to at or below it (not on every
     * later sale), and only after the transaction committed.
     */
    private function notifyIfCrossedThreshold(Part $part, PartBranchStock $stock, int $before, int $after): void
    {
        $threshold = $stock->minStockHundredths();

        if ($threshold <= 0 || $before <= $threshold || $after > $threshold) {
            return;
        }

        $branch = $this->context->requireBranch();
        $notification = new LowStockNotification($part, $branch, $after, $threshold);

        DB::afterCommit(fn () => Notification::send($this->recipients($branch), $notification));
    }

    /**
     * Active users who work in the branch and can see stock.
     *
     * @return list<User>
     */
    private function recipients(Branch $branch): array
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can(Permission::StockView->value) && $user->canAccessBranch($branch))
            ->values()
            ->all();
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
