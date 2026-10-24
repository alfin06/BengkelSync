<?php

namespace App\Http\Resources;

use App\Models\Mechanic;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The full NIK and the KTP photo URL are only included for users with
 * mechanic.view_identity; everyone else gets a masked NIK and no photo.
 *
 * @mixin Mechanic
 */
class MechanicResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $identity = $request->user()?->can('viewIdentity', $this->resource) ?? false;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone === null ? null : Phone::format($this->phone),
            'phone_e164' => $this->phone,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'nik' => $identity ? $this->nik : null,
            'nik_masked' => $this->maskedNik(),
            'has_ktp' => $this->ktp_path !== null,
            'ktp_url' => $identity && $this->ktp_path !== null ? route('mechanics.ktp', $this->id) : null,
        ];
    }
}
