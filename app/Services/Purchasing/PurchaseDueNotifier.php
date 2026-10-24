<?php

namespace App\Services\Purchasing;

use App\Enums\Permission;
use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PurchaseDueNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Notification;

/**
 * Daily supplier-debt reminders (pilot decision): a bell notification 3 days before a
 * purchase is due and one when it is overdue, to the branch's users who can pay suppliers.
 * Each purchase is notified once per kind, so running the job twice changes nothing.
 */
class PurchaseDueNotifier
{
    public const DAYS_BEFORE = 3;

    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return int number of notifications sent (one per purchase per kind)
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
        $open = fn () => Purchase::query()
            ->with('supplier')
            ->whereIn('status', [PurchaseStatus::Unpaid, PurchaseStatus::Partial])
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
            ->filter(fn (User $user) => $user->can(Permission::SupplierPaymentCreate->value) && $user->canAccessBranch($branch));

        foreach ($dueSoon as $purchase) {
            Notification::send($recipients, new PurchaseDueNotification($purchase, PurchaseDueNotification::DUE_SOON, $purchase->supplier->name, $branch->name));
            $purchase->forceFill(['due_soon_notified_at' => now()])->saveQuietly();
        }

        foreach ($overdue as $purchase) {
            Notification::send($recipients, new PurchaseDueNotification($purchase, PurchaseDueNotification::OVERDUE, $purchase->supplier->name, $branch->name));
            // An overdue bill never needs the "due soon" reminder any more.
            $purchase->forceFill(['overdue_notified_at' => now(), 'due_soon_notified_at' => $purchase->due_soon_notified_at ?? now()])->saveQuietly();
        }

        return $dueSoon->count() + $overdue->count();
    }
}
