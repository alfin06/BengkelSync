<?php

namespace App\Http\Requests\Customers;

use App\Enums\CustomerType;
use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Shared rules for creating and editing a customer.
 */
abstract class CustomerRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CustomerType::class)],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'whatsapp_same_as_phone' => ['boolean'],
            'whatsapp' => ['nullable', 'exclude_if:whatsapp_same_as_phone,true', 'string', 'max:20', new IndonesianPhone],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9.\-\s]+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
