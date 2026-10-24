<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;
use App\Services\Invoicing\InvoiceLock;

/**
 * Tenant and branch isolation come from the Payment global scopes.
 */
class PaymentPolicy
{
    public function __construct(private readonly InvoiceLock $lock) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentView->value);
    }

    /**
     * Cancel one line of a kwitansi (with a reason); not on a locked invoice without
     * invoice.update_locked.
     */
    public function void(User $user, Payment $payment): bool
    {
        return $user->can(Permission::PaymentDelete->value)
            && ! $payment->isVoided()
            && $this->lock->allows($user, $payment->invoice);
    }
}
