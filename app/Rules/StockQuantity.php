<?php

namespace App\Rules;

use App\Enums\PartUnit;
use App\Support\Quantity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A quantity typed by staff: "4", "4,2" or "4.25", zero or more. Counted units (pcs, set)
 * must be whole numbers; pass the part's unit when it is known.
 */
final class StockQuantity implements ValidationRule
{
    public function __construct(private readonly ?PartUnit $unit = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Quantity::isValid($value)) {
            $fail('validation.quantity')->translate();

            return;
        }

        /** @var string|int $value */
        $hundredths = Quantity::parse($value);

        if ($hundredths < 0) {
            $fail('validation.quantity')->translate();

            return;
        }

        if ($this->unit !== null && ! $this->unit->allowsDecimals() && ! Quantity::isWhole($hundredths)) {
            $fail('validation.whole_quantity')->translate(['unit' => $this->unit->label()]);
        }
    }
}
