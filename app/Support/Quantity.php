<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Stock quantities with up to 2 decimals (e.g. 4.2 litres of oil), without floats.
 *
 * In PHP a quantity is an int of hundredths ("centi-units"): 4.2 L = 420. The database
 * stores DECIMAL(12,2); convert with fromDecimal() / toDecimal() at the boundary.
 */
final class Quantity
{
    public const SCALE = 100;

    /** Largest quantity that fits DECIMAL(12,2): 9 999 999 999.99. */
    public const MAX = 999_999_999_999;

    /**
     * User input such as "4", "4.2", "4,25" or "1.000,5" (Indonesian thousands) → hundredths.
     */
    public static function parse(string|int $value): int
    {
        if (is_int($value)) {
            return self::guard($value * self::SCALE);
        }

        $clean = str_replace([' ', "\u{00A0}"], '', trim($value));

        // Indonesian style "1.000,5": dots are thousands separators when a comma is present.
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        }

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $clean, $matches)) {
            throw new InvalidArgumentException("Not a valid quantity: [{$value}].");
        }

        $hundredths = (int) $matches[2] * self::SCALE + (int) str_pad($matches[3] ?? '0', 2, '0');

        return self::guard($matches[1] === '-' ? -$hundredths : $hundredths);
    }

    public static function isValid(mixed $value): bool
    {
        if (! is_string($value) && ! is_int($value)) {
            return false;
        }

        try {
            self::parse($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Database value ("4.20", as Laravel's decimal cast returns it) → hundredths.
     */
    public static function fromDecimal(string|int|null $value): int
    {
        return $value === null ? 0 : self::parse((string) $value);
    }

    /**
     * Hundredths → database value: 420 → "4.20".
     */
    public static function toDecimal(int $hundredths): string
    {
        $sign = $hundredths < 0 ? '-' : '';
        $abs = abs($hundredths);

        return $sign.intdiv($abs, self::SCALE).'.'.str_pad((string) ($abs % self::SCALE), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Display: 420 → "4,2", 400 → "4", 125050 → "1.250,5".
     */
    public static function format(int $hundredths): string
    {
        $whole = number_format(intdiv(abs($hundredths), self::SCALE), 0, ',', '.');
        $fraction = rtrim(str_pad((string) (abs($hundredths) % self::SCALE), 2, '0', STR_PAD_LEFT), '0');

        return ($hundredths < 0 ? '-' : '').$whole.($fraction === '' ? '' : ','.$fraction);
    }

    public static function isWhole(int $hundredths): bool
    {
        return $hundredths % self::SCALE === 0;
    }

    /**
     * Total price of a quantity, rounded half-up to whole Rupiah: 4.2 L × Rp 55.000 = Rp 231.000.
     */
    public static function priceOf(int $hundredths, int $unitPrice): int
    {
        return Money::divideHalfUp($hundredths * $unitPrice, self::SCALE);
    }

    private static function guard(int $hundredths): int
    {
        if (abs($hundredths) > self::MAX) {
            throw new InvalidArgumentException('Quantity is too large.');
        }

        return $hundredths;
    }
}
