<?php

namespace App\Services\Permissions;

use App\Enums\Permission as PermissionName;
use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the permissions table match the Permission enum. Safe to run on every deploy.
 */
class PermissionSynchronizer
{
    private const GUARD = 'web';

    public function __construct(private readonly PermissionRegistrar $registrar) {}

    public function sync(): void
    {
        DB::transaction(function (): void {
            $existing = Permission::query()->where('guard_name', self::GUARD)->pluck('name')->all();
            $wanted = PermissionName::values();

            foreach (array_diff($wanted, $existing) as $name) {
                Permission::query()->create(['name' => $name, 'guard_name' => self::GUARD]);
            }

            Permission::query()
                ->where('guard_name', self::GUARD)
                ->whereNotIn('name', $wanted)
                ->delete();

            $this->registrar->forgetCachedPermissions();

            // The Owner role always has every permission, including ones added later.
            Role::query()
                ->where('name', RoleName::Owner->value)
                ->each(fn (Role $owner) => $owner->syncPermissions($wanted));
        });

        $this->registrar->forgetCachedPermissions();
    }
}
