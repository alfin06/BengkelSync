<?php

namespace App\Services\Catalog;

use App\Exceptions\BusinessRuleViolation;
use App\Models\ServiceItem;
use App\Models\WorkOrderServiceLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ServiceItemService
{
    public const STATUSES = ['active', 'inactive', 'all'];

    /**
     * @return Builder<ServiceItem>
     */
    public function search(?string $term, string $status = 'active'): Builder
    {
        $term = trim((string) $term);

        return ServiceItem::query()
            ->when($status !== 'all', fn (Builder $query) => $query->where('is_active', $status === 'active'))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')
                ->orWhere('category', 'like', '%'.$term.'%')))
            // Jobs without a category come last, then alphabetical within each category.
            ->orderByRaw('category IS NULL')
            ->orderBy('category')
            ->orderBy('name');
    }

    /**
     * Categories already in use, for the form's suggestion list.
     *
     * @return list<string>
     */
    public function categories(): array
    {
        return ServiceItem::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(array $data): ServiceItem
    {
        return DB::transaction(fn () => ServiceItem::query()->create($this->prepare($data)));
    }

    /**
     * Price changes are audited and never touch existing work orders or invoices, which
     * keep their own copy of the price.
     *
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(ServiceItem $item, array $data): ServiceItem
    {
        DB::transaction(fn () => $item->fill($this->prepare($data))->save());

        return $item;
    }

    /**
     * Only jobs that were never used can be deleted; used ones are deactivated instead,
     * so history keeps pointing at a real catalog entry (decision 3 of the plan).
     */
    public function delete(ServiceItem $item): void
    {
        if ($this->isUsed($item)) {
            throw new BusinessRuleViolation(__('service_items.delete_used'));
        }

        DB::transaction(fn () => $item->delete());
    }

    /**
     * A job that appears on a work order cannot be deleted, only deactivated.
     */
    public function isUsed(ServiceItem $item): bool
    {
        return WorkOrderServiceLine::query()->where('service_item_id', $item->id)->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $code = isset($data['code']) ? strtoupper(trim((string) $data['code'])) : '';
        $category = isset($data['category']) ? trim((string) $data['category']) : '';

        return [
            'code' => $code === '' ? null : $code,
            'name' => trim((string) $data['name']),
            'category' => $category === '' ? null : $category,
            'default_price' => (int) $data['default_price'],
            'estimated_minutes' => $data['estimated_minutes'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
