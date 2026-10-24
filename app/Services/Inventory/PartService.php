<?php

namespace App\Services\Inventory;

use App\Enums\PartUnit;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Part;
use App\Support\Quantity;
use App\Support\Tenancy\BranchScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PartService
{
    public const STATUSES = ['active', 'inactive', 'all', 'low'];

    public function __construct(private readonly StockService $stock) {}

    /**
     * Parts with their stock in the active branch. Status "low" lists parts at or below
     * their threshold (the "stok menipis" filter).
     *
     * @return Builder<Part>
     */
    public function search(?string $term, string $status = 'active'): Builder
    {
        $term = trim((string) $term);

        return Part::query()
            ->with('stock')
            ->when($status === 'active' || $status === 'low', fn (Builder $query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->when($status === 'low', fn (Builder $query) => $query->whereHas('stock', fn (Builder $stock) => $stock
                ->where('min_stock', '>', 0)
                ->whereColumn('qty', '<=', 'min_stock')))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('sku', 'like', '%'.$term.'%')
                ->orWhere('brand', 'like', '%'.$term.'%')))
            ->orderBy('name');
    }

    /**
     * Number of parts at or below their threshold in the active branch (for the badge).
     */
    public function lowStockCount(): int
    {
        return $this->search(null, 'low')->count();
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     * @param  int|null  $openingStock  hundredths; counted stock in the active branch
     * @param  int  $minStock  hundredths; low-stock threshold in the active branch
     */
    public function create(array $data, ?int $openingStock = null, int $minStock = 0, ?string $location = null): Part
    {
        return DB::transaction(function () use ($data, $openingStock, $minStock, $location): Part {
            $part = Part::query()->create($this->prepare($data));

            $this->stock->configure($part, $minStock, $location);

            if ($openingStock !== null && $openingStock > 0) {
                $this->stock->adjustTo($part, $openingStock, __('stock.opening_balance'));
            }

            return $part;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Part $part, array $data): Part
    {
        $prepared = $this->prepare($data);
        $newUnit = $prepared['unit'];

        // Stock in a decimal unit cannot become a counted unit while it holds fractions.
        if ($newUnit instanceof PartUnit && ! $newUnit->allowsDecimals() && $part->unit->allowsDecimals()) {
            // All branches of this tenant: only the branch scope is lifted, never the tenant scope.
            $fractional = $part->stock()->withoutGlobalScope(BranchScope::class)->get()
                ->contains(fn ($stock) => ! Quantity::isWhole($stock->qtyHundredths()));

            if ($fractional) {
                throw new BusinessRuleViolation(__('parts.unit_change_blocked'));
            }
        }

        DB::transaction(fn () => $part->fill($prepared)->save());

        return $part;
    }

    /**
     * Parts with stock or history are deactivated instead (so movements keep their part).
     */
    public function delete(Part $part): void
    {
        if ($part->movements()->withoutGlobalScope(BranchScope::class)->exists()) {
            throw new BusinessRuleViolation(__('parts.delete_has_history'));
        }

        DB::transaction(fn () => $part->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $sku = isset($data['sku']) ? strtoupper(trim((string) $data['sku'])) : '';

        return [
            'sku' => $sku === '' ? null : $sku,
            'name' => trim((string) $data['name']),
            'brand' => $this->nullIfBlank($data['brand'] ?? null),
            'category' => $this->nullIfBlank($data['category'] ?? null),
            'unit' => PartUnit::from((string) $data['unit']),
            'cost_price' => (int) ($data['cost_price'] ?? 0),
            'sell_price' => (int) $data['sell_price'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'notes' => $this->nullIfBlank($data['notes'] ?? null),
        ];
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
