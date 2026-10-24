<?php

namespace Database\Factories;

use App\Enums\CashAccountType;
use App\Models\CashAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant and branch context. Give money with CashService, never directly.
 *
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    protected $model = CashAccount::class;

    public function definition(): array
    {
        return [
            'name' => 'Akun '.fake()->unique()->word(),
            'type' => CashAccountType::CashDrawer,
            'is_active' => true,
        ];
    }

    public function bank(): static
    {
        return $this->state(['type' => CashAccountType::Bank, 'name' => 'BCA '.fake()->unique()->firstName()]);
    }

    public function pettyCash(): static
    {
        return $this->state(['type' => CashAccountType::PettyCash]);
    }
}
