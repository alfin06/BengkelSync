<?php

namespace App\Http\Requests\Payments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling a payment line or a whole kwitansi, always with a reason.
 */
class VoidPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('payment') ?? $this->route('receipt');

        return $this->user()?->can('void', $subject) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}
