<?php

namespace App\Http\Requests\Invoicing;

use App\Enums\Permission;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Branch invoice settings (SPEC section 5): whether invoices carry tax, its name, rate
 * and mode, and the footer text. Decided by the branch manager (branch.update).
 */
class InvoiceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::BranchUpdate->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'tax_enabled' => ['required', 'boolean'],
            'tax_name' => ['required', 'string', 'max:20'],
            'tax_rate' => ['required', 'string', 'max:6'],
            'tax_inclusive' => ['required', 'boolean'],
            'invoice_footer' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('tax_rate')) {
                return;
            }

            try {
                $this->basisPoints();
            } catch (InvalidArgumentException) {
                $validator->errors()->add('tax_rate', __('invoices.settings.rate_invalid'));
            }
        }];
    }

    /**
     * "11", "11,5" or "11.50" → "11.50" (no floats).
     */
    public function taxRate(): string
    {
        $basisPoints = $this->basisPoints();

        return sprintf('%d.%02d', intdiv($basisPoints, 100), $basisPoints % 100);
    }

    private function basisPoints(): int
    {
        return Money::basisPoints(str_replace(',', '.', $this->string('tax_rate')->toString()));
    }
}
