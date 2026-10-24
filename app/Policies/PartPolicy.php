<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Part;
use App\Models\User;

class PartPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PartView->value);
    }

    public function view(User $user, Part $part): bool
    {
        return $user->can(Permission::PartView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::PartCreate->value);
    }

    public function update(User $user, Part $part): bool
    {
        return $user->can(Permission::PartUpdate->value);
    }

    public function delete(User $user, Part $part): bool
    {
        return $user->can(Permission::PartDelete->value);
    }

    /**
     * Stock opname / correction, including the opening balance.
     */
    public function adjustStock(User $user, ?Part $part = null): bool
    {
        return $user->can(Permission::StockAdjust->value);
    }

    /**
     * Low-stock threshold and shelf location in the active branch.
     */
    public function configureStock(User $user, Part $part): bool
    {
        return $user->can(Permission::PartUpdate->value);
    }

    /**
     * Purchase cost is financial information.
     */
    public function viewCost(User $user, ?Part $part = null): bool
    {
        return $user->can(Permission::PartUpdate->value) || $user->can(Permission::DashboardViewFinancials->value);
    }
}
