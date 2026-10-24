<?php

namespace Database\Factories;

use App\Enums\PartUnit;
use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant context. Gives stock with StockService, never by writing qty.
 *
 * @extends Factory<Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    public function definition(): array
    {
        $cost = fake()->numberBetween(2, 80) * 5_000;

        return [
            'sku' => strtoupper(fake()->unique()->bothify('??-####')),
            'name' => 'Part '.fake()->unique()->words(2, true),
            'brand' => fake()->randomElement(['Aspira', 'Denso', 'NGK', 'Bosch', null]),
            'category' => fake()->randomElement(['Filter', 'Rem', 'Kelistrikan', null]),
            'unit' => PartUnit::Piece,
            'cost_price' => $cost,
            'sell_price' => (int) round($cost * 1.3, -2),
            'is_active' => true,
        ];
    }

    public function liquid(): static
    {
        return $this->state(['unit' => PartUnit::Liter, 'category' => 'Oli']);
    }
}
