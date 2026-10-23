<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy\TenancyViolation;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes a model to the current tenant (CLAUDE.md rule 1).
 *
 * - Every query is filtered by tenant_id. Without a tenant in the context the query
 *   throws, unless the model opts out with requiresTenantContext() (only User, for login).
 * - tenant_id is filled automatically on create.
 * - Writing a row of another tenant, or moving a row to another tenant, throws.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope(static::requiresTenantContext()));

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', $context->requireTenant()->getKey());

                return;
            }

            self::guardSameTenant($model, $context);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('tenant_id')) {
                throw TenancyViolation::ownerChange($model, 'tenant_id');
            }

            self::guardSameTenant($model, app(TenantContext::class));
        });

        static::deleting(function (Model $model): void {
            self::guardSameTenant($model, app(TenantContext::class));
        });
    }

    /**
     * Whether querying this model without a tenant in the context is an error.
     */
    public static function requiresTenantContext(): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    private static function guardSameTenant(Model $model, TenantContext $context): void
    {
        if ($context->hasTenant() && (int) $model->getAttribute('tenant_id') !== $context->tenantId()) {
            throw TenancyViolation::crossTenantWrite($model);
        }
    }
}
