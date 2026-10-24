<?php

namespace App\Services\Inventory;

use App\Models\Supplier;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    /**
     * @return Builder<Supplier>
     */
    public function search(?string $term): Builder
    {
        $term = trim((string) $term);

        return Supplier::query()
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('name', 'like', '%'.$term.'%')
                ->orWhere('sales_name', 'like', '%'.$term.'%')))
            ->orderBy('name');
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(array $data): Supplier
    {
        return DB::transaction(fn () => Supplier::query()->create($this->prepare($data)));
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        DB::transaction(fn () => $supplier->fill($this->prepare($data))->save());

        return $supplier;
    }

    /**
     * Soft delete: purchases (next module) keep pointing at the supplier.
     */
    public function delete(Supplier $supplier): void
    {
        DB::transaction(fn () => $supplier->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $phone = Phone::normalize(isset($data['phone']) ? (string) $data['phone'] : null);
        $sameAsPhone = (bool) ($data['whatsapp_same_as_phone'] ?? false);

        return [
            'name' => trim((string) $data['name']),
            'phone' => $phone,
            'whatsapp' => $sameAsPhone ? $phone : Phone::normalize(isset($data['whatsapp']) ? (string) $data['whatsapp'] : null),
            'sales_name' => $data['sales_name'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'npwp' => $data['npwp'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
