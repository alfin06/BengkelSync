<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create vehicles through VehicleService in tests that care about ownership rows;
 * this factory only fills the vehicle itself. Pass customer_id with forceFill.
 *
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    public function definition(): array
    {
        $makes = ['Toyota' => ['Avanza', 'Innova', 'Fortuner'], 'Honda' => ['Jazz', 'Brio', 'CR-V'], 'Suzuki' => ['Ertiga', 'Carry']];
        $make = fake()->randomElement(array_keys($makes));

        return [
            'plate_number' => fake()->randomElement(['B', 'H', 'D', 'AB']).' '.fake()->unique()->numberBetween(1, 9999).' '.strtoupper(fake()->lexify('??')),
            'make' => $make,
            'model' => fake()->randomElement($makes[$make]),
            'year' => fake()->numberBetween(2005, 2026),
            'color' => fake()->safeColorName(),
        ];
    }
}
