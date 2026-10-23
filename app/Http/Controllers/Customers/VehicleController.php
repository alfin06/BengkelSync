<?php

namespace App\Http\Controllers\Customers;

use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customers\StoreVehicleRequest;
use App\Http\Requests\Customers\UpdateVehicleRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\VehicleResource;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Services\Customers\VehicleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    public function create(Customer $customer): Response
    {
        Gate::authorize('create', Vehicle::class);

        return Inertia::render('Vehicles/Create', [
            'customer' => CustomerResource::make($customer)->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreVehicleRequest $request, Customer $customer): RedirectResponse
    {
        $vehicle = $this->vehicles->create($customer, $request->validated(), $request->boolean('restore_deleted'));

        return redirect()->route('vehicles.show', $vehicle)->with('success', __('vehicles.created'));
    }

    public function show(Request $request, Vehicle $vehicle): Response
    {
        Gate::authorize('view', $vehicle);

        $vehicle->load('customer');
        $ownerships = $vehicle->ownerships()->with('customer')->latest('started_on')->latest('id')->get();
        $user = $request->user();

        return Inertia::render('Vehicles/Show', [
            'vehicle' => VehicleResource::make($vehicle)->resolve(),
            'ownerships' => $ownerships->map(fn (VehicleOwnership $ownership) => [
                'id' => $ownership->id,
                'customer' => ['id' => $ownership->customer->id, 'name' => $ownership->customer->name],
                'started_on' => $ownership->started_on->toDateString(),
                'ended_on' => $ownership->ended_on?->toDateString(),
                'reason' => $ownership->reason,
            ]),
            'can' => [
                'update' => $user?->can('update', $vehicle) ?? false,
                'delete' => $user?->can('delete', $vehicle) ?? false,
                'changeOwner' => $user?->can('changeOwner', $vehicle) ?? false,
            ],
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        Gate::authorize('update', $vehicle);

        $vehicle->load('customer');

        return Inertia::render('Vehicles/Edit', [
            'vehicle' => VehicleResource::make($vehicle)->resolve(),
            ...$this->formOptions(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle): RedirectResponse
    {
        $this->vehicles->update($vehicle, $request->validated());

        return redirect()->route('vehicles.show', $vehicle)->with('success', __('vehicles.updated'));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::authorize('delete', $vehicle);

        $customerId = $vehicle->customer_id;
        $this->vehicles->delete($vehicle);

        return redirect()->route('customers.show', $customerId)->with('success', __('vehicles.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'suggestions' => $this->vehicles->suggestions(),
            'transmissions' => array_map(fn (Transmission $t) => ['value' => $t->value, 'label' => $t->label()], Transmission::cases()),
            'fuelTypes' => array_map(fn (FuelType $f) => ['value' => $f->value, 'label' => $f->label()], FuelType::cases()),
        ];
    }
}
