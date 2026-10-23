<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\CustomerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Shared by all branches of a tenant (SPEC section 1).
 *
 * @property int $id
 * @property int $tenant_id
 * @property CustomerType $type
 * @property string $name
 * @property string|null $phone E.164, e.g. +6281234567890
 * @property string|null $whatsapp E.164
 * @property string|null $email
 * @property string|null $address
 * @property string|null $npwp
 * @property string|null $notes
 * @property Carbon $created_at
 */
class Customer extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<CustomerFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'phone',
        'whatsapp',
        'email',
        'address',
        'npwp',
        'notes',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'individual',
        'phone' => null,
        'whatsapp' => null,
        'email' => null,
        'address' => null,
        'npwp' => null,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
        ];
    }

    /**
     * Vehicles this customer owns now.
     *
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<VehicleOwnership, $this>
     */
    public function ownerships(): HasMany
    {
        return $this->hasMany(VehicleOwnership::class);
    }
}
