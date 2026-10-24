<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One payment method on a kwitansi, e.g. Rp 500.000 cash. The money (plus any card
 * surcharge) went into its cash account. Never deleted: a wrong line is voided.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $receipt_id
 * @property int $invoice_id
 * @property int $payment_method_id
 * @property int $cash_account_id
 * @property int $amount applied to the invoice
 * @property string $surcharge_percent DECIMAL(5,2)
 * @property int $surcharge_amount
 * @property int|null $cash_tendered
 * @property string|null $reference
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property int|null $voided_by
 * @property-read PaymentMethod $method
 * @property-read CashAccount $account
 * @property-read User|null $voider
 */
class Payment extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'receipt_id',
        'invoice_id',
        'payment_method_id',
        'cash_account_id',
        'amount',
        'surcharge_percent',
        'surcharge_amount',
        'cash_tendered',
        'reference',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'surcharge_percent' => '0.00',
        'surcharge_amount' => 0,
        'cash_tendered' => null,
        'reference' => null,
        'voided_at' => null,
        'void_reason' => null,
        'voided_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'surcharge_percent' => 'decimal:2',
            'surcharge_amount' => 'integer',
            'cash_tendered' => 'integer',
            'voided_at' => 'datetime',
        ];
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    /**
     * What the customer handed over for this line: the amount plus the card surcharge.
     */
    public function received(): int
    {
        return $this->amount + $this->surcharge_amount;
    }

    /**
     * @return BelongsTo<PaymentReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'receipt_id');
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<PaymentMethod, $this>
     */
    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
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
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
