<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Exceptions\BusinessRuleViolation;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role that belongs to one tenant (spatie teams, team key = tenant_id).
 *
 * This model deliberately does NOT use the BelongsToTenant global scope: spatie loads and
 * caches roles for all tenants internally, and a global scope would corrupt that cache.
 * Spatie filters by the current team itself; application code must use forTenant().
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $name
 */
class Role extends SpatieRole
{
    protected static function booted(): void
    {
        static::deleting(function (Role $role): void {
            if ($role->isOwnerRole()) {
                throw new BusinessRuleViolation(__('users.owner_role_protected'));
            }
        });

        static::updating(function (Role $role): void {
            if ($role->isDirty('name') && $role->getOriginal('name') === RoleName::Owner->value) {
                throw new BusinessRuleViolation(__('users.owner_role_protected'));
            }
        });
    }

    /**
     * @param  Builder<Role>  $query
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): void
    {
        $query->where('tenant_id', $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }

    public function isOwnerRole(): bool
    {
        return $this->name === RoleName::Owner->value;
    }
}
