<?php

namespace App\Rules;

use App\Support\PlateNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class IndonesianPlate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PlateNumber::isValid($value)) {
            $fail('validation.plate')->translate();
        }
    }
}
