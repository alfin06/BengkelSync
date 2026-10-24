<?php

namespace App\Models;

use App\Enums\CashMovementType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only money ledger (SPEC section 8.3). Written only by CashService; corrections
 * are new movements, never edits.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $cash_account_id
 * @property CashMovementType $type
 * @property int $amount signed Rupiah
 * @property int $balance_after
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $transfer_group
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read User|null $creator
 */
class CashMovement extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;

    public const UPDATED_AT = null;

    protected $fillable = [
        'cash_account_id',
        'type',
        'amount',
        'balance_after',
        'reference_type',
        'reference_id',
        'transfer_group',
        'note',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reference_type' => null,
        'reference_id' => null,
        'transfer_group' => null,
        'note' => null,
        'created_by' => null,
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cash movements are append-only.'));
        static::deleting(fn () => throw new LogicException('Cash movements are append-only.'));
    }

    protected function casts(): array
    {
        return [
            'type' => CashMovementType::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
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
