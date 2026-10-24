<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A bill to a customer. Written only by InvoiceService; totals are always calculated
 * on the server.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number e.g. KLM/INV/2026/00001
 * @property int|null $work_order_id
 * @property int|null $customer_id null = "Pelanggan umum"
 * @property Carbon $issue_date
 * @property Carbon|null $due_date
 * @property Carbon|null $due_soon_notified_at
 * @property Carbon|null $overdue_notified_at
 * @property int $subtotal
 * @property int $discount_amount
 * @property bool $tax_enabled
 * @property string $tax_name
 * @property string $tax_rate DECIMAL(5,2), e.g. "11.00"
 * @property bool $tax_inclusive
 * @property int $tax_amount
 * @property int $total
 * @property int $amount_paid
 * @property InvoiceStatus $status
 * @property string|null $void_reason
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property int $revision_no
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Customer|null $customer
 * @property-read WorkOrder|null $workOrder
 * @property-read Branch $branch
 * @property-read Collection<int, InvoiceLine> $lines
 * @property-read Collection<int, PaymentReceipt> $receipts
 */
class Invoice extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'issue_date',
        'due_date',
        'notes',
    ];

    /** @var list<string> */
    protected array $auditExclude = ['due_soon_notified_at', 'overdue_notified_at'];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'work_order_id' => null,
        'customer_id' => null,
        'due_date' => null,
        'due_soon_notified_at' => null,
        'overdue_notified_at' => null,
        'subtotal' => 0,
        'discount_amount' => 0,
        'tax_enabled' => false,
        'tax_name' => 'PPN',
        'tax_rate' => '0.00',
        'tax_inclusive' => false,
        'tax_amount' => 0,
        'total' => 0,
        'amount_paid' => 0,
        'status' => 'unpaid',
        'void_reason' => null,
        'voided_at' => null,
        'voided_by' => null,
        'revision_no' => 0,
        'notes' => null,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'due_soon_notified_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_enabled' => 'boolean',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'status' => InvoiceStatus::class,
            'voided_at' => 'datetime',
            'revision_no' => 'integer',
        ];
    }

    public function outstanding(): int
    {
        return $this->status === InvoiceStatus::Void ? 0 : max($this->total - $this->amount_paid, 0);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * Checkouts (kwitansi), oldest first.
     *
     * @return HasMany<PaymentReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class)->orderBy('paid_on')->orderBy('id');
    }

    /**
     * @return HasMany<InvoiceRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(InvoiceRevision::class)->orderByDesc('revision_no');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
