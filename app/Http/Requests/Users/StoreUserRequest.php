<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

/**
 * Adding a staff account with a password the owner gives them (SPEC section 9: no
 * invitations in the MVP).
 */
class StoreUserRequest extends StaffUserRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', User::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'password' => ['required', 'string', 'max:100', Password::defaults()],
        ];
    }
}
