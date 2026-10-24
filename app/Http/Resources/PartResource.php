<?php

namespace App\Http\Resources;

use App\Models\Part;
use App\Models\PartBranchStock;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A part with its stock in the active branch. The purchase cost is only included for
 * users allowed to see it.
 *
 * @mixin Part
 */
class PartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $canSeeCost = $request->user()?->can('viewCost', $this->resource) ?? false;
        /** @var PartBranchStock|null $stock */
        $stock = $this->relationLoaded('stock') ? $this->stock : null;
        $qty = $stock?->qtyHundredths() ?? 0;
        $min = $stock?->minStockHundredths() ?? 0;

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'brand' => $this->brand,
            'category' => $this->category,
            'unit' => $this->unit->value,
            'unit_label' => $this->unit->label(),
            'allows_decimals' => $this->unit->allowsDecimals(),
            'cost_price' => $canSeeCost ? $this->cost_price : null,
            'cost_label' => $canSeeCost ? Money::format($this->cost_price) : null,
            'sell_price' => $this->sell_price,
            'price_label' => Money::format($this->sell_price),
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'stock' => [
                // Plain strings for the inputs ("4.2"), labels for display ("4,2").
                'qty' => rtrim(rtrim(Quantity::toDecimal($qty), '0'), '.'),
                'qty_label' => Quantity::format($qty),
                'min_stock' => rtrim(rtrim(Quantity::toDecimal($min), '0'), '.'),
                'min_label' => Quantity::format($min),
                'location' => $stock?->location,
                'is_low' => $stock?->isLow() ?? false,
                'is_empty' => $qty === 0,
            ],
        ];
    }
}
