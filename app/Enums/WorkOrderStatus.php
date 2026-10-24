<?php

namespace App\Enums;

/**
 * Work order status flow (SPEC section 4):
 * draft → queued → in_progress ⇄ waiting_parts → completed → invoiced → closed,
 * cancelled from any state before invoiced. Invoiced and closed are set by the invoice
 * module, never by hand.
 */
enum WorkOrderStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case InProgress = 'in_progress';
    case WaitingParts = 'waiting_parts';
    case Completed = 'completed';
    case Invoiced = 'invoiced';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /**
     * Statuses a user may move to from this one ("cancelled" has its own action). The
     * first is the usual next step and gets the main button.
     *
     * @return list<self>
     */
    public function nextManual(): array
    {
        return match ($this) {
            self::Draft => [self::Queued],
            // Quick jobs (an oil change) may be finished straight from the queue.
            self::Queued => [self::InProgress, self::Completed],
            self::InProgress => [self::Completed, self::WaitingParts],
            self::WaitingParts => [self::InProgress],
            // Reopen when something was missed; only until an invoice exists.
            self::Completed => [self::InProgress],
            self::Invoiced, self::Closed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->nextManual(), true);
    }

    /**
     * Before the invoice: services, parts and the checklist can still change.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Queued, self::InProgress, self::WaitingParts, self::Completed], true);
    }

    public function isCancellable(): bool
    {
        return $this->isEditable();
    }

    /**
     * Still in the workshop (shown by default in the list).
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Queued, self::InProgress, self::WaitingParts], true);
    }

    public function label(): string
    {
        return __('work_orders.status.'.$this->value);
    }

    /**
     * The button text for moving to this status ("Mulai kerjakan", "Selesai", ...).
     */
    public function actionLabel(): string
    {
        return __('work_orders.action.'.$this->value);
    }
}
