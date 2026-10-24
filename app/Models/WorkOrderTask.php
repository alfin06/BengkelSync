<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One item of the front-desk checklist ("Cek bunyi kaki-kaki").
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $work_order_id
 * @property string $description
 * @property bool $is_done
 * @property int $sort
 */
class WorkOrderTask extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'work_order_id',
        'description',
        'is_done',
        'sort',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_done' => false,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
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
}
