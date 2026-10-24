<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A part used on a work order. Its quantity has already left the branch stock.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $work_order_id
 * @property int $part_id
 * @property string $qty DECIMAL(12,2)
 * @property int $unit_price
 * @property int $unit_cost
 * @property int $line_total
 * @property int $sort
 * @property-read Part $part
 */
class WorkOrderPart extends Model
{
    use Auditable;
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'work_order_id',
        'part_id',
        'qty',
        'unit_price',
        'unit_cost',
        'line_total',
        'sort',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'line_total' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function qtyHundredths(): int
    {
        return Quantity::fromDecimal($this->qty);
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }
}
