<?php

namespace App\Services\Purchasing;

use App\Enums\Permission;
use App\Models\CashAccount;
use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Which cash accounts a user may pay suppliers from, and the on-the-spot rule for Front Desk
 * (pilot decision): with supplier_payment.on_receipt but not supplier_payment.create, a
 * purchase can only be paid in full when it is received, from the cash drawer or petty
 * cash, up to the branch's cash purchase limit.
 */
class PaymentAccountRules
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * @return Collection<int, CashAccount>
     */
    public function accountsFor(User $user, bool $onReceipt): Collection
    {
        $fullPayer = $user->can(Permission::SupplierPaymentCreate->value);
        $receiptPayer = $onReceipt && $user->can(Permission::SupplierPaymentOnReceipt->value);

        if (! $fullPayer && ! $receiptPayer) {
            return new Collection;
        }

        return CashAccount::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->filter(fn (CashAccount $account) => $user->can('view', $account)
                && ($fullPayer || $account->type->isPhysical()))
            ->values();
    }

    /**
     * Why a pay-on-receipt is not allowed for this user, or null when it is.
     */
    public function onReceiptViolation(User $user, CashAccount $account, int $amount, int $total): ?string
    {
        if ($user->can(Permission::SupplierPaymentCreate->value)) {
            return null;
        }

        if (! $user->can(Permission::SupplierPaymentOnReceipt->value)) {
            return __('purchases.pay_not_allowed');
        }

        if (! $account->type->isPhysical()) {
            return __('purchases.pay_cash_only');
        }

        if ($amount !== $total) {
            return __('purchases.pay_full_only');
        }

        $limit = $this->context->requireBranch()->cash_purchase_limit;
        if ($limit !== null && $total > $limit) {
            return __('purchases.pay_over_limit', ['limit' => Money::format($limit)]);
        }

        return null;
    }
}
