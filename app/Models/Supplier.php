<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shared by all branches of a tenant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $phone E.164
 * @property string|null $whatsapp E.164
 * @property string|null $sales_name
 * @property string|null $email
 * @property string|null $address
 * @property string|null $npwp
 * @property string|null $notes
 */
class Supplier extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'whatsapp',
        'sales_name',
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
        'phone' => null,
        'whatsapp' => null,
        'sales_name' => null,
        'email' => null,
        'address' => null,
        'npwp' => null,
        'notes' => null,
    ];
}
