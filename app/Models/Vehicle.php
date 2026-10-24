<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VehicleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shared by all branches of a tenant, so its service history spans branches (SPEC section 7).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $customer_id
 * @property string $plate_number normalized, e.g. "B 1234 XYZ"
 * @property string $make
 * @property string $model
 * @property int|null $year
 * @property string|null $color
 * @property string|null $vin
 * @property string|null $engine_number
 * @property Transmission|null $transmission
 * @property FuelType|null $fuel_type
 * @property int|null $last_odometer
 * @property string|null $notes
 * @property-read Customer $customer
 */
class Vehicle extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<VehicleFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * customer_id is not fillable: the owner only changes through VehicleService.
     */
    protected $fillable = [
        'plate_number',
        'make',
        'model',
        'year',
        'color',
        'vin',
        'engine_number',
        'transmission',
        'fuel_type',
        'last_odometer',
        'notes',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'year' => null,
        'color' => null,
        'vin' => null,
        'engine_number' => null,
        'transmission' => null,
        'fuel_type' => null,
        'last_odometer' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_odometer' => 'integer',
            'transmission' => Transmission::class,
            'fuel_type' => FuelType::class,
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return HasMany<VehicleOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(VehicleOwnership::class);
    }

    /**
     * Work orders of the active branch (the branch scope applies). Service history across
     * branches lifts only that scope, see VehicleController::show().
     *
     * @return HasMany<WorkOrder, $this>
     */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function displayName(): string
    {
        return trim("{$this->make} {$this->model}".($this->year ? " ({$this->year})" : ''));
    }
}
