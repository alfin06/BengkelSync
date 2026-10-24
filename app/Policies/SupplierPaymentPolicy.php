<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\SupplierPayment;
use App\Models\User;

class SupplierPaymentPolicy
{
    /**
     * Cancel a wrong payment (the money goes back into its account).
     */
    public function void(User $user, SupplierPayment $payment): bool
    {
        return $user->can(Permission::SupplierPaymentDelete->value) && ! $payment->isVoided();
    }
}
