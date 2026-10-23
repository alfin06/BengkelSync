<?php

namespace App\Support;

/**
 * Indonesian vehicle plate numbers: region letters, number, suffix letters, e.g. `B 1234 XYZ`.
 */
final class PlateNumber
{
    private const PATTERN = '/^([A-Z]{1,2})(\d{1,4})([A-Z]{0,3})$/';

    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $compact = preg_replace('/[^A-Z0-9]/', '', strtoupper($input)) ?? '';

        if (! preg_match(self::PATTERN, $compact, $matches)) {
            return null;
        }

        return trim($matches[1].' '.$matches[2].' '.$matches[3]);
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }
}
