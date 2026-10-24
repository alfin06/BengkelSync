<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\InvoiceDueNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Daily customer-debt reminders ("Piutang", pilot decision): a bell notification 3 days
 * before an invoice is due and one when it is overdue, to the branch's users who take
 * payments. Each invoice is notified once per kind, so running the job twice changes nothing.
 */
class InvoiceDueNotifier
{
    public const DAYS_BEFORE = 3;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return int number of notifications sent (one per invoice per kind)
     */
    public function run(): int
    {
        $sent = 0;

        foreach (Tenant::query()->where('status', 'active')->get() as $tenant) {
            $branches = $this->context->run($tenant, null, fn () => Branch::query()->get());

            foreach ($branches as $branch) {
                $sent += $this->context->run($tenant, $branch, fn () => $this->forBranch($branch));
            }
        }

        return $sent;
    }

    private function forBranch(Branch $branch): int
    {
        // "Today" in the branch's own timezone (rule 11).
        $today = now($branch->timezone)->startOfDay();
        $open = fn () => Invoice::query()
            ->with('customer')
            ->whereIn('status', [InvoiceStatus::Unpaid, InvoiceStatus::Partial])
            ->whereNotNull('due_date');

        $dueSoon = $open()
            ->whereNull('due_soon_notified_at')
            ->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(self::DAYS_BEFORE)->toDateString()])
            ->get();

        $overdue = $open()
            ->whereNull('overdue_notified_at')
            ->where('due_date', '<', $today->toDateString())
            ->get();

        if ($dueSoon->isEmpty() && $overdue->isEmpty()) {
            return 0;
        }

        $recipients = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->can(Permission::PaymentCreate->value) && $user->canAccessBranch($branch));

        foreach ($dueSoon as $invoice) {
            Notification::send($recipients, new InvoiceDueNotification($invoice, InvoiceDueNotification::DUE_SOON, $invoice->customer?->name, $branch->name));
            $invoice->forceFill(['due_soon_notified_at' => now()])->saveQuietly();
        }

        foreach ($overdue as $invoice) {
            Notification::send($recipients, new InvoiceDueNotification($invoice, InvoiceDueNotification::OVERDUE, $invoice->customer?->name, $branch->name));
            // An overdue invoice never needs the "due soon" reminder any more.
            $invoice->forceFill(['overdue_notified_at' => now(), 'due_soon_notified_at' => $invoice->due_soon_notified_at ?? now()])->saveQuietly();
        }

        return $dueSoon->count() + $overdue->count();
    }
}
