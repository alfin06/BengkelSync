<?php

namespace App\Services\Users;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * The shop's roles and their permissions (SPEC section 2, permission matrix). The Owner
 * role always has every permission and cannot be changed. Every change is audited and
 * clears spatie's permission cache, so it applies on the next request.
 */
class RoleService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly AuditLogger $audit,
        private readonly PermissionRegistrar $registrar,
    ) {}

    /**
     * Roles of the active tenant, Owner first.
     *
     * @return Collection<int, Role>
     */
    public function roles(): Collection
    {
        return Role::query()
            ->forTenant($this->context->requireTenant())
            ->with('permissions')
            ->orderByRaw('name = ? desc', [RoleName::Owner->value])
            ->orderBy('id')
            ->get();
    }

    /**
     * A system role shows its translated name ("Admin Depan"); a custom role its own.
     */
    public static function label(Role $role): string
    {
        return RoleName::tryFrom($role->name)?->label() ?? $role->name;
    }

    /**
     * Roles the actor may give to someone. Owners may give any role. Others may give only
     * roles whose permissions they have themselves, and never Owner, so nobody can hand
     * out more power than they hold.
     *
     * @return Collection<int, Role>
     */
    public function assignableRoles(User $actor): Collection
    {
        return $this->roles()->filter(fn (Role $role) => $this->canAssign($actor, $role))->values();
    }

    public function canAssign(User $actor, Role $role): bool
    {
        if ($actor->isOwner()) {
            return true;
        }

        if ($role->isOwnerRole()) {
            return false;
        }

        return $role->permissions->every(fn ($permission) => $actor->can($permission->name));
    }

    /**
     * A new role, optionally starting from another role's permissions.
     *
     * @param  list<string>  $permissions
     */
    public function create(string $name, array $permissions): Role
    {
        $name = $this->validName($name);
        $tenant = $this->context->requireTenant();

        return DB::transaction(function () use ($name, $permissions, $tenant): Role {
            /** @var Role $role */
            $role = Role::query()->create(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
            $role->syncPermissions($this->validPermissions($permissions));
            $this->audit->record('role_created', $role, [], ['name' => $name, 'permissions' => $this->validPermissions($permissions)]);
            $this->registrar->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * Save the permission matrix of a role (and its name, for custom roles).
     *
     * @param  list<string>  $permissions
     */
    public function update(Role $role, ?string $name, array $permissions): Role
    {
        $this->ensureEditable($role);
        $permissions = $this->validPermissions($permissions);

        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $old = ['name' => $role->name, 'permissions' => $role->permissions->pluck('name')->sort()->values()->all()];

            if ($name !== null && RoleName::tryFrom($role->name) === null) {
                $role->forceFill(['name' => $this->validName($name, $role)])->save();
            }

            $role->syncPermissions($permissions);
            $sorted = $permissions;
            sort($sorted);
            $this->audit->record('role_updated', $role, $old, ['name' => $role->name, 'permissions' => $sorted]);
            $this->registrar->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * Only custom roles nobody uses can be deleted.
     */
    public function delete(Role $role): void
    {
        if (RoleName::tryFrom($role->name) !== null) {
            throw new BusinessRuleViolation(__('roles_admin.system_role_delete'));
        }

        if ($role->users()->exists()) {
            throw new BusinessRuleViolation(__('roles_admin.role_in_use'));
        }

        DB::transaction(function () use ($role): void {
            $this->audit->record('role_deleted', $role, ['name' => $role->name], []);
            $role->delete();
        });

        $this->registrar->forgetCachedPermissions();
    }

    private function ensureEditable(Role $role): void
    {
        if ($role->isOwnerRole()) {
            throw new BusinessRuleViolation(__('users.owner_role_protected'));
        }

        if ($role->tenant_id !== $this->context->requireTenant()->id) {
            abort(404);
        }
    }

    private function validName(string $name, ?Role $except = null): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new BusinessRuleViolation(__('roles_admin.name_required'));
        }

        // System role keys ("owner", "front_desk", ...) are reserved, and so are their labels.
        $reserved = array_merge(
            array_map(fn (RoleName $role) => mb_strtolower($role->value), RoleName::cases()),
            array_map(fn (RoleName $role) => mb_strtolower($role->label()), RoleName::cases()),
        );
        if (in_array(mb_strtolower($name), $reserved, true)) {
            throw new BusinessRuleViolation(__('roles_admin.name_taken'));
        }

        $taken = Role::query()
            ->forTenant($this->context->requireTenant())
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($except, fn ($query, Role $role) => $query->whereKeyNot($role->id))
            ->exists();
        if ($taken) {
            throw new BusinessRuleViolation(__('roles_admin.name_taken'));
        }

        return $name;
    }

    /**
     * Unknown names are dropped; billing stays with the Owner role.
     *
     * @param  list<string>  $permissions
     * @return list<string>
     */
    private function validPermissions(array $permissions): array
    {
        $allowed = array_diff(Permission::values(), [Permission::BillingManage->value]);

        return array_values(array_intersect($allowed, array_unique($permissions)));
    }
}
