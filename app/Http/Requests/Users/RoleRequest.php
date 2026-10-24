<?php

namespace App\Http\Requests\Users;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A role and its permissions (the permission matrix). Adding (no route role) or editing.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? ($this->user()?->can('update', $role) ?? false)
            : ($this->user()?->can('create', Role::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [$this->route('role') instanceof Role ? 'nullable' : 'required', 'string', 'max:50'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in(Permission::values())],
        ];
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return array_values(array_map('strval', (array) $this->input('permissions', [])));
    }
}
