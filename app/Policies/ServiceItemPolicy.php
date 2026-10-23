<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ServiceItem;
use App\Models\User;

class ServiceItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ServiceView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ServiceCreate->value);
    }

    public function update(User $user, ServiceItem $item): bool
    {
        return $user->can(Permission::ServiceUpdate->value);
    }

    public function delete(User $user, ServiceItem $item): bool
    {
        return $user->can(Permission::ServiceDelete->value);
    }
}
