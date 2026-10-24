<?php

namespace App\Http\Requests\Users;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Editing a staff account: details, role, branches, and whether it may log in.
 */
class UpdateUserRequest extends StaffUserRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'is_active' => ['required', 'boolean'],
        ];
    }
}
