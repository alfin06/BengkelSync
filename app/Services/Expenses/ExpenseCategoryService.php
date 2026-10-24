<?php

namespace App\Services\Expenses;

use App\Enums\Permission;
use App\Exceptions\BusinessRuleViolation;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * The shop's expense categories (shared by its branches). Restricted categories can only
 * be created or changed by users who may see them.
 */
class ExpenseCategoryService
{
    /** Defaults for a new shop (pilot decision); "Gaji & bonus" is restricted to the Owner. */
    private const DEFAULTS = [
        ['expenses.defaults.transport', false],
        ['expenses.defaults.food', false],
        ['expenses.defaults.supplies', false],
        ['expenses.defaults.utilities', false],
        ['expenses.defaults.tools', false],
        ['expenses.defaults.salary', true],
        ['expenses.defaults.other', false],
    ];

    public function __construct(private readonly TenantContext $context) {}

    public function provisionDefaults(Tenant $tenant): void
    {
        $this->context->run($tenant, null, function (): void {
            foreach (self::DEFAULTS as $sort => [$key, $restricted]) {
                ExpenseCategory::query()->create(['name' => __($key), 'is_restricted' => $restricted, 'sort' => $sort + 1]);
            }
        });
    }

    /**
     * @param  array{name: string, is_restricted?: bool, is_active?: bool}  $data
     */
    public function create(User $user, array $data): ExpenseCategory
    {
        $restricted = (bool) ($data['is_restricted'] ?? false);
        $this->ensureMayRestrict($user, $restricted);

        return DB::transaction(fn () => ExpenseCategory::query()->create([
            'name' => $this->uniqueName($data['name']),
            'is_restricted' => $restricted,
            'sort' => (int) ExpenseCategory::query()->max('sort') + 1,
        ]));
    }

    /**
     * @param  array{name: string, is_restricted?: bool, is_active?: bool}  $data
     */
    public function update(User $user, ExpenseCategory $category, array $data): ExpenseCategory
    {
        $restricted = (bool) ($data['is_restricted'] ?? $category->is_restricted);
        $this->ensureMayRestrict($user, $restricted || $category->is_restricted);

        DB::transaction(fn () => $category->fill([
            'name' => $this->uniqueName($data['name'], $category),
            'is_restricted' => $restricted,
            'is_active' => (bool) ($data['is_active'] ?? $category->is_active),
        ])->save());

        return $category;
    }

    private function ensureMayRestrict(User $user, bool $restricted): void
    {
        if ($restricted && ! $user->can(Permission::ExpenseViewRestricted->value)) {
            throw new BusinessRuleViolation(__('expenses.restricted_not_allowed'));
        }
    }

    private function uniqueName(string $name, ?ExpenseCategory $except = null): string
    {
        $name = trim($name);

        $taken = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($except, fn ($query, ExpenseCategory $category) => $query->whereKeyNot($category->id))
            ->exists();

        if ($taken) {
            throw new BusinessRuleViolation(__('expenses.category_taken'));
        }

        return $name;
    }
}
