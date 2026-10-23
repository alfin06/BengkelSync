<?php

use App\Support\PlateNumber;

it('normalizes plate numbers', function (string $input, string $expected) {
    expect(PlateNumber::normalize($input))->toBe($expected);
})->with([
    ['b1234xyz', 'B 1234 XYZ'],
    ['B 1234 XYZ', 'B 1234 XYZ'],
    [' h-1234-ab ', 'H 1234 AB'],
    ['AB 12 C', 'AB 12 C'],
    'no suffix' => ['B 1', 'B 1'],
]);

it('rejects values that are not plate numbers', function (?string $input) {
    expect(PlateNumber::normalize($input))->toBeNull()
        ->and(PlateNumber::isValid($input))->toBeFalse();
})->with([null, '', '1234', 'ABC 1234 X', 'B 12345 X', 'B 1234 WXYZ']);
