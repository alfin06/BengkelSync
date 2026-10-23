<?php

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * @implements Scope<Model>
 */
final class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $branchId = app(TenantContext::class)->branchId();

        if ($branchId === null) {
            throw TenancyViolation::missingBranch($model::class);
        }

        $builder->where($model->qualifyColumn('branch_id'), $branchId);
    }
}
