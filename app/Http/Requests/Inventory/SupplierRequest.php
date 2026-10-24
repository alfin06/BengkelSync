<?php

namespace App\Http\Requests\Inventory;

use App\Models\Supplier;
use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Adding (no route model) and editing a supplier.
 */
class SupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        $supplier = $this->route('supplier');

        return $supplier instanceof Supplier
            ? ($this->user()?->can('update', $supplier) ?? false)
            : ($this->user()?->can('create', Supplier::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'whatsapp_same_as_phone' => ['boolean'],
            'whatsapp' => ['nullable', 'exclude_if:whatsapp_same_as_phone,true', 'string', 'max:20', new IndonesianPhone],
            'sales_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9.\-\s]+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
