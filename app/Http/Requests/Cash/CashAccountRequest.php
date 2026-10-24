<?php

namespace App\Http\Requests\Cash;

use App\Enums\CashAccountType;
use App\Models\CashAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Adding (no route model) and editing a cash account of the active branch.
 */
class CashAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $account = $this->route('cash_account');

        return $account instanceof CashAccount
            ? ($this->user()?->can('update', $account) ?? false)
            : ($this->user()?->can('create', CashAccount::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::enum(CashAccountType::class)],
            'is_active' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * Names are unique within the branch (the query is branch-scoped).
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $account = $this->route('cash_account');
            $taken = CashAccount::query()
                ->where('name', trim($this->string('name')->toString()))
                ->when($account instanceof CashAccount, fn ($query) => $query->whereKeyNot($account->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('name', __('validation.unique', ['attribute' => __('validation.attributes.name')]));
            }
        }];
    }
}
