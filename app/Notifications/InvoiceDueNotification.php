<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Notifications\Notification;

/**
 * Bell notification for customer debt: 3 days before the due date ("due_soon") and once
 * it is overdue ("overdue"). Raw values only; the text is built in the reader's language.
 */
class InvoiceDueNotification extends Notification
{
    public const DUE_SOON = 'invoice_due_soon';

    public const OVERDUE = 'invoice_overdue';

    public function __construct(
        private readonly Invoice $invoice,
        private readonly string $kind,
        private readonly ?string $customerName,
        private readonly string $branchName,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'invoice_id' => $this->invoice->id,
            'number' => $this->invoice->number,
            // Null: "Pelanggan umum".
            'customer_name' => $this->customerName,
            'branch_name' => $this->branchName,
            'outstanding' => $this->invoice->outstanding(),
            'due_date' => $this->invoice->due_date?->toDateString(),
        ];
    }
}
