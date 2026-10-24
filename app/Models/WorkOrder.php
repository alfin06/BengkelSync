<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A vehicle checked in for repair ("SPK"). Written only by App\Services\WorkOrders\WorkOrderService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property string $number e.g. KLM/WO/2026/00001
 * @property int $vehicle_id
 * @property int $customer_id
 * @property WorkOrderStatus $status
 * @property string $complaint
 * @property string|null $diagnosis
 * @property int|null $odometer_in
 * @property Carbon|null $estimated_completion
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string|null $cancel_reason
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read Vehicle $vehicle
 * @property-read Customer $customer
 * @property-read Branch $branch
 * @property-read Collection<int, WorkOrderTask> $tasks
 * @property-read Collection<int, WorkOrderServiceLine> $services
 * @property-read Collection<int, WorkOrderPart> $parts
 * @property-read Collection<int, Mechanic> $mechanics
 */
class WorkOrder extends Model
{
    use Auditable;
    use BelongsToBranch;
    use BelongsToTenant;

    protected $fillable = [
        'complaint',
        'diagnosis',
        'odometer_in',
        'estimated_completion',
        'notes',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
        'diagnosis' => null,
        'odometer_in' => null,
        'estimated_completion' => null,
        'started_at' => null,
        'completed_at' => null,
        'cancel_reason' => null,
        'notes' => null,
        'created_by' => null,
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkOrderStatus::class,
            'odometer_in' => 'integer',
            'estimated_completion' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Estimated bill: services plus parts. The invoice is the real bill.
     */
    public function estimatedTotal(): int
    {
        return (int) $this->services->sum('line_total') + (int) $this->parts->sum('line_total');
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<WorkOrderTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkOrderTask::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<WorkOrderServiceLine, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(WorkOrderServiceLine::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return HasMany<WorkOrderPart, $this>
     */
    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Mechanic, $this>
     */
    public function mechanics(): BelongsToMany
    {
        return $this->belongsToMany(Mechanic::class, 'work_order_mechanic')->withTrashed()->orderBy('name');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
