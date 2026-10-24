<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How a customer can pay in a branch ("Tunai", "QRIS BCA", "Debit non-BCA", ...), and
 * which cash account the money lands in.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $name
 * @property PaymentMethodType $type
 * @property int $cash_account_id
 * @property string $surcharge_percent DECIMAL(5,2), e.g. "3.00"
 * @property bool $needs_reference
 * @property bool $is_active
 * @property int $sort
 * @property-read CashAccount $account
 */
class PaymentMethod extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'cash_account_id',
        'surcharge_percent',
        'needs_reference',
        'is_active',
        'sort',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'surcharge_percent' => '0.00',
        'needs_reference' => false,
        'is_active' => true,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'surcharge_percent' => 'decimal:2',
            'needs_reference' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<CashAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'cash_account_id');
    }
}
