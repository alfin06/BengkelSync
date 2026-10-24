<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\Tenancy\BranchScope;
use App\Support\Tenancy\TenancyViolation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes operational data to the active branch (CLAUDE.md rule 2).
 *
 * Use together with BelongsToTenant. Every query is filtered by the active branch
 * from the TenantContext (which comes from the session, never from the request).
 * branch_id is filled on create, and rows of other branches cannot be written.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function (Model $model): void {
            $context = app(TenantContext::class);

            if ($model->getAttribute('branch_id') === null) {
                $model->setAttribute('branch_id', $context->requireBranch()->getKey());

                return;
            }

            self::guardSameBranch($model, $context);
        });

        static::updating(function (Model $model): void {
            if ($model->isDirty('branch_id')) {
                throw TenancyViolation::ownerChange($model, 'branch_id');
            }

            self::guardSameBranch($model, app(TenantContext::class));
        });

        static::deleting(function (Model $model): void {
            self::guardSameBranch($model, app(TenantContext::class));
        });
    }

    /**
     * Includes deleted branches, so history across branches (e.g. a vehicle's services)
     * still shows where the work was done.
     *
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    private static function guardSameBranch(Model $model, TenantContext $context): void
    {
        if ((int) $model->getAttribute('branch_id') !== $context->requireBranch()->getKey()) {
            throw TenancyViolation::crossBranchWrite($model);
        }
    }
}
