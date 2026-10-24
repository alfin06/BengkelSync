<?php

namespace App\Services\Invoicing;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * The invoice lock period (SPEC section 6): invoices dated more than N days ago can no
 * longer be edited or cancelled, nor their payments cancelled, so closed months stay
 * stable for the books. Users with invoice.update_locked (Owner) can still fix mistakes;
 * such changes are marked in the history.
 */
class InvoiceLock
{
    public function __construct(private readonly TenantContext $context) {}

    public function isLocked(Invoice $invoice): bool
    {
        $days = $this->context->requireTenant()->invoiceLockDays();

        if ($days === null) {
            return false;
        }

        $cutoff = Carbon::parse($this->context->today())->subDays($days)->toDateString();

        return $invoice->issue_date->toDateString() < $cutoff;
    }

    /**
     * May this user change this invoice (or its payments) as far as the lock goes?
     */
    public function allows(User $user, Invoice $invoice): bool
    {
        return ! $this->isLocked($invoice) || $user->can(Permission::InvoiceUpdateLocked->value);
    }
}
