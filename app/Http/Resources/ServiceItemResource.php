<?php

namespace App\Http\Resources;

use App\Models\ServiceItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceItem
 */
class ServiceItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'default_price' => $this->default_price,
            'price_label' => Money::format($this->default_price),
            'estimated_minutes' => $this->estimated_minutes,
            'is_active' => $this->is_active,
        ];
    }
}
