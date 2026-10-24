<?php

namespace App\Support;

/**
 * Rupiah in Indonesian words for the kwitansi: 1250000 → "satu juta dua ratus lima puluh
 * ribu rupiah". Kwitansi are written in Indonesian whatever the reader's language.
 */
final class Terbilang
{
    private const UNITS = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

    private const SCALES = [
        1_000_000_000_000 => 'triliun',
        1_000_000_000 => 'miliar',
        1_000_000 => 'juta',
    ];

    public static function rupiah(int $amount): string
    {
        return $amount === 0 ? 'nol rupiah' : self::words($amount).' rupiah';
    }

    private static function words(int $number): string
    {
        foreach (self::SCALES as $size => $name) {
            if ($number >= $size) {
                return self::join(self::words(intdiv($number, $size)).' '.$name, $number % $size);
            }
        }

        if ($number >= 1000) {
            // 1.000–1.999 is "seribu", not "satu ribu".
            $thousands = intdiv($number, 1000);
            $prefix = $thousands === 1 ? 'seribu' : self::words($thousands).' ribu';

            return self::join($prefix, $number % 1000);
        }

        if ($number >= 100) {
            $hundreds = intdiv($number, 100);
            $prefix = $hundreds === 1 ? 'seratus' : self::UNITS[$hundreds].' ratus';

            return self::join($prefix, $number % 100);
        }

        if ($number >= 20) {
            return self::join(self::UNITS[intdiv($number, 10)].' puluh', $number % 10);
        }

        if ($number >= 12) {
            return self::UNITS[$number - 10].' belas';
        }

        return self::UNITS[$number];
    }

    private static function join(string $prefix, int $rest): string
    {
        return $rest === 0 ? $prefix : $prefix.' '.self::words($rest);
    }
}
