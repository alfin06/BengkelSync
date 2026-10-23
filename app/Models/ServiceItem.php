<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ServiceItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One job in the service catalog, e.g. "Ganti oli mesin". Work orders copy its name and
 * price when the job is added, so changing the catalog never changes past documents.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $code
 * @property string $name
 * @property string|null $category
 * @property int $default_price integer Rupiah
 * @property int|null $estimated_minutes
 * @property bool $is_active
 */
class ServiceItem extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<ServiceItemFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'default_price',
        'estimated_minutes',
        'is_active',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'code' => null,
        'category' => null,
        'default_price' => 0,
        'estimated_minutes' => null,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'integer',
            'estimated_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
