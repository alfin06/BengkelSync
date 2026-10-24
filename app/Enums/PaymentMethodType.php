<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Qris = 'qris';
    case Debit = 'debit';
    case CreditCard = 'credit_card';
    case Other = 'other';

    public function label(): string
    {
        return __('cash.method_type.'.$this->value);
    }
}
