<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Customer;
use App\Models\User;

/**
 * Tenant isolation is already guaranteed by the Customer tenant scope (a customer of
 * another tenant is never found); this policy only checks permissions.
 */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CustomerView->value);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomerView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CustomerCreate->value);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomerUpdate->value);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can(Permission::CustomerDelete->value);
    }
}
