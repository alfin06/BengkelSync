<?php

namespace App\Http\Requests\Cash;

use App\Models\CashAccount;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The branch's limit for purchases Front Desk may pay on the spot from cash. Empty = no limit.
 */
class CashPurchaseLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Same permission as the rest of the branch payment settings (branch.update).
        return $this->user()?->can('create', CashAccount::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cash_purchase_limit' => ['nullable', 'integer', 'min:0', 'max:999999999999'],
        ];
    }
}
