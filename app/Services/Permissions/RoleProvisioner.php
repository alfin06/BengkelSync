<?php

namespace App\Services\Permissions;

use App\Enums\Permission;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\Tenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * Copies the default roles (SPEC section 2) into a tenant.
 */
class RoleProvisioner
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function provision(Tenant $tenant): void
    {
        foreach (RoleName::cases() as $roleName) {
            $role = Role::query()->firstOrCreate([
                'tenant_id' => $tenant->getKey(),
                'name' => $roleName->value,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(array_map(
                fn (Permission $permission): string => $permission->value,
                $roleName->defaultPermissions(),
            ));
        }

        $this->registrar->forgetCachedPermissions();
    }
}
