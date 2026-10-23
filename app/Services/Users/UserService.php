<?php

namespace App\Services\Users;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Branch;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * All writes to users go through here, so the owner safeguards (SPEC section 2: at least
 * one active owner must exist) cannot be bypassed.
 */
class UserService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, email: string, password: string, phone?: string|null, locale?: string|null, all_branches?: bool}  $attributes
     * @param  list<int>  $branchIds
     */
    public function create(array $attributes, RoleName|string $role, array $branchIds = []): User
    {
        return DB::transaction(function () use ($attributes, $role, $branchIds): User {
            $attributes['phone'] = Phone::normalize($attributes['phone'] ?? null);

            $user = User::query()->create($attributes);
            $user->branches()->sync($this->tenantBranchIds($branchIds));
            $user->assignRole($role instanceof RoleName ? $role->value : $role);

            return $user;
        });
    }

    /**
     * @param  array{name: string, phone?: string|null, locale?: string|null}  $attributes
     */
    public function updateProfile(User $user, array $attributes): User
    {
        $attributes['phone'] = Phone::normalize($attributes['phone'] ?? null);

        $user->fill($attributes)->save();

        return $user;
    }

    public function changePassword(User $user, string $password): void
    {
        DB::transaction(function () use ($user, $password): void {
            $user->forceFill(['password' => $password])->save();
            $this->audit->record('password_changed', $user);
        });
    }

    public function changeRole(User $user, RoleName|string $role): void
    {
        $roleName = $role instanceof RoleName ? $role->value : $role;

        if ($roleName !== RoleName::Owner->value) {
            $this->ensureAnotherActiveOwner($user);
        }

        DB::transaction(function () use ($user, $roleName): void {
            $old = $user->getRoleNames()->all();
            $user->syncRoles([$roleName]);
            $this->audit->record('role_changed', $user, ['roles' => $old], ['roles' => [$roleName]]);
        });
    }

    public function deactivate(User $user): void
    {
        $this->ensureAnotherActiveOwner($user);

        DB::transaction(function () use ($user): void {
            $user->forceFill(['is_active' => false])->save();
            $this->endSessions($user);
        });
    }

    public function delete(User $user): void
    {
        $this->ensureAnotherActiveOwner($user);

        DB::transaction(function () use ($user): void {
            $user->delete();
            $this->endSessions($user);
        });
    }

    /**
     * Throws when $user is an active owner and no other active owner would remain.
     */
    public function ensureAnotherActiveOwner(User $user): void
    {
        if (! $user->is_active || ! $user->isOwner()) {
            return;
        }

        $otherOwnerExists = User::query()
            ->role(RoleName::Owner->value)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();

        if (! $otherOwnerExists) {
            throw new BusinessRuleViolation(__('users.last_owner'));
        }
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<int>
     */
    private function tenantBranchIds(array $branchIds): array
    {
        if ($branchIds === []) {
            return [];
        }

        // The Branch tenant scope drops ids of other tenants, so they can never be attached.
        $valid = Branch::query()->whereKey($branchIds)->pluck('id')->all();

        if (count($valid) !== count(array_unique($branchIds))) {
            throw new BusinessRuleViolation(__('users.invalid_branch'));
        }

        return array_map('intval', $valid);
    }

    private function endSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->getKey())->delete();
    }
}
