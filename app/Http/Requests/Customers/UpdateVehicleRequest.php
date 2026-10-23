<?php

namespace App\Http\Requests\Customers;

use App\Models\Vehicle;

class UpdateVehicleRequest extends VehicleRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('vehicle')) ?? false;
    }

    protected function ignoredVehicleId(): ?int
    {
        $vehicle = $this->route('vehicle');

        return $vehicle instanceof Vehicle ? $vehicle->id : null;
    }
}
