<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string $code
 * @property string|null $address
 * @property string|null $phone
 * @property string $timezone
 * @property string|null $logo_path
 * @property string|null $npwp
 * @property bool $tax_enabled
 * @property string $tax_name
 * @property string $tax_rate
 * @property bool $tax_inclusive
 * @property string|null $invoice_footer
 */
class Branch extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'address',
        'phone',
        'timezone',
        'logo_path',
        'npwp',
        'tax_enabled',
        'tax_name',
        'tax_rate',
        'tax_inclusive',
        'invoice_footer',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'address' => null,
        'phone' => null,
        'timezone' => 'Asia/Jakarta',
        'logo_path' => null,
        'npwp' => null,
        'tax_enabled' => false,
        'tax_name' => 'PPN',
        'tax_rate' => '11.00',
        'tax_inclusive' => false,
        'invoice_footer' => null,
    ];

    protected function casts(): array
    {
        return [
            'tax_enabled' => 'boolean',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
