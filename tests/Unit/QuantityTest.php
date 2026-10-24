<?php

use App\Support\Quantity;

it('parses typed quantities into hundredths', function (string|int $input, int $expected) {
    expect(Quantity::parse($input))->toBe($expected);
})->with([
    ['4', 400],
    ['4.2', 420],
    ['4,2', 420],
    ['4,25', 425],
    ['0.5', 50],
    ['1.000,5', 100050],
    ['-3', -300],
    [7, 700],
]);

it('rejects invalid quantities', function (string $input) {
    expect(Quantity::isValid($input))->toBeFalse();
})->with(['', 'abc', '4.255', '4,2,1', '1e3']);

it('converts to and from the database decimal', function () {
    expect(Quantity::toDecimal(420))->toBe('4.20')
        ->and(Quantity::toDecimal(5))->toBe('0.05')
        ->and(Quantity::toDecimal(-150))->toBe('-1.50')
        ->and(Quantity::fromDecimal('4.20'))->toBe(420)
        ->and(Quantity::fromDecimal(null))->toBe(0);
});

it('formats quantities the Indonesian way, without trailing zeros', function () {
    expect(Quantity::format(420))->toBe('4,2')
        ->and(Quantity::format(400))->toBe('4')
        ->and(Quantity::format(425))->toBe('4,25')
        ->and(Quantity::format(125050))->toBe('1.250,5')
        ->and(Quantity::format(-50))->toBe('-0,5');
});

it('prices a decimal quantity in whole Rupiah, rounded half up', function () {
    expect(Quantity::priceOf(420, 55_000))->toBe(231_000)
        ->and(Quantity::priceOf(33, 1_000))->toBe(330)
        ->and(Quantity::priceOf(5, 101))->toBe(5); // 5.05 → 5
});
