<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money paid to a supplier for one purchase, out of a cash account. Never deleted: a wrong
 * payment is voided with a reason and a reversing cash movement.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $purchase_id
 * @property int $cash_account_id
 * @property Carbon $paid_on
 * @property int $amount
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $paid_by
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property-read CashAccount $account
 * @property-read User|null $payer
 */
class SupplierPayment extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'purchase_id',
        'cash_account_id',
        'paid_on',
        'amount',
        'reference',
        'note',
        'paid_by',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'reference' => null,
        'note' => null,
        'paid_by' => null,
        'voided_at' => null,
        'void_reason' => null,
        'voided_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
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
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
