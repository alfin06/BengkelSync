<?php

namespace Database\Factories;

use App\Enums\CustomerType;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant context: tenant_id is filled by BelongsToTenant.
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        $phone = '+628'.fake()->unique()->numerify('##########');

        return [
            'type' => CustomerType::Individual,
            'name' => fake()->name(),
            'phone' => $phone,
            'whatsapp' => $phone,
            'address' => fake()->address(),
        ];
    }

    public function company(): static
    {
        return $this->state(['type' => CustomerType::Company, 'name' => 'PT '.fake()->company()]);
    }
}
