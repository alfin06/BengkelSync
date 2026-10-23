<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public function __construct(
        private readonly TenantContext $context,
        private readonly AuthFactory $auth,
        private readonly Request $request,
    ) {}

    /**
     * Record an explicit action, e.g. a void or a cash adjustment that needs a reason.
     *
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function record(
        string $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        ?string $reason = null,
    ): AuditLog {
        $userId = $this->auth->guard()->id();
        // Read raw attributes: not every audited model has tenant_id or branch_id columns.
        $attributes = $subject?->getAttributes() ?? [];

        return AuditLog::create([
            'tenant_id' => (int) ($attributes['tenant_id'] ?? $this->context->requireTenant()->getKey()),
            'branch_id' => $attributes['branch_id'] ?? $this->context->branchId(),
            'user_id' => is_int($userId) ? $userId : null,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'reason' => $reason,
            'ip' => app()->runningInConsole() ? null : $this->request->ip(),
        ]);
    }

    public function modelCreated(Model $model): void
    {
        $this->record(self::CREATED, $model, new: $this->filter($model, $model->getAttributes()));
    }

    public function modelUpdated(Model $model): void
    {
        $new = $this->filter($model, $model->getChanges());

        if ($new === []) {
            return;
        }

        $old = array_intersect_key($model->getRawOriginal(), $new);

        $this->record(self::UPDATED, $model, $old, $new);
    }

    public function modelDeleted(Model $model): void
    {
        $this->record(self::DELETED, $model, old: $this->filter($model, $model->getRawOriginal()));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filter(Model $model, array $attributes): array
    {
        $excluded = method_exists($model, 'auditExcludedAttributes')
            ? $model->auditExcludedAttributes()
            : $model->getHidden();

        return array_diff_key($attributes, array_flip($excluded));
    }
}
