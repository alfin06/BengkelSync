<?php

namespace App\Http\Requests\Purchasing;

use App\Models\Purchase;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Revising a saved purchase: always with a reason (audit log).
 */
class UpdatePurchaseRequest extends PurchaseRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->purchase()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$this->purchase()->purchase_date->toDateString()],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function purchase(): Purchase
    {
        /** @var Purchase $purchase */
        $purchase = $this->route('purchase');

        return $purchase;
    }
}
