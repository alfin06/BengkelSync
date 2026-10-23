<?php

use App\Support\Money;

it('formats integer rupiah with dot thousands separators', function (int $amount, string $expected) {
    expect(Money::format($amount))->toBe($expected);
})->with([
    [0, 'Rp 0'],
    [500, 'Rp 500'],
    [1_250_000, 'Rp 1.250.000'],
    [-75_000, '-Rp 75.000'],
]);

it('parses typed amounts', function (string|int $input, int $expected) {
    expect(Money::parse($input))->toBe($expected);
})->with([
    ['1.250.000', 1_250_000],
    ['Rp 1.250.000', 1_250_000],
    ['1250000', 1_250_000],
    ['1.250.000,00', 1_250_000],
    ['-5.000', -5_000],
    [42, 42],
]);

it('rejects amounts with cents or garbage', function (string $input) {
    Money::parse($input);
})->with(['1.250,50', 'abc', '', 'Rp'])->throws(InvalidArgumentException::class);

it('calculates a percentage rounded half up', function (int $amount, string $rate, int $expected) {
    expect(Money::percentOf($amount, $rate))->toBe($expected);
})->with([
    'PPN 11%' => [1_000_000, '11', 110_000],
    'rounds .5 up' => [50, '11', 6],        // 5.5 → 6
    'rounds below .5 down' => [40, '11', 4], // 4.4 → 4
    'card surcharge 3%' => [333_333, '3', 10_000], // 9999.99 → 10000
    'decimal rate' => [200_000, '2.5', 5_000],
    'negative amounts mirror' => [-50, '11', -6],
]);

it('extracts tax from a tax-inclusive total', function () {
    // 1.110.000 including 11% → 110.000 tax
    expect(Money::includedTax(1_110_000, '11'))->toBe(110_000)
        // 100.000 × 11 / 111 = 9909.9 → 9910
        ->and(Money::includedTax(100_000, '11.00'))->toBe(9_910);
});

it('converts percentages to basis points without floats', function () {
    expect(Money::basisPoints('11'))->toBe(1_100)
        ->and(Money::basisPoints('11.5'))->toBe(1_150)
        ->and(Money::basisPoints('0.01'))->toBe(1)
        ->and(Money::basisPoints(100))->toBe(10_000);
});

it('rejects invalid or out of range percentages', function (string $rate) {
    Money::basisPoints($rate);
})->with(['-1', '100.01', '11.123', 'abc'])->throws(InvalidArgumentException::class);

it('refuses amounts that could overflow', function () {
    Money::percentOf(PHP_INT_MAX, '11');
})->throws(OverflowException::class);
