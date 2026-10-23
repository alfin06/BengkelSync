<?php

use App\Support\Phone;

it('normalizes Indonesian numbers to +62', function (string $input, string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['081234567890', '+6281234567890'],
    ['0812-3456-7890', '+6281234567890'],
    ['0812 3456 7890', '+6281234567890'],
    ['6281234567890', '+6281234567890'],
    ['+62 812 3456 7890', '+6281234567890'],
    ['81234567890', '+6281234567890'],
    'landline with area code' => ['(024) 7654321', '+62247654321'],
]);

it('rejects invalid numbers', function (?string $input) {
    expect(Phone::normalize($input))->toBeNull()
        ->and(Phone::isValid($input))->toBeFalse();
})->with([null, '', '0812', '0812345678901234', '08abc4567890', '00812345678']);

it('formats mobile numbers for display', function () {
    expect(Phone::format('+6281234567890'))->toBe('0812-3456-7890')
        ->and(Phone::format('+62812345678'))->toBe('0812-3456-78')
        ->and(Phone::format('+62247654321'))->toBe('0247654321');
});
