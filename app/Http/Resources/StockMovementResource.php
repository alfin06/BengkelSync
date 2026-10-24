<?php

namespace App\Http\Resources;

use App\Models\StockMovement;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockMovement
 */
class StockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $qty = Quantity::fromDecimal($this->qty);
        $timezone = app(TenantContext::class)->branch()->timezone ?? 'Asia/Jakarta';

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'qty' => $qty,
            'qty_label' => ($qty > 0 ? '+' : '').Quantity::format($qty),
            'qty_after_label' => Quantity::format(Quantity::fromDecimal($this->qty_after)),
            'note' => $this->note,
            'user' => $this->creator?->name,
            // Stored in UTC, shown in the branch timezone (rule 11).
            'created_at_label' => $this->created_at->copy()->setTimezone($timezone)->translatedFormat('j M Y, H:i'),
        ];
    }
}
