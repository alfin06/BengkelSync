<?php

namespace App\Models;

use App\Enums\PartUnit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PartFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A part in the tenant-wide catalog; prices are the same in every branch (SPEC section 11).
 * Quantities live in PartBranchStock and only change through StockService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $sku
 * @property string $name
 * @property string|null $brand
 * @property string|null $category
 * @property PartUnit $unit
 * @property int $cost_price weighted average, integer Rupiah per unit
 * @property int $sell_price integer Rupiah per unit
 * @property bool $is_active
 * @property string|null $notes
 * @property-read PartBranchStock|null $stock stock in the active branch
 */
class Part extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** @use HasFactory<PartFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'sku',
        'name',
        'brand',
        'category',
        'unit',
        'cost_price',
        'sell_price',
        'is_active',
        'notes',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sku' => null,
        'brand' => null,
        'category' => null,
        'unit' => 'pcs',
        'cost_price' => 0,
        'sell_price' => 0,
        'is_active' => true,
        'notes' => null,
    ];

    protected function casts(): array
    {
        return [
            'unit' => PartUnit::class,
            'cost_price' => 'integer',
            'sell_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Stock in the active branch (PartBranchStock is branch-scoped).
     *
     * @return HasOne<PartBranchStock, $this>
     */
    public function stock(): HasOne
    {
        return $this->hasOne(PartBranchStock::class);
    }

    /**
     * @return HasMany<StockMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
