<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CashAccount;
use App\Models\User;

/**
 * Who sees which balances (cash plan, decision 2): physical cash with cash.view; bank and
 * other accounts also need dashboard.view_financials. Managing accounts needs
 * branch.update (decision 3).
 */
class CashAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CashView->value);
    }

    public function view(User $user, CashAccount $account): bool
    {
        return $user->can(Permission::CashView->value)
            && ($account->type->isPhysical() || $this->viewFinancial($user));
    }

    public function viewFinancial(User $user): bool
    {
        return $user->can(Permission::DashboardViewFinancials->value);
    }

    public function transfer(User $user, CashAccount $account): bool
    {
        return $user->can(Permission::CashTransfer->value) && $this->view($user, $account);
    }

    public function adjust(User $user, CashAccount $account): bool
    {
        return $user->can(Permission::CashAdjust->value) && $this->view($user, $account);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }

    public function update(User $user, CashAccount $account): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }

    public function delete(User $user, CashAccount $account): bool
    {
        return $user->can(Permission::BranchUpdate->value);
    }
}
