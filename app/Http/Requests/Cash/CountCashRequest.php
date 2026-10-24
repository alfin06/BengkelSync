<?php

namespace App\Http\Requests\Cash;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cash count: the amount actually counted, with a reason.
 */
class CountCashRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('adjust', $this->route('cash_account')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counted_amount' => ['required', 'integer', 'min:-999999999999', 'max:999999999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
