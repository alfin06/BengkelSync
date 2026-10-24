<?php

namespace App\Enums;

/**
 * Why stock changed (SPEC section 4, stock_movements.type).
 */
enum StockMovementType: string
{
    case Purchase = 'purchase';
    case WorkOrderUse = 'work_order_use';
    // A part sold on a walk-in invoice (no work order).
    case Sale = 'sale';
    case InvoiceEdit = 'invoice_edit';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';

    public function label(): string
    {
        return __('stock.movement.'.$this->value);
    }
}
