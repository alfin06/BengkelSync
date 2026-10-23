<?php

namespace App\Models\Concerns;

use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Model;

/**
 * Writes an audit_logs row on create, update and delete (CLAUDE.md rule 9).
 *
 * Hidden attributes (e.g. password) and timestamps are never logged. A model can
 * exclude more columns with a `protected array $auditExclude = [...]` property.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $model) => app(AuditLogger::class)->modelCreated($model));
        static::updated(fn (Model $model) => app(AuditLogger::class)->modelUpdated($model));
        static::deleted(fn (Model $model) => app(AuditLogger::class)->modelDeleted($model));
    }

    /**
     * @return list<string>
     */
    public function auditExcludedAttributes(): array
    {
        /** @var list<string> $extra */
        $extra = property_exists($this, 'auditExclude') ? $this->auditExclude : [];

        return array_values(array_unique([
            ...$this->getHidden(),
            ...$extra,
            $this->getCreatedAtColumn() ?? 'created_at',
            $this->getUpdatedAtColumn() ?? 'updated_at',
        ]));
    }
}
