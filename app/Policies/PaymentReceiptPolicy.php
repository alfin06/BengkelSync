<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Invoicing\InvoiceLock;

/**
 * Tenant and branch isolation come from the PaymentReceipt global scopes.
 */
class PaymentReceiptPolicy
{
    public function __construct(private readonly InvoiceLock $lock) {}

    public function view(User $user, PaymentReceipt $receipt): bool
    {
        return $user->can(Permission::PaymentView->value);
    }

    /**
     * Cancel every remaining line of a kwitansi at once.
     */
    public function void(User $user, PaymentReceipt $receipt): bool
    {
        return $user->can(Permission::PaymentDelete->value) && $this->lock->allows($user, $receipt->invoice);
    }
}
