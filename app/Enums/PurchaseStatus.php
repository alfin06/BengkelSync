<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';
    case Void = 'void';

    public static function for(int $total, int $paid): self
    {
        return match (true) {
            $paid <= 0 => $total === 0 ? self::Paid : self::Unpaid,
            $paid >= $total => self::Paid,
            default => self::Partial,
        };
    }

    public function label(): string
    {
        return __('purchases.status.'.$this->value);
    }
}
