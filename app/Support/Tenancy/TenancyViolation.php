<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Thrown when code tries to read or write data outside the current tenant or branch.
 *
 * This is a programming error, not a user error, so it is never caught and shown as a
 * friendly message: it should fail loudly in tests and in error tracking.
 */
final class TenancyViolation extends LogicException
{
    public static function missingTenant(?string $model = null): self
    {
        return new self($model === null
            ? 'No tenant is set in the TenantContext.'
            : "Querying [{$model}] requires a tenant in the TenantContext.");
    }

    public static function missingBranch(?string $model = null): self
    {
        return new self($model === null
            ? 'No active branch is set in the TenantContext.'
            : "Querying [{$model}] requires an active branch in the TenantContext.");
    }

    public static function crossTenantWrite(Model $model): self
    {
        return new self('Cannot write ['.$model::class.'] that belongs to another tenant.');
    }

    public static function crossBranchWrite(Model $model): self
    {
        return new self('Cannot write ['.$model::class.'] that belongs to another branch.');
    }

    public static function ownerChange(Model $model, string $column): self
    {
        return new self("The [{$column}] of [".$model::class.'] cannot be changed.');
    }

    public static function branchOutsideTenant(): self
    {
        return new self('The branch does not belong to the tenant.');
    }
}
