<?php

namespace Database\Factories;

use App\Models\Mechanic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant and branch context: tenant_id and branch_id are filled by the traits.
 * NIKs are random 16-digit numbers, never real people's.
 *
 * @extends Factory<Mechanic>
 */
class MechanicFactory extends Factory
{
    protected $model = Mechanic::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            'phone' => '+628'.fake()->numerify('##########'),
            'nik' => fake()->numerify('################'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
