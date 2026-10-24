<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stock of one part in one branch. qty is only changed by StockService (CLAUDE.md rule 6),
 * which writes a StockMovement in the same transaction.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $branch_id
 * @property int $part_id
 * @property string $qty DECIMAL(12,2), e.g. "4.20"; use qtyHundredths()
 * @property string $min_stock DECIMAL(12,2): low-stock threshold
 * @property string|null $location
 */
class PartBranchStock extends Model
{
    use BelongsToBranch;
    use BelongsToTenant;

    protected $table = 'part_branch_stock';

    /**
     * qty is deliberately not fillable: only StockService sets it.
     */
    protected $fillable = [
        'part_id',
        'min_stock',
        'location',
    ];

    /**
     * Same defaults as the database, so a freshly created model has every attribute.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'qty' => '0.00',
        'min_stock' => '0.00',
        'location' => null,
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'min_stock' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }

    public function qtyHundredths(): int
    {
        return Quantity::fromDecimal($this->qty);
    }

    public function minStockHundredths(): int
    {
        return Quantity::fromDecimal($this->min_stock);
    }

    public function isLow(): bool
    {
        return $this->minStockHundredths() > 0 && $this->qtyHundredths() <= $this->minStockHundredths();
    }
}
