<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant context: tenant_id is filled by BelongsToTenant.
 *
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        $phone = '+628'.fake()->numerify('##########');

        return [
            'name' => 'Toko '.fake()->unique()->lastName().' Motor',
            'phone' => $phone,
            'whatsapp' => $phone,
            'sales_name' => fake()->firstName(),
            'address' => fake()->address(),
        ];
    }
}
