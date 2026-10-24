<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

/**
 * Tenant and branch isolation come from the Expense global scopes. Expenses in a
 * restricted category (salaries) also need expense.view_restricted, for everything.
 */
class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ExpenseView->value);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can(Permission::ExpenseView->value) && $this->maySeeCategory($user, $expense);
    }

    /**
     * Any amount from any account (expense.create), or small ones from cash (expense.create_cash).
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::ExpenseCreate->value) || $user->can(Permission::ExpenseCreateCash->value);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can(Permission::ExpenseUpdate->value) && ! $expense->isVoided() && $this->maySeeCategory($user, $expense);
    }

    public function void(User $user, Expense $expense): bool
    {
        return $user->can(Permission::ExpenseDelete->value) && ! $expense->isVoided() && $this->maySeeCategory($user, $expense);
    }

    public function manageCategories(User $user): bool
    {
        return $user->can(Permission::ExpenseManageCategories->value);
    }

    private function maySeeCategory(User $user, Expense $expense): bool
    {
        return ! $expense->category->is_restricted || $user->can(Permission::ExpenseViewRestricted->value);
    }
}
