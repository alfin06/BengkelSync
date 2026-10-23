<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class TenantScope implements Scope
{
    /**
     * @param  bool  $requiresTenant  whether querying without a tenant in the context is an error
     */
    public function __construct(private readonly bool $requiresTenant = true) {}

    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);

        if ($context->hasTenant()) {
            $builder->where($model->qualifyColumn('tenant_id'), $context->tenantId());

            return;
        }

        if ($this->requiresTenant) {
            throw TenancyViolation::missingTenant($model::class);
        }
    }
}
