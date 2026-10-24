<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Invoicing\InvoiceLock;

/**
 * Tenant and branch isolation come from the Invoice global scopes; this policy checks
 * permissions and the status.
 */
class InvoicePolicy
{
    public function __construct(private readonly InvoiceLock $lock) {}

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
     * Edit an issued invoice (SPEC section 6): an unpaid one with invoice.update, one with
     * payments with invoice.update_paid. Cancelled invoices are never edited.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        if ($invoice->status === InvoiceStatus::Void || ! $this->lock->allows($user, $invoice)) {
            return false;
        }

        return $invoice->amount_paid === 0
            ? $user->can(Permission::InvoiceUpdate->value)
            : $user->can(Permission::InvoiceUpdatePaid->value);
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
            && $this->lock->allows($user, $invoice)
            && $invoice->amount_paid === 0;
    }
}
