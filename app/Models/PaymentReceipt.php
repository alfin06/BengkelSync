<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One checkout at the counter = one printed kwitansi. Its lines are the payment methods
 * used. Written only by PaymentService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number e.g. KLM/KW/2026/00001
 * @property int $invoice_id
 * @property Carbon $paid_on
 * @property int $amount applied to the invoice when received (cancelled lines included)
 * @property int $surcharge_total
 * @property int|null $received_by
 * @property string|null $note
 * @property Carbon $created_at
 * @property-read Invoice $invoice
 * @property-read Collection<int, Payment> $payments
 * @property-read User|null $receiver
 */
class PaymentReceipt extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'invoice_id',
        'paid_on',
        'note',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'surcharge_total' => 0,
        'received_by' => null,
        'note' => null,
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'integer',
            'surcharge_total' => 'integer',
        ];
    }

    /**
     * Still counted towards the invoice: lines that were not cancelled.
     */
    public function activeAmount(): int
    {
        return (int) $this->payments->whereNull('voided_at')->sum('amount');
    }

    public function isFullyVoided(): bool
    {
        return $this->payments->every(fn (Payment $payment) => $payment->isVoided());
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'receipt_id')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
