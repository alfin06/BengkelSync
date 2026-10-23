<?php

namespace App\Support;

use InvalidArgumentException;
use OverflowException;

/**
 * Integer Rupiah helpers (CLAUDE.md rule 5). No floats anywhere: rates are converted
 * to basis points (1/100 of a percent) and every division rounds half-up.
 */
final class Money
{
    /** Largest amount that can be multiplied by a rate in basis points without overflowing. */
    private const MAX_AMOUNT = 400_000_000_000_000;

    public static function format(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';

        return $sign.'Rp '.number_format(abs($amount), 0, ',', '.');
    }

    /**
     * Parse user input such as "1.250.000", "Rp 1.250.000" or "1250000,00" into Rupiah.
     */
    public static function parse(string|int $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        $clean = str_replace(['Rp', 'rp', 'RP', ' ', "\u{00A0}", '.'], '', trim($value));
        $negative = str_starts_with($clean, '-');
        $clean = ltrim($clean, '-');

        if (str_contains($clean, ',')) {
            [$clean, $fraction] = explode(',', $clean, 2);

            if (! preg_match('/^0{1,2}$/', $fraction)) {
                throw new InvalidArgumentException("Rupiah amounts cannot have cents: [{$value}].");
            }
        }

        if ($clean === '' || ! ctype_digit($clean)) {
            throw new InvalidArgumentException("Not a valid Rupiah amount: [{$value}].");
        }

        $amount = (int) $clean;

        return $negative ? -$amount : $amount;
    }

    /**
     * A percentage of an amount, e.g. tax added on top or a card surcharge.
     *
     * @param  string|int  $rate  percent with at most 2 decimals, e.g. "11", "11.00" or "2.5"
     */
    public static function percentOf(int $amount, string|int $rate): int
    {
        self::guardAmount($amount);

        return self::divideHalfUp($amount * self::basisPoints($rate), 10_000);
    }

    /**
     * The tax contained in a tax-inclusive total: round(total × rate / (100 + rate)) (SPEC section 5).
     */
    public static function includedTax(int $total, string|int $rate): int
    {
        self::guardAmount($total);
        $basisPoints = self::basisPoints($rate);

        return self::divideHalfUp($total * $basisPoints, 10_000 + $basisPoints);
    }

    /**
     * Convert a percent string (0–100, at most 2 decimals) to basis points without floats: "11.5" → 1150.
     */
    public static function basisPoints(string|int $rate): int
    {
        $rate = trim((string) $rate);

        if (! preg_match('/^(\d{1,3})(?:\.(\d{1,2}))?$/', $rate, $matches)) {
            throw new InvalidArgumentException("Not a valid percentage: [{$rate}].");
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '0', 2, '0');
        $basisPoints = $whole * 100 + $fraction;

        if ($basisPoints > 10_000) {
            throw new InvalidArgumentException("Percentage cannot exceed 100: [{$rate}].");
        }

        return $basisPoints;
    }

    /**
     * Integer division rounded half away from zero.
     */
    public static function divideHalfUp(int $dividend, int $divisor): int
    {
        if ($divisor <= 0) {
            throw new InvalidArgumentException('Divisor must be positive.');
        }

        $quotient = intdiv(abs($dividend) * 2 + $divisor, $divisor * 2);

        return $dividend < 0 ? -$quotient : $quotient;
    }

    private static function guardAmount(int $amount): void
    {
        if (abs($amount) > self::MAX_AMOUNT) {
            throw new OverflowException("Amount is too large: [{$amount}].");
        }
    }
}
