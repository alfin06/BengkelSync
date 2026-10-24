<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Staff accounts of the same tenant (the User tenant scope keeps other shops out).
 * Owner accounts can only be changed by an owner; nobody changes their own account here
 * (that is the profile page), so nobody can lock themselves out.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UserView->value);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UserCreate->value);
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->can(Permission::UserUpdate->value)
            && $actor->isNot($user)
            && ($actor->isOwner() || ! $user->isOwner());
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->can(Permission::UserDelete->value)
            && $actor->isNot($user)
            && ($actor->isOwner() || ! $user->isOwner());
    }
}
