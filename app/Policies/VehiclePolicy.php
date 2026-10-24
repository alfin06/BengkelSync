<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vehicle;

class VehiclePolicy
{
    /**
     * Searching vehicles (the plate lookup at check-in).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::VehicleView->value);
    }

    public function view(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::VehicleView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::VehicleCreate->value);
    }

    public function update(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::VehicleUpdate->value);
    }

    public function delete(User $user, Vehicle $vehicle): bool
    {
        return $user->can(Permission::VehicleDelete->value);
    }

    /**
     * Changing the owner is an edit of the vehicle (decision 6 of the Phase 1 plan).
     */
    public function changeOwner(User $user, Vehicle $vehicle): bool
    {
        return $this->update($user, $vehicle);
    }
}
