<?php

namespace App\Http\Requests\Settings;

use App\Enums\Permission;
use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The active branch's details on printed documents (branch.update).
 */
class BranchProfileRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'npwp' => ['nullable', 'string', 'max:32', 'regex:/^[0-9.\-\s]+$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'remove_logo' => ['boolean'],
        ];
    }
}
