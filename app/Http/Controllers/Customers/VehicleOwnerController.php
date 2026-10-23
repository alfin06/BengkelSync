<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\ChangeVehicleOwnerRequest;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Services\Customers\VehicleService;
use Illuminate\Http\RedirectResponse;

class VehicleOwnerController extends Controller
{
    public function update(ChangeVehicleOwnerRequest $request, Vehicle $vehicle, VehicleService $vehicles): RedirectResponse
    {
        /** @var Customer $newOwner checked in the request */
        $newOwner = $request->newOwner();

        $vehicles->changeOwner($vehicle, $newOwner, $request->validated('reason'));

        return redirect()->route('vehicles.show', $vehicle)
            ->with('success', __('vehicles.owner_changed', ['name' => $newOwner->name]));
    }
}
