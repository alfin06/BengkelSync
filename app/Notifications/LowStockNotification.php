<?php

namespace App\Notifications;

use App\Models\Branch;
use App\Models\Part;
use App\Support\Quantity;
use Illuminate\Notifications\Notification;

/**
 * In-app notification (bell icon) when a part reaches its low-stock threshold in a branch.
 * Stored in the notifications table, which only its own user can read.
 */
class LowStockNotification extends Notification
{
    public function __construct(
        private readonly Part $part,
        private readonly Branch $branch,
        private readonly int $qty,
        private readonly int $threshold,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Raw values only; the text is built in the user's own language when it is shown.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'low_stock',
            'part_id' => $this->part->id,
            'part_name' => $this->part->name,
            'unit' => $this->part->unit->value,
            'branch_id' => $this->branch->id,
            'branch_name' => $this->branch->name,
            'qty' => Quantity::toDecimal($this->qty),
            'threshold' => Quantity::toDecimal($this->threshold),
        ];
    }
}
