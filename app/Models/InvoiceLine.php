<?php

namespace App\Models;

use App\Enums\InvoiceLineType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $invoice_id
 * @property InvoiceLineType $type
 * @property int|null $part_id
 * @property int|null $service_item_id
 * @property string $description
 * @property string $qty DECIMAL(12,2)
 * @property int $unit_price
 * @property int|null $unit_cost
 * @property int $discount_amount
 * @property int $line_total
 * @property int|null $mechanic_id
 * @property int $sort
 * @property-read Part|null $part
 */
class InvoiceLine extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'type',
        'part_id',
        'service_item_id',
        'description',
        'qty',
        'unit_price',
        'unit_cost',
        'discount_amount',
        'line_total',
        'mechanic_id',
        'sort',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'part_id' => null,
        'service_item_id' => null,
        'unit_cost' => null,
        'discount_amount' => 0,
        'mechanic_id' => null,
        'sort' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => InvoiceLineType::class,
            'qty' => 'decimal:2',
            'unit_price' => 'integer',
            'unit_cost' => 'integer',
            'discount_amount' => 'integer',
            'line_total' => 'integer',
            'sort' => 'integer',
        ];
    }

    public function qtyHundredths(): int
    {
        return Quantity::fromDecimal($this->qty);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Part, $this>
     */
    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class)->withTrashed();
    }
}
