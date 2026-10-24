<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job on a work order. The price is typed by staff (a catalog job only prefills it).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $work_order_id
 * @property int|null $service_item_id
 * @property string $description
 * @property int $qty
 * @property int $unit_price
 * @property int $line_total
 * @property int|null $mechanic_id
 * @property int $sort
 * @property-read Mechanic|null $mechanic
 */
class WorkOrderServiceLine extends Model
{
    use Auditable;
    use BelongsToTenant;

    protected $table = 'work_order_services';

    public $timestamps = false;

    protected $fillable = [
        'work_order_id',
        'service_item_id',
        'description',
        'qty',
        'unit_price',
        'line_total',
        'mechanic_id',
        'sort',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'service_item_id' => null,
        'qty' => 1,
        'mechanic_id' => null,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'integer',
            'line_total' => 'integer',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Mechanic, $this>
     */
    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class)->withTrashed();
    }

    /**
     * @return BelongsTo<ServiceItem, $this>
     */
    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class);
    }
}
