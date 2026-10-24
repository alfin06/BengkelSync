<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PaymentMethod;
use App\Models\User;

/**
 * Payment methods are branch settings (cash plan, decision 3).
 */
class PaymentMethodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }

    public function update(User $user, PaymentMethod $method): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }

    public function delete(User $user, PaymentMethod $method): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }
}
