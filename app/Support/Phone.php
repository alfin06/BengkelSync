<?php

namespace App\Support;

/**
 * Indonesian phone numbers. Input is accepted as `08…`, `628…`, `+62 8…` (spaces, dashes
 * and dots allowed) and stored as E.164: `+628…`.
 */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = preg_replace('/[\s\-.()]/', '', trim($input)) ?? '';

        $national = match (true) {
            str_starts_with($digits, '+62') => substr($digits, 3),
            str_starts_with($digits, '62') => substr($digits, 2),
            str_starts_with($digits, '0') => substr($digits, 1),
            default => $digits,
        };

        // National numbers (without the leading 0) are 8 to 12 digits and never start with 0.
        if (! preg_match('/^[1-9]\d{7,11}$/', $national)) {
            return null;
        }

        return '+62'.$national;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /**
     * Display form: mobile numbers as `0812-3456-7890`, other numbers as `0` + national digits.
     */
    public static function format(?string $phone): string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null) {
            return (string) $phone;
        }

        $local = '0'.substr($normalized, 3);

        if (! str_starts_with($local, '08')) {
            return $local;
        }

        return implode('-', array_filter([
            substr($local, 0, 4),
            substr($local, 4, 4),
            substr($local, 8),
        ], fn (string $part): bool => $part !== ''));
    }
}
