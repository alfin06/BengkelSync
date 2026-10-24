<?php

namespace App\Services\Invoicing;

use App\Exceptions\BusinessRuleViolation;
use App\Support\Money;
use App\Support\Quantity;

/**
 * Invoice totals (SPEC sections 4 and 5), always calculated on the server:
 * line = qty × price − line discount; subtotal = Σ lines; base = subtotal − invoice discount;
 * tax added on top: tax = round(base × rate / 100), total = base + tax;
 * tax included: total = base, tax = round(total × rate / (100 + rate)).
 * Integer Rupiah, rounded half up.
 */
final class InvoiceCalculator
{
    /**
     * @param  int  $qty  hundredths
     */
    public static function lineTotal(int $qty, int $unitPrice, int $discount = 0): int
    {
        $gross = Quantity::priceOf($qty, $unitPrice);

        if ($discount < 0 || $discount > $gross) {
            throw new BusinessRuleViolation(__('invoices.line_discount_too_high', ['amount' => Money::format($gross)]));
        }

        return $gross - $discount;
    }

    /**
     * @param  list<int>  $lineTotals
     * @return array{subtotal: int, discount_amount: int, tax_amount: int, total: int}
     */
    public static function totals(array $lineTotals, int $discount, bool $taxEnabled, string $taxRate, bool $taxInclusive): array
    {
        $subtotal = array_sum($lineTotals);

        if ($discount < 0 || $discount > $subtotal) {
            throw new BusinessRuleViolation(__('invoices.discount_too_high', ['amount' => Money::format($subtotal)]));
        }

        $base = $subtotal - $discount;

        if (! $taxEnabled) {
            return ['subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => 0, 'total' => $base];
        }

        if ($taxInclusive) {
            return ['subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => Money::includedTax($base, $taxRate), 'total' => $base];
        }

        $tax = Money::percentOf($base, $taxRate);

        return ['subtotal' => $subtotal, 'discount_amount' => $discount, 'tax_amount' => $tax, 'total' => $base + $tax];
    }
}
