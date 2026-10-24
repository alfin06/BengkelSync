<?php

namespace App\Services\Expenses;

use App\Enums\CashMovementType;
use App\Enums\Permission;
use App\Exceptions\BusinessRuleViolation;
use App\Models\CashAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Cash\CashService;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Expenses (SPEC section 4): the money leaves its cash account through CashService in the
 * same transaction. Staff with only expense.create_cash (Front Desk, pilot decision) may
 * pay only from cash, up to the branch's cash limit. Restricted categories (salaries) are
 * only for users with expense.view_restricted.
 */
class ExpenseService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly CashService $cash,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Expenses of the active branch the user may see (restricted ones only with permission).
     *
     * @param  array{month?: string|null, category_id?: int|null, cash_account_id?: int|null}  $filters
     * @return Builder<Expense>
     */
    public function search(User $user, array $filters): Builder
    {
        $month = $filters['month'] ?? null;

        return Expense::query()
            ->whereHas('category', fn (Builder $category) => $category->visibleTo($user))
            ->when($month, fn (Builder $query, string $month) => $query->whereBetween('expense_date', [$month.'-01', date('Y-m-t', (int) strtotime($month.'-01'))]))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($filters['cash_account_id'] ?? null, fn (Builder $query, int $id) => $query->where('cash_account_id', $id))
            ->latest('expense_date')
            ->latest('id');
    }

    /**
     * Accounts this user may pay expenses from: all active ones they can see, or only the
     * cash drawer and petty cash for staff with expense.create_cash only.
     *
     * @return Collection<int, CashAccount>
     */
    public function accountsFor(User $user): Collection
    {
        $fullPayer = $user->can(Permission::ExpenseCreate->value);

        return CashAccount::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->filter(fn (CashAccount $account) => $user->can('view', $account) && ($fullPayer || $account->type->isPhysical()))
            ->values();
    }

    /**
     * @param  array{expense_date: string, category_id: int, description: string, amount: int, cash_account_id: int}  $data
     */
    public function create(User $user, array $data, ?UploadedFile $receipt = null): Expense
    {
        $category = $this->usableCategory($user, $data['category_id']);
        $account = $this->usableAccount($user, $data['cash_account_id'], $data['amount']);
        $path = $receipt === null ? null : $this->storeReceipt($receipt);

        try {
            return DB::transaction(function () use ($user, $data, $category, $account, $path): Expense {
                $expense = new Expense([
                    'expense_date' => $data['expense_date'],
                    'category_id' => $category->id,
                    'description' => trim($data['description']),
                ]);
                $expense->forceFill([
                    'amount' => $data['amount'],
                    'cash_account_id' => $account->id,
                    'attachment_path' => $path,
                    'created_by' => $user->id,
                ])->save();

                $this->cash->record($account, CashMovementType::Expense, -$expense->amount, $expense, $this->cashNote($expense, $category));

                return $expense;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                $this->disk()->delete($path);
            }

            throw $exception;
        }
    }

    /**
     * Date, category, description and receipt photo. A wrong amount or account is
     * corrected by voiding and recording again, so the cash ledger stays simple.
     *
     * @param  array{expense_date: string, category_id: int, description: string}  $data
     */
    public function update(User $user, Expense $expense, array $data, ?UploadedFile $receipt = null, bool $removeReceipt = false): Expense
    {
        if ($expense->isVoided()) {
            throw new BusinessRuleViolation(__('expenses.is_voided'));
        }

        $category = $this->usableCategory($user, $data['category_id'], $expense->category_id);
        $oldPath = $expense->attachment_path;
        $newPath = $receipt === null ? null : $this->storeReceipt($receipt);

        try {
            DB::transaction(function () use ($expense, $data, $category, $oldPath, $newPath, $removeReceipt): void {
                $expense->fill([
                    'expense_date' => $data['expense_date'],
                    'category_id' => $category->id,
                    'description' => trim($data['description']),
                ]);

                if ($newPath !== null || $removeReceipt) {
                    $expense->attachment_path = $newPath;
                }

                $expense->save();

                if ($oldPath !== null && $oldPath !== $expense->attachment_path) {
                    DB::afterCommit(fn () => $this->disk()->delete($oldPath));
                }
            });
        } catch (Throwable $exception) {
            if ($newPath !== null) {
                $this->disk()->delete($newPath);
            }

            throw $exception;
        }

        return $expense;
    }

    /**
     * Cancel a wrong expense: the money goes back into its account; the row is kept.
     */
    public function void(User $user, Expense $expense, string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        DB::transaction(function () use ($user, $expense, $reason): void {
            $locked = Expense::query()->with(['category', 'account'])->whereKey($expense->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new BusinessRuleViolation(__('expenses.is_voided'));
            }

            $locked->forceFill(['voided_at' => now(), 'void_reason' => trim($reason), 'voided_by' => $user->id])->save();
            $this->cash->record($locked->account, CashMovementType::Expense, $locked->amount, $locked, __('expenses.void_cash_note', ['note' => $this->cashNote($locked, $locked->category)]));
            $this->audit->record('expense_voided', $locked, ['amount' => $locked->amount], [], trim($reason));
        });

        $expense->refresh();
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.private_disk'));
    }

    private function usableCategory(User $user, int $categoryId, ?int $currentId = null): ExpenseCategory
    {
        $category = ExpenseCategory::query()->visibleTo($user)->find($categoryId);

        if ($category === null || (! $category->is_active && $category->id !== $currentId)) {
            throw new BusinessRuleViolation(__('expenses.category_unavailable'));
        }

        return $category;
    }

    private function usableAccount(User $user, int $accountId, int $amount): CashAccount
    {
        if ($amount <= 0) {
            throw new BusinessRuleViolation(__('cash.zero_amount'));
        }

        $account = $this->accountsFor($user)->firstWhere('id', $accountId);
        if ($account === null) {
            throw new BusinessRuleViolation(__('expenses.account_not_allowed'));
        }

        // Cash-only staff: up to the branch limit (shared with on-the-spot purchases).
        if (! $user->can(Permission::ExpenseCreate->value)) {
            $limit = $this->context->requireBranch()->cash_purchase_limit;
            if ($limit !== null && $amount > $limit) {
                throw new BusinessRuleViolation(__('expenses.over_limit', ['limit' => Money::format($limit)]));
            }
        }

        return $account;
    }

    /**
     * The cash history shows the description, except for restricted categories (salaries).
     */
    private function cashNote(Expense $expense, ExpenseCategory $category): string
    {
        return $category->is_restricted ? __('expenses.restricted_cash_note') : $category->name.': '.$expense->description;
    }

    private function storeReceipt(UploadedFile $file): string
    {
        // Random file name: nothing about the purchase in the path.
        $path = $this->disk()->putFileAs(
            'tenants/'.$this->context->requireTenant()->getKey().'/expenses',
            $file,
            Str::uuid()->toString().'.'.$file->guessExtension(),
        );

        if ($path === false) {
            throw new BusinessRuleViolation(__('expenses.upload_failed'));
        }

        return $path;
    }
}
