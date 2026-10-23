<?php

namespace App\Services\Customers;

use App\Enums\CustomerType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public const SORTS = ['name', 'latest'];

    /**
     * One search box for name, phone (typed as 08…, 628… or +62…) or plate number (any spacing).
     *
     * @return Builder<Customer>
     */
    public function search(?string $term, string $sort = 'name'): Builder
    {
        $query = Customer::query()->with(['vehicles' => fn ($vehicles) => $vehicles->orderBy('plate_number')]);
        $term = trim((string) $term);

        if ($term !== '') {
            $query->where(function (Builder $where) use ($term): void {
                $where->where('name', 'like', '%'.$term.'%');

                $digits = ltrim(preg_replace('/\D/', '', $term) ?? '', '0');
                if (str_starts_with($digits, '62')) {
                    $digits = substr($digits, 2);
                }
                if (strlen($digits) >= 4) {
                    $where->orWhere('phone', 'like', '%'.$digits.'%')
                        ->orWhere('whatsapp', 'like', '%'.$digits.'%');
                }

                $plate = preg_replace('/[^A-Z0-9]/', '', strtoupper($term)) ?? '';
                if (strlen($plate) >= 2) {
                    $where->orWhereHas('vehicles', fn (Builder $vehicles) => $vehicles
                        ->whereRaw("REPLACE(plate_number, ' ', '') LIKE ?", ['%'.$plate.'%']));
                }
            });
        }

        return $sort === 'latest' ? $query->latest('id') : $query->orderBy('name');
    }

    /**
     * Customers that already use this phone number (as phone or WhatsApp). Not an error:
     * families and fleets share numbers. The form only warns.
     *
     * @return Collection<int, Customer>
     */
    public function withPhone(string $phone, ?int $exceptId = null): Collection
    {
        $normalized = Phone::normalize($phone);

        if ($normalized === null) {
            return new Collection;
        }

        return Customer::query()
            ->where(fn (Builder $where) => $where->where('phone', $normalized)->orWhere('whatsapp', $normalized))
            ->when($exceptId, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->orderBy('name')
            ->limit(5)
            ->get(['id', 'name', 'phone']);
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function create(array $data): Customer
    {
        return DB::transaction(fn () => Customer::query()->create($this->prepare($data)));
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function update(Customer $customer, array $data): Customer
    {
        DB::transaction(fn () => $customer->fill($this->prepare($data))->save());

        return $customer;
    }

    /**
     * Customers that still own vehicles cannot be deleted: change the owner or delete the
     * vehicles first, so no vehicle is left without an owner.
     */
    public function delete(Customer $customer): void
    {
        if ($customer->vehicles()->exists()) {
            throw new BusinessRuleViolation(__('customers.delete_has_vehicles'));
        }

        DB::transaction(fn () => $customer->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $phone = Phone::normalize(isset($data['phone']) ? (string) $data['phone'] : null);
        $sameAsPhone = (bool) ($data['whatsapp_same_as_phone'] ?? false);
        $type = CustomerType::from((string) $data['type']);

        return [
            'type' => $type,
            'name' => trim((string) $data['name']),
            'phone' => $phone,
            'whatsapp' => $sameAsPhone ? $phone : Phone::normalize(isset($data['whatsapp']) ? (string) $data['whatsapp'] : null),
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            // A tax number only makes sense for companies; never keep a stale one.
            'npwp' => $type === CustomerType::Company ? ($data['npwp'] ?? null) : null,
            'notes' => $data['notes'] ?? null,
        ];
    }
}
