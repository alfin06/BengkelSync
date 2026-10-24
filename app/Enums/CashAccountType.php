<?php

namespace App\Enums;

enum CashAccountType: string
{
    case CashDrawer = 'cash_drawer';
    case PettyCash = 'petty_cash';
    case Bank = 'bank';
    case Other = 'other';

    /**
     * Physical money: the balance can never go below zero, and anyone with cash.view sees
     * it. Bank and other accounts may go negative (the shop may not record every bank
     * movement) and their balance is financial information.
     */
    public function isPhysical(): bool
    {
        return $this === self::CashDrawer || $this === self::PettyCash;
    }

    public function label(): string
    {
        return __('cash.account_type.'.$this->value);
    }
}
