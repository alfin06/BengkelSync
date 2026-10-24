<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shop's name and the invoice lock period (Owner: settings.update).
 */
class ShopSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::SettingsUpdate->value) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            // Empty: no lock.
            'invoice_lock_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }
}
