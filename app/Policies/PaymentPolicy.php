<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

/**
 * Tenant and branch isolation come from the Payment global scopes.
 */
class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PaymentView->value);
    }

    /**
     * Cancel one line of a kwitansi (with a reason).
     */
    public function void(User $user, Payment $payment): bool
    {
        return $user->can(Permission::PaymentDelete->value) && ! $payment->isVoided();
    }
}
