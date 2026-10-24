<?php

namespace App\Http\Requests\Cash;

use App\Enums\Permission;
use App\Models\CashAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Move money between two accounts of the active branch. Accounts of other branches are not
 * found (CashAccount is branch-scoped).
 */
class TransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $from = $this->from();
        $to = $this->to();

        // Missing accounts are reported by validation; only check rights on real ones.
        return $user !== null
            && $user->can(Permission::CashTransfer->value)
            && ($from === null || $user->can('transfer', $from))
            && ($to === null || $user->can('transfer', $to));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'integer'],
            'to_account_id' => ['required', 'integer', 'different:from_account_id'],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (['from_account_id' => $this->from(), 'to_account_id' => $this->to()] as $field => $account) {
                if (! $validator->errors()->has($field) && $account === null) {
                    $validator->errors()->add($field, __('validation.exists', ['attribute' => __('validation.attributes.'.$field)]));
                }
            }
        }];
    }

    public function from(): ?CashAccount
    {
        return CashAccount::query()->find($this->integer('from_account_id'));
    }

    public function to(): ?CashAccount
    {
        return CashAccount::query()->find($this->integer('to_account_id'));
    }
}
