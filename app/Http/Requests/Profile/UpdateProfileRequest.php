<?php

namespace App\Http\Requests\Profile;

use App\Rules\IndonesianPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A user may change their own name, phone and language. Email, role and branch access
 * are managed by the owner (user management), never by the user themself.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'locale' => ['required', Rule::in(config('app.supported_locales'))],
        ];
    }
}
