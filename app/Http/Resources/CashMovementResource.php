<?php

namespace App\Http\Resources;

use App\Models\CashMovement;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $timezone = app(TenantContext::class)->branch()->timezone ?? 'Asia/Jakarta';

        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'amount_label' => ($this->amount > 0 ? '+' : '').Money::format($this->amount),
            'balance_after_label' => Money::format($this->balance_after),
            'note' => $this->note,
            'user' => $this->creator?->name,
            'created_at_label' => $this->created_at->copy()->setTimezone($timezone)->translatedFormat('j M Y, H:i'),
        ];
    }
}
