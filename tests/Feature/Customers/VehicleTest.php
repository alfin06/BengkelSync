<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Services\Customers\VehicleService;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function validVehicle(array $overrides = []): array
{
    return [
        'plate_number' => 'h1234ab',
        'make' => 'Toyota',
        'model' => 'Avanza',
        'year' => 2019,
        'color' => 'Silver',
        'vin' => 'mhfm1ba3jk0123456',
        'engine_number' => null,
        'transmission' => 'manual',
        'fuel_type' => 'gasoline',
        'last_odometer' => 45000,
        'notes' => null,
        ...$overrides,
    ];
}

function ownershipsOf(Vehicle $vehicle): array
{
    return VehicleOwnership::query()->where('vehicle_id', $vehicle->id)->orderBy('id')
        ->get(['customer_id', 'started_on', 'ended_on', 'reason'])
        ->map(fn (VehicleOwnership $o) => [$o->customer_id, $o->started_on->toDateString(), $o->ended_on?->toDateString(), $o->reason])
        ->all();
}

it('adds a vehicle with a normalized plate and opens its ownership', function () {
    $shop = provisionTenant();
    $customer = customerIn($shop);
    Carbon::setTestNow('2026-10-25 20:00:00'); // already the 26th in Jakarta

    $this->actingAs($shop->owner)->post(route('vehicles.store', $customer), validVehicle())->assertSessionHasNoErrors();

    inTenant($shop->tenant, null, function () use ($customer) {
        $vehicle = Vehicle::query()->firstOrFail();

        expect($vehicle->plate_number)->toBe('H 1234 AB')
            ->and($vehicle->vin)->toBe('MHFM1BA3JK0123456')
            ->and($vehicle->customer_id)->toBe($customer->id)
            ->and(ownershipsOf($vehicle))->toBe([[$customer->id, '2026-10-26', null, null]]);
    });
});

it('rejects a plate that is already used in the tenant, in any spelling', function () {
    $shop = provisionTenant();
    $customer = customerIn($shop);
    vehicleFor($shop, $customer, ['plate_number' => 'H 1234 AB']);

    $this->actingAs($shop->owner)
        ->post(route('vehicles.store', $customer), validVehicle(['plate_number' => 'h-1234-ab']))
        ->assertSessionHasErrors(['plate_number' => __('vehicles.plate_taken')]);
});

it('allows the same plate in another tenant', function () {
    $other = provisionTenant('Bengkel Lain');
    vehicleFor($other, customerIn($other), ['plate_number' => 'H 1234 AB']);
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('vehicles.store', customerIn($shop)), validVehicle())
        ->assertSessionHasNoErrors();
});

it('rejects invalid input', function (array $overrides, string $field) {
    $shop = provisionTenant();

    $this->actingAs($shop->owner)
        ->post(route('vehicles.store', customerIn($shop)), validVehicle($overrides))
        ->assertSessionHasErrors($field);
})->with([
    'bad plate' => [['plate_number' => '1234'], 'plate_number'],
    'missing make' => [['make' => ''], 'make'],
    'year in the future' => [['year' => 2100], 'year'],
    'negative odometer' => [['last_odometer' => -5], 'last_odometer'],
    'unknown fuel' => [['fuel_type' => 'coal'], 'fuel_type'],
]);

it('asks before restoring a deleted vehicle with the same plate, then restores it for the new owner', function () {
    $shop = provisionTenant();
    $oldOwner = customerIn($shop, ['name' => 'Pemilik Lama']);
    $newOwner = customerIn($shop, ['name' => 'Pemilik Baru']);
    $vehicle = vehicleFor($shop, $oldOwner, ['plate_number' => 'H 1234 AB']);
    inTenant($shop->tenant, $shop->branch, fn () => app(VehicleService::class)->delete($vehicle));

    $this->actingAs($shop->owner)
        ->post(route('vehicles.store', $newOwner), validVehicle())
        ->assertSessionHasErrors(['plate_number' => __('vehicles.plate_deleted'), 'restorable']);

    $this->actingAs($shop->owner)
        ->post(route('vehicles.store', $newOwner), validVehicle(['restore_deleted' => true]))
        ->assertRedirect(route('vehicles.show', $vehicle));

    inTenant($shop->tenant, null, function () use ($vehicle, $oldOwner, $newOwner) {
        $restored = Vehicle::query()->findOrFail($vehicle->id);

        expect($restored->customer_id)->toBe($newOwner->id)
            ->and(Vehicle::withTrashed()->count())->toBe(1)
            ->and(array_column(ownershipsOf($restored), 0))->toBe([$oldOwner->id, $newOwner->id]);
    });
});

it('changes the owner, keeping the history and auditing it', function () {
    $shop = provisionTenant();
    $oldOwner = customerIn($shop);
    $newOwner = customerIn($shop);
    $vehicle = vehicleFor($shop, $oldOwner);

    $this->actingAs($shop->owner)
        ->put(route('vehicles.owner.update', $vehicle), ['customer_id' => $newOwner->id, 'reason' => 'Dijual'])
        ->assertRedirect(route('vehicles.show', $vehicle))
        ->assertSessionHas('success');

    inTenant($shop->tenant, null, function () use ($vehicle, $oldOwner, $newOwner) {
        $history = ownershipsOf($vehicle);

        expect($vehicle->fresh()?->customer_id)->toBe($newOwner->id)
            ->and($history)->toHaveCount(2)
            ->and($history[0][0])->toBe($oldOwner->id)
            ->and($history[0][2])->not->toBeNull()
            ->and($history[1])->toMatchArray([0 => $newOwner->id, 2 => null, 3 => 'Dijual'])
            ->and(AuditLog::query()->where('auditable_id', $vehicle->id)->where('action', 'updated')->latest('id')->first()?->new_values)
            ->toMatchArray(['customer_id' => $newOwner->id]);
    });
});

it('refuses the current owner or a customer of another tenant as new owner', function () {
    $shop = provisionTenant();
    $owner = customerIn($shop);
    $vehicle = vehicleFor($shop, $owner);
    $other = provisionTenant('Bengkel Lain');
    $foreign = customerIn($other);

    $this->actingAs($shop->owner)
        ->put(route('vehicles.owner.update', $vehicle), ['customer_id' => $owner->id])
        ->assertSessionHas('error', __('vehicles.same_owner'));

    $this->actingAs($shop->owner)
        ->put(route('vehicles.owner.update', $vehicle), ['customer_id' => $foreign->id])
        ->assertSessionHasErrors('customer_id');
});

it('updates a vehicle and keeps its own plate valid', function () {
    $shop = provisionTenant();
    $vehicle = vehicleFor($shop, customerIn($shop), ['plate_number' => 'H 1234 AB']);

    $this->actingAs($shop->owner)
        ->put(route('vehicles.update', $vehicle), validVehicle(['plate_number' => 'H 1234 AB', 'color' => 'Hitam']))
        ->assertRedirect(route('vehicles.show', $vehicle));

    expect($vehicle->fresh()?->color)->toBe('Hitam');
});

it('shows a vehicle with its ownership history', function () {
    $shop = provisionTenant();
    $vehicle = vehicleFor($shop, customerIn($shop, ['name' => 'Budi']));
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);

    $this->actingAs($frontDesk)->get(route('vehicles.show', $vehicle))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vehicles/Show')
            ->where('vehicle.customer.name', 'Budi')
            ->has('ownerships', 1)
            ->where('can.changeOwner', true)
            ->where('can.delete', false));
});

it('enforces vehicle permissions and tenant isolation', function () {
    $shop = provisionTenant();
    $vehicle = vehicleFor($shop, customerIn($shop));
    $warehouse = userWithRole($shop, RoleName::Warehouse);
    $frontDesk = userWithRole($shop, RoleName::FrontDesk);
    $other = provisionTenant('Bengkel Lain');

    $this->actingAs($warehouse)->get(route('vehicles.show', $vehicle))->assertForbidden();
    $this->actingAs($frontDesk)->delete(route('vehicles.destroy', $vehicle))->assertForbidden();
    $this->actingAs($other->owner)->get(route('vehicles.show', $vehicle))->assertNotFound();
});

it('offers makes and models already used by the shop', function () {
    $shop = provisionTenant();
    $customer = customerIn($shop);
    vehicleFor($shop, $customer, ['make' => 'Toyota', 'model' => 'Avanza']);
    vehicleFor($shop, $customer, ['make' => 'Toyota', 'model' => 'Innova']);

    $this->actingAs($shop->owner)->get(route('vehicles.create', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Vehicles/Create')
            ->where('suggestions.makes', ['Toyota'])
            ->where('suggestions.models.Toyota', ['Avanza', 'Innova']));
});
