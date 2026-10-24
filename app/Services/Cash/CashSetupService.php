<?php

namespace App\Services\Cash;

use App\Enums\CashAccountType;
use App\Enums\PaymentMethodType;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Branch settings for money: cash accounts and payment methods, and the defaults every
 * new branch starts with.
 */
class CashSetupService
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * "Laci Kasir", "Kas Kecil" and the payment method "Tunai" into the drawer (decision of
     * the cash plan). Names are written in the tenant's language at creation time.
     */
    public function provisionDefaults(Branch $branch): void
    {
        $this->context->run($branch->tenant()->firstOrFail(), $branch, function (): void {
            $drawer = CashAccount::query()->create([
                'name' => __('cash.defaults.cash_drawer'),
                'type' => CashAccountType::CashDrawer,
                'sort' => 1,
            ]);

            CashAccount::query()->create([
                'name' => __('cash.defaults.petty_cash'),
                'type' => CashAccountType::PettyCash,
                'sort' => 2,
            ]);

            PaymentMethod::query()->create([
                'name' => __('cash.defaults.cash_method'),
                'type' => PaymentMethodType::Cash,
                'cash_account_id' => $drawer->id,
                'sort' => 1,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function createAccount(array $data): CashAccount
    {
        return DB::transaction(fn () => CashAccount::query()->create($this->accountData($data)));
    }

    /**
     * The type cannot change once money moved through the account: the balance rules and
     * who may see it depend on the type.
     *
     * @param  array<string, mixed>  $data  validated input
     */
    public function updateAccount(CashAccount $account, array $data): CashAccount
    {
        $prepared = $this->accountData($data);

        if ($prepared['type'] !== $account->type && $account->movements()->exists()) {
            throw new BusinessRuleViolation(__('cash.type_locked'));
        }

        if (! $prepared['is_active'] && PaymentMethod::query()->where('cash_account_id', $account->id)->where('is_active', true)->exists()) {
            throw new BusinessRuleViolation(__('cash.account_in_use'));
        }

        DB::transaction(fn () => $account->fill($prepared)->save());

        return $account;
    }

    /**
     * Accounts with history or payment methods are deactivated instead.
     */
    public function deleteAccount(CashAccount $account): void
    {
        if ($account->movements()->exists() || PaymentMethod::query()->where('cash_account_id', $account->id)->exists()) {
            throw new BusinessRuleViolation(__('cash.account_delete_blocked'));
        }

        DB::transaction(fn () => $account->delete());
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function createMethod(array $data): PaymentMethod
    {
        return DB::transaction(fn () => PaymentMethod::query()->create($this->methodData($data)));
    }

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function updateMethod(PaymentMethod $method, array $data): PaymentMethod
    {
        DB::transaction(fn () => $method->fill($this->methodData($data))->save());

        return $method;
    }

    /**
     * Customer payments (next module) will reference methods; until then nothing uses them.
     */
    public function deleteMethod(PaymentMethod $method): void
    {
        DB::transaction(fn () => $method->delete());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function accountData(array $data): array
    {
        return [
            'name' => trim((string) $data['name']),
            'type' => CashAccountType::from((string) $data['type']),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort' => (int) ($data['sort'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function methodData(array $data): array
    {
        $account = CashAccount::query()->findOrFail((int) $data['cash_account_id']);

        if (! $account->is_active) {
            throw new BusinessRuleViolation(__('cash.account_inactive', ['account' => $account->name]));
        }

        // Validate the rate the same way money code will use it.
        $surcharge = (string) ($data['surcharge_percent'] ?? '0');
        Money::basisPoints($surcharge);

        return [
            'name' => trim((string) $data['name']),
            'type' => PaymentMethodType::from((string) $data['type']),
            'cash_account_id' => $account->id,
            'surcharge_percent' => $surcharge,
            'needs_reference' => (bool) ($data['needs_reference'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'sort' => (int) ($data['sort'] ?? 0),
        ];
    }
}
