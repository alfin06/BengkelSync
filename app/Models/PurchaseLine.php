<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $purchase_id
 * @property int $part_id
 * @property string $qty DECIMAL(12,2)
 * @property int $unit_cost
 * @property int $line_total
 * @property-read Part $part
 */
class PurchaseLine extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'purchase_id',
        'part_id',
        'qty',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
        ];
    }

    public function qtyHundredths(): int
    {
        return Quantity::fromDecimal($this->qty);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
