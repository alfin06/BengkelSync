<?php

namespace App\Http\Controllers\Expenses;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Expenses\ExpenseCategoryService;
use Illuminate\Http\RedirectResponse;

/**
 * The shop's expense categories (Owner and Finance by default).
 */
class ExpenseCategoryController extends Controller
{
    public function __construct(private readonly ExpenseCategoryService $categories) {}

    public function store(ExpenseCategoryRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $category = $this->categories->create($user, $request->only(['name', 'is_restricted']));

        return back()->with('success', __('expenses.category_saved', ['name' => $category->name]));
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->categories->update($user, $expenseCategory, $request->only(['name', 'is_restricted', 'is_active']));

        return back()->with('success', __('expenses.category_saved', ['name' => $expenseCategory->name]));
    }
}
