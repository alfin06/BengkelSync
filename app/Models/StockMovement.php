<?php

namespace App\Models;

use App\Enums\StockMovementType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only stock ledger (SPEC section 8.3: ledgers over mutable totals). Written only
 * by StockService; never updated or deleted, corrections are new movements.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $part_id
 * @property StockMovementType $type
 * @property string $qty signed DECIMAL(12,2)
 * @property string $qty_after DECIMAL(12,2)
 * @property int|null $unit_cost
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read User|null $creator
 */
class StockMovement extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'part_id',
        'type',
        'qty',
        'qty_after',
        'unit_cost',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit_cost' => null,
        'reference_type' => null,
        'reference_id' => null,
        'note' => null,
        'created_by' => null,
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock movements are append-only.'));
        static::deleting(fn () => throw new \LogicException('Stock movements are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'qty' => 'decimal:2',
            'qty_after' => 'decimal:2',
            'unit_cost' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
