<?php

namespace App\Notifications;

use App\Models\Purchase;
use Illuminate\Notifications\Notification;

/**
 * Bell notification for supplier debt: 3 days before the due date ("due_soon") and once it
 * is overdue ("overdue"). Raw values only; the text is built in the reader's language.
 */
class PurchaseDueNotification extends Notification
{
    public const DUE_SOON = 'purchase_due_soon';

    public const OVERDUE = 'purchase_overdue';

    public function __construct(
        private readonly Purchase $purchase,
        private readonly string $kind,
        private readonly string $supplierName,
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
            'purchase_id' => $this->purchase->id,
            'number' => $this->purchase->number,
            'supplier_name' => $this->supplierName,
            'branch_name' => $this->branchName,
            'outstanding' => $this->purchase->outstanding(),
            'due_date' => $this->purchase->due_date?->toDateString(),
        ];
    }
}
