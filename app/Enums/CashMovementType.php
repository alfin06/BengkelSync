<?php

namespace App\Enums;

/**
 * Why money moved (SPEC section 4, cash_movements.type).
 */
enum CashMovementType: string
{
    case CustomerPayment = 'customer_payment';
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return __('cash.movement.'.$this->value);
    }
}
