<?php

namespace App\Enums;

/**
 * Units a part is counted in. Only measured units allow decimal quantities
 * (4,2 liter of oil); counted units (pcs, set) must be whole numbers.
 */
enum PartUnit: string
{
    case Piece = 'pcs';
    case Set = 'set';
    case Pair = 'pair';
    case Bottle = 'bottle';
    case Can = 'can';
    case Liter = 'liter';
    case Meter = 'meter';
    case Kilogram = 'kg';

    public function allowsDecimals(): bool
    {
        return in_array($this, [self::Liter, self::Meter, self::Kilogram], true);
    }

    public function label(): string
    {
        return __('parts.unit.'.$this->value);
    }
}
