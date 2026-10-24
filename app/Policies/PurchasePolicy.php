<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PurchaseView->value);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::PurchaseView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PurchaseCreate->value);
    }

    /**
     * Revising a saved purchase (with a reason).
     */
    public function update(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::PurchaseUpdate->value) && $purchase->status !== PurchaseStatus::Void;
    }

    public function void(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::PurchaseDelete->value) && $purchase->status !== PurchaseStatus::Void;
    }

    /**
     * Paying a purchase later, from any account the user may use ("Hutang Supplier").
     */
    public function pay(User $user, Purchase $purchase): bool
    {
        return $user->can(Permission::SupplierPaymentCreate->value) && $purchase->outstanding() > 0;
    }

    /**
     * The supplier-debt list.
     */
    public function viewDebts(User $user): bool
    {
        return $user->can(Permission::SupplierPaymentView->value);
    }
}
