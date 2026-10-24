<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Invoice;
use App\Models\WorkOrder;

/**
 * Keeps an invoice's status, and its work order, in line with what is paid. Used after
 * every payment, cancelled payment and invoice edit, inside their transactions:
 * a fully paid work order is "closed", otherwise "invoiced".
 */
class InvoiceSettlement
{
    public function apply(Invoice $invoice, int $paid): void
    {
        $status = InvoiceStatus::for($invoice->total, $paid);
        $invoice->forceFill(['amount_paid' => $paid, 'status' => $status])->save();

        if ($invoice->work_order_id === null) {
            return;
        }

        $workOrder = WorkOrder::query()->whereKey($invoice->work_order_id)->lockForUpdate()->firstOrFail();
        $next = $status === InvoiceStatus::Paid ? WorkOrderStatus::Closed : WorkOrderStatus::Invoiced;

        if ($workOrder->status !== $next) {
            $workOrder->forceFill(['status' => $next])->save();
        }
    }
}
