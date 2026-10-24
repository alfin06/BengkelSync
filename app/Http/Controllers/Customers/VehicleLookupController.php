<?php

namespace App\Http\Controllers\Customers;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Find a vehicle by plate for check-in. Also tells whether it already has an open work
 * order in this branch, so it is not checked in twice.
 */
class VehicleLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Vehicle::class);

        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:20']]);
        $compact = preg_replace('/\s+/', '', strtoupper($validated['q'])) ?? '';

        $vehicles = Vehicle::query()
            ->with('customer')
            ->whereRaw("REPLACE(plate_number, ' ', '') LIKE ?", ['%'.$compact.'%'])
            ->orderBy('plate_number')
            ->limit(8)
            ->get();

        // Branch-scoped: only this branch's open jobs.
        $open = WorkOrder::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->whereIn('status', ['draft', 'queued', 'in_progress', 'waiting_parts', 'completed'])
            ->pluck('number', 'vehicle_id');

        return response()->json($vehicles->map(fn (Vehicle $vehicle) => [
            'id' => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'name' => $vehicle->displayName(),
            'last_odometer' => $vehicle->last_odometer,
            'customer_name' => $vehicle->customer->name,
            'open_work_order' => $open->get($vehicle->id),
        ]));
    }
}
