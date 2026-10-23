<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who owned a vehicle and when. The open row (ended_on = null) is the current owner.
 * Written only by VehicleService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $vehicle_id
 * @property int $customer_id
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property string|null $reason
 * @property-read Customer $customer
 */
class VehicleOwnership extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'vehicle_id',
        'customer_id',
        'started_on',
        'ended_on',
        'reason',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ended_on' => null,
        'reason' => null,
    ];

    protected function casts(): array
    {
        return [
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
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
}
