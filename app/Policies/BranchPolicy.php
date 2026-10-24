<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Branch;
use App\Models\User;

/**
 * Branches: the Owner adds and deletes them; branch managers edit the branches they
 * work in. Other tenants' branches never reach here (tenant-scoped route binding).
 */
class BranchPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::BranchCreate->value);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->can(Permission::BranchUpdate->value) && $user->canAccessBranch($branch);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $user->can(Permission::BranchDelete->value) && $user->canAccessBranch($branch);
    }

    /**
     * A deleted branch is outside everyone's branch list, so restoring it needs access to
     * all branches (the Owner).
     */
    public function restore(User $user, Branch $branch): bool
    {
        return $user->can(Permission::BranchDelete->value) && $user->all_branches;
    }
}
