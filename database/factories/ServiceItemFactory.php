<?php

namespace Database\Factories;

use App\Models\ServiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant context: tenant_id is filled by BelongsToTenant.
 *
 * @extends Factory<ServiceItem>
 */
class ServiceItemFactory extends Factory
{
    protected $model = ServiceItem::class;

    public function definition(): array
    {
        return [
            'code' => null,
            'name' => 'Jasa '.fake()->unique()->words(2, true),
            'category' => fake()->randomElement(['Perawatan', 'Kaki-kaki', 'Kelistrikan', null]),
            'default_price' => fake()->numberBetween(5, 60) * 5_000,
            'estimated_minutes' => fake()->randomElement([15, 30, 45, 60, 90, null]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
