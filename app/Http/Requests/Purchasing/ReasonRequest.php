<?php

namespace App\Http\Requests\Purchasing;

use App\Models\Purchase;
use App\Models\SupplierPayment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Voiding a purchase or a supplier payment: always with a reason.
 */
class ReasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('supplier_payment') ?? $this->route('purchase');

        return ($subject instanceof Purchase || $subject instanceof SupplierPayment)
            && ($this->user()?->can('void', $subject) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }
}
