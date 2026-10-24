<?php

namespace App\Http\Requests\Purchasing;

use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchasing\PaymentAccountRules;
use App\Services\Purchasing\PurchaseService;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Validator;

/**
 * Receiving goods, optionally paying right away ("Bayar sekarang").
 */
class StorePurchaseRequest extends PurchaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Purchase::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'supplier_id' => ['required', 'integer'],
            'purchase_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.app(TenantContext::class)->today()],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:purchase_date'],
            'pay_amount' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
            'pay_account_id' => ['nullable', 'required_unless:pay_amount,null,0', 'integer'],
            'pay_reference' => ['nullable', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($this->supplier() === null && ! $validator->errors()->has('supplier_id')) {
                    $validator->errors()->add('supplier_id', __('validation.exists', ['attribute' => __('validation.attributes.supplier_id')]));
                }

                $amount = $this->integer('pay_amount');
                if ($amount <= 0 || $validator->errors()->isNotEmpty()) {
                    return;
                }

                /** @var User $user */
                $user = $this->user();
                $rules = app(PaymentAccountRules::class);
                $account = $this->payAccount();

                if ($account === null || ! $rules->accountsFor($user, onReceipt: true)->contains('id', $account->id)) {
                    $validator->errors()->add('pay_account_id', __('purchases.pay_account_not_allowed'));

                    return;
                }

                $total = PurchaseService::totalOf($this->lines());

                if ($amount > $total) {
                    $validator->errors()->add('pay_amount', __('purchases.overpayment', ['outstanding' => Money::format($total)]));

                    return;
                }

                $violation = $rules->onReceiptViolation($user, $account, $amount, $total);
                if ($violation !== null) {
                    $validator->errors()->add('pay_amount', $violation);
                }
            },
        ];
    }

    public function supplier(): ?Supplier
    {
        return Supplier::query()->find($this->integer('supplier_id'));
    }

    public function payAccount(): ?CashAccount
    {
        return CashAccount::query()->find($this->integer('pay_account_id'));
    }
}
