<?php

namespace App\Http\Requests\Purchasing;

use App\Models\CashAccount;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Purchasing\PaymentAccountRules;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Paying (part of) a supplier bill later, from the supplier-debt list.
 */
class PaySupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pay', $this->purchase()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'cash_account_id' => ['required', 'integer'],
            'paid_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$this->purchase()->purchase_date->toDateString(), 'before_or_equal:'.app(TenantContext::class)->today()],
            'reference' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('cash_account_id')) {
                return;
            }

            /** @var User $user */
            $user = $this->user();
            $account = $this->account();

            if ($account === null || ! app(PaymentAccountRules::class)->accountsFor($user, onReceipt: false)->contains('id', $account->id)) {
                $validator->errors()->add('cash_account_id', __('purchases.pay_account_not_allowed'));
            }
        }];
    }

    public function purchase(): Purchase
    {
        /** @var Purchase $purchase */
        $purchase = $this->route('purchase');

        return $purchase;
    }

    public function account(): ?CashAccount
    {
        return CashAccount::query()->find($this->integer('cash_account_id'));
    }
}
