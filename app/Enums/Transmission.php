<?php

namespace App\Enums;

enum Transmission: string
{
    case Manual = 'manual';
    case Automatic = 'automatic';
    case Cvt = 'cvt';

    public function label(): string
    {
        return __('vehicles.transmission.'.$this->value);
    }
}
