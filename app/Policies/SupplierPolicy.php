<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::SupplierView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SupplierCreate->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SupplierUpdate->value);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SupplierDelete->value);
    }
}
