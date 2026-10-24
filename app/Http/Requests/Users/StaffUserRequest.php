<?php

namespace App\Http\Requests\Users;

use App\Models\Role;
use App\Models\User;
use App\Rules\IndonesianPhone;
use App\Services\Users\RoleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared rules for adding and editing a staff account. Besides the fields, it checks that
 * the person doing it may give this role and these branches: never more than they have.
 */
abstract class StaffUserRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // One email = one account in the whole system (SPEC section 11).
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editedUser()?->id)],
            'phone' => ['nullable', 'string', 'max:20', new IndonesianPhone],
            'locale' => ['nullable', Rule::in(['id', 'en'])],
            'role_id' => ['required', 'integer'],
            'all_branches' => ['boolean'],
            'branch_ids' => ['array', 'max:50'],
            'branch_ids.*' => ['integer', 'distinct'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var User $actor */
            $actor = $this->user();

            $role = $this->role();
            if ($role === null) {
                $validator->errors()->add('role_id', __('users_admin.role_invalid'));
            } elseif (! app(RoleService::class)->canAssign($actor, $role->load('permissions'))) {
                $validator->errors()->add('role_id', __('users_admin.role_not_allowed'));
            }

            if ($this->boolean('all_branches')) {
                if (! $actor->all_branches && ! $actor->isOwner()) {
                    $validator->errors()->add('all_branches', __('users_admin.all_branches_not_allowed'));
                }

                return;
            }

            $ids = $this->branchIds();
            if ($ids === []) {
                $validator->errors()->add('branch_ids', __('users_admin.branch_required'));

                return;
            }

            // Only branches of this shop the actor works in.
            $allowed = $actor->accessibleBranches()->whereKey($ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
            if (count($allowed) !== count($ids)) {
                $validator->errors()->add('branch_ids', __('users_admin.branch_not_allowed'));
            }
        }];
    }

    public function role(): ?Role
    {
        return Role::query()->forTenant(app(TenantContext::class)->requireTenant())->find($this->integer('role_id'));
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('branch_ids', []))));
    }

    /**
     * @return array{name: string, email: string, phone: string|null, locale: string|null}
     */
    public function details(): array
    {
        return [
            'name' => trim($this->string('name')->toString()),
            'email' => mb_strtolower(trim($this->string('email')->toString())),
            'phone' => $this->input('phone'),
            'locale' => $this->input('locale'),
        ];
    }

    protected function editedUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
