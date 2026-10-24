<?php

namespace App\Http\Resources;

use App\Models\Supplier;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
class SupplierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone === null ? null : Phone::format($this->phone),
            'phone_e164' => $this->phone,
            'whatsapp' => $this->whatsapp === null ? null : Phone::format($this->whatsapp),
            'whatsapp_link' => $this->whatsapp === null ? null : 'https://wa.me/'.ltrim($this->whatsapp, '+'),
            'sales_name' => $this->sales_name,
            'email' => $this->email,
            'address' => $this->address,
            'npwp' => $this->npwp,
            'notes' => $this->notes,
        ];
    }
}
