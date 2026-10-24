<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * The permission matrix. Role is not tenant-scoped (see App\Models\Role), so every check
 * also compares the tenant: another shop's role is never visible or editable.
 */
class RolePolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::RoleView->value);
    }

    public function view(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RoleView->value) && $role->tenant_id === $actor->tenant_id;
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::RoleCreate->value);
    }

    public function update(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RoleUpdate->value) && $role->tenant_id === $actor->tenant_id && ! $role->isOwnerRole();
    }

    public function delete(User $actor, Role $role): bool
    {
        return $actor->can(Permission::RoleDelete->value) && $role->tenant_id === $actor->tenant_id && ! $role->isOwnerRole();
    }
}
