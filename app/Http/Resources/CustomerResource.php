<?php

namespace App\Http\Resources;

use App\Models\Customer;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'name' => $this->name,
            'phone' => $this->phone === null ? null : Phone::format($this->phone),
            'phone_e164' => $this->phone,
            'whatsapp' => $this->whatsapp === null ? null : Phone::format($this->whatsapp),
            // wa.me wants the number without "+".
            'whatsapp_link' => $this->whatsapp === null ? null : 'https://wa.me/'.ltrim($this->whatsapp, '+'),
            'email' => $this->email,
            'address' => $this->address,
            'npwp' => $this->npwp,
            'notes' => $this->notes,
            'vehicles' => $this->whenLoaded('vehicles', fn () => VehicleResource::collection($this->vehicles)->resolve($request)),
        ];
    }
}
