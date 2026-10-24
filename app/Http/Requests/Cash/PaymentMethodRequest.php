<?php

namespace App\Http\Requests\Cash;

use App\Enums\PaymentMethodType;
use App\Models\CashAccount;
use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding (no route model) and editing a payment method of the active branch.
 */
class PaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        $method = $this->route('payment_method');

        return $method instanceof PaymentMethod
            ? ($this->user()?->can('update', $method) ?? false)
            : ($this->user()?->can('create', PaymentMethod::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(PaymentMethodType::class)],
            'cash_account_id' => ['required', 'integer'],
            // Percent with at most 2 decimals, 0–100 (Money::basisPoints rules).
            'surcharge_percent' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'needs_reference' => ['boolean'],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * The account must belong to the active branch (accounts of other branches are not
     * found), and names are unique within the branch.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('cash_account_id') && CashAccount::query()->find($this->integer('cash_account_id')) === null) {
                $validator->errors()->add('cash_account_id', __('validation.exists', ['attribute' => __('validation.attributes.cash_account_id')]));
            }

            $method = $this->route('payment_method');
            $taken = PaymentMethod::query()
                ->where('name', trim($this->string('name')->toString()))
                ->when($method instanceof PaymentMethod, fn ($query) => $query->whereKeyNot($method->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('validation.attributes.name')]));
            }
        }];
    }
}
