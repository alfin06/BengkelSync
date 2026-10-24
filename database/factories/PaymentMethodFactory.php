<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Create inside a tenant and branch context.
 *
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'name' => 'Metode '.fake()->unique()->word(),
            'type' => PaymentMethodType::Cash,
            'cash_account_id' => CashAccount::factory(),
            'surcharge_percent' => '0.00',
        ];
    }
}
