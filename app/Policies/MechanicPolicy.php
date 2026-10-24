<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Mechanic;
use App\Models\User;

/**
 * Tenant and branch isolation come from the Mechanic global scopes (a mechanic of another
 * branch is never found); this policy only checks permissions.
 */
class MechanicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::MechanicView->value);
    }

    public function view(User $user, Mechanic $mechanic): bool
    {
        return $user->can(Permission::MechanicView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::MechanicCreate->value);
    }

    public function update(User $user, Mechanic $mechanic): bool
    {
        return $user->can(Permission::MechanicUpdate->value);
    }

    public function delete(User $user, Mechanic $mechanic): bool
    {
        return $user->can(Permission::MechanicDelete->value);
    }

    /**
     * See the full NIK and the KTP photo, and change them.
     */
    public function viewIdentity(User $user, ?Mechanic $mechanic = null): bool
    {
        return $user->can(Permission::MechanicViewIdentity->value);
    }
}
