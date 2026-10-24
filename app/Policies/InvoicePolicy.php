<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

/**
 * Tenant and branch isolation come from the Invoice global scopes; this policy checks
 * permissions and the status.
 */
class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InvoiceView->value);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::InvoiceView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InvoiceCreate->value);
    }

    /**
     * A discount on a line or on the whole invoice.
     */
    public function discount(User $user): bool
    {
        return $user->can(Permission::InvoiceDiscount->value);
    }

    /**
     * Turn tax on or off for one invoice, against the branch setting.
     */
    public function toggleTax(User $user): bool
    {
        return $user->can(Permission::InvoiceToggleTax->value);
    }

    /**
     * Take a payment ("Bayar").
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::PaymentCreate->value) && $invoice->outstanding() > 0;
    }

    /**
     * The customer-debt list ("Piutang pelanggan").
     */
    public function viewDebts(User $user): bool
    {
        return $user->can(Permission::PaymentView->value);
    }

    /**
     * Cancel an invoice made by mistake; only while nothing is paid.
     */
    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::InvoiceVoid->value)
            && $invoice->status !== InvoiceStatus::Void
            && $invoice->amount_paid === 0;
    }
}
