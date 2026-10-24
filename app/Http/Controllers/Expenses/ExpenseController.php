<?php

namespace App\Http\Controllers\Expenses;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expenses\ExpenseRequest;
use App\Http\Requests\Expenses\VoidExpenseRequest;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Expenses\ExpenseService;
use App\Support\BranchTime;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request, TenantContext $context): Response
    {
        Gate::authorize('viewAny', Expense::class);

        /** @var User $user */
        $user = $request->user();
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'category_id' => ['nullable', 'integer'],
            'cash_account_id' => ['nullable', 'integer'],
        ]);
        $filters['month'] ??= substr($context->today(), 0, 7);
        $filters['category_id'] = isset($filters['category_id']) ? (int) $filters['category_id'] : null;
        $filters['cash_account_id'] = isset($filters['cash_account_id']) ? (int) $filters['cash_account_id'] : null;

        $query = $this->expenses->search($user, $filters);
        $total = (int) $query->clone()->whereNull('voided_at')->sum('amount');

        return Inertia::render('Expenses/Index', [
            'expenses' => $query->with(['category', 'account', 'creator'])
                ->paginate(30)
                ->withQueryString()
                ->through(fn (Expense $expense) => $this->present($user, $expense)),
            'filters' => $filters,
            'totalLabel' => Money::format($total),
            'today' => $context->today(),
            'categories' => ExpenseCategory::query()->visibleTo($user)->orderBy('sort')->orderBy('name')->get()
                ->map(fn (ExpenseCategory $category) => ['id' => $category->id, 'name' => $category->name, 'is_active' => $category->is_active, 'is_restricted' => $category->is_restricted])->all(),
            'accounts' => CashAccount::query()->orderBy('sort')->orderBy('name')->get()
                ->filter(fn (CashAccount $account) => $user->can('view', $account))
                ->map(fn (CashAccount $account) => ['id' => $account->id, 'name' => $account->name])->values()->all(),
            // Where this user may pay from (cash only for Front Desk), and the cash limit.
            'payAccounts' => $this->expenses->accountsFor($user)->map(fn (CashAccount $account) => ['value' => $account->id, 'label' => $account->name])->all(),
            'cashLimit' => $user->can(Permission::ExpenseCreate->value) ? null : $context->requireBranch()->cash_purchase_limit,
            'can' => [
                'create' => $user->can('create', Expense::class),
                'manageCategories' => $user->can('manageCategories', Expense::class),
                'restricted' => $user->can(Permission::ExpenseViewRestricted->value),
            ],
        ]);
    }

    public function store(ExpenseRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $expense = $this->expenses->create($user, $request->expenseData(), $request->file('receipt'));

        return back()->with('success', __('expenses.saved', ['amount' => Money::format($expense->amount)]));
    }

    public function update(ExpenseRequest $request, Expense $expense): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->expenses->update($user, $expense, $request->expenseData(), $request->file('receipt'), $request->boolean('remove_receipt'));

        return back()->with('success', __('expenses.updated'));
    }

    public function void(VoidExpenseRequest $request, Expense $expense): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->expenses->void($user, $expense, $request->string('reason')->toString());

        return back()->with('success', __('expenses.voided'));
    }

    /**
     * The receipt photo from the private disk, never cached (SPEC section 8.2).
     */
    public function receipt(Expense $expense): StreamedResponse
    {
        Gate::authorize('view', $expense);

        abort_if($expense->attachment_path === null || ! $this->expenses->disk()->exists($expense->attachment_path), 404);

        return $this->expenses->disk()->response($expense->attachment_path, 'nota-'.$expense->id, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(User $user, Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_date' => $expense->expense_date->toDateString(),
            'category_id' => $expense->category_id,
            'category_name' => $expense->category->name,
            'is_restricted' => $expense->category->is_restricted,
            'description' => $expense->description,
            'amount_label' => Money::format($expense->amount),
            'account_name' => $expense->account->name,
            'created_by' => $expense->creator?->name,
            'has_receipt' => $expense->attachment_path !== null,
            'voided' => $expense->isVoided(),
            'void_reason' => $expense->void_reason,
            'voided_at_label' => BranchTime::label($expense->voided_at),
            'can' => ['update' => $user->can('update', $expense), 'void' => $user->can('void', $expense)],
        ];
    }
}
