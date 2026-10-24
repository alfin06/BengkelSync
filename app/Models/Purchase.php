<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Goods received from a supplier. Written only by PurchaseService; payments by
 * SupplierPaymentService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number e.g. KLM/PB/2026/00001
 * @property int $supplier_id
 * @property string|null $supplier_ref
 * @property Carbon $purchase_date
 * @property Carbon|null $due_date
 * @property int $total
 * @property int $amount_paid
 * @property PurchaseStatus $status
 * @property string|null $void_reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $due_soon_notified_at
 * @property Carbon|null $overdue_notified_at
 * @property-read Supplier $supplier
 */
class Purchase extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'supplier_id',
        'supplier_ref',
        'purchase_date',
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
        'supplier_ref' => null,
        'due_date' => null,
        'total' => 0,
        'amount_paid' => 0,
        'status' => 'unpaid',
        'void_reason' => null,
        'notes' => null,
        'created_by' => null,
        'due_soon_notified_at' => null,
        'overdue_notified_at' => null,
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'due_date' => 'date',
            'total' => 'integer',
            'amount_paid' => 'integer',
            'status' => PurchaseStatus::class,
            'due_soon_notified_at' => 'datetime',
            'overdue_notified_at' => 'datetime',
        ];
    }

    public function outstanding(): int
    {
        return $this->status === PurchaseStatus::Void ? 0 : max($this->total - $this->amount_paid, 0);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    /**
     * @return HasMany<PurchaseLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseLine::class);
    }

    /**
     * @return HasMany<SupplierPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
