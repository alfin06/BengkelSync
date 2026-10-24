<?php

use App\Enums\DocumentType;
use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\DocumentSequence;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\PartBranchStock;
use App\Models\ServiceItem;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleOwnership;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\VehicleService;
use App\Services\Inventory\StockService;
use App\Services\Tenancy\ProvisionedTenant;
use App\Support\DocumentNumber;
use Illuminate\Support\Str;

// CLAUDE.md rule 1: every tenant-owned model proves tenant A cannot read or write tenant B's data.

it('isolates branches', function () {
    assertTenantIsolated(Branch::class, fn (ProvisionedTenant $shop) => Branch::query()->create([
        'name' => 'Cabang '.Str::random(4),
        'code' => Str::upper(Str::random(4)),
    ]));
});

it('isolates users', function () {
    assertTenantIsolated(User::class, fn (ProvisionedTenant $shop) => userWithRole($shop, RoleName::FrontDesk));
});

it('isolates audit logs', function () {
    assertTenantIsolated(AuditLog::class, fn (ProvisionedTenant $shop) => app(AuditLogger::class)->record('test', $shop->branch));
});

it('isolates customers', function () {
    assertTenantIsolated(Customer::class, fn (ProvisionedTenant $shop) => Customer::factory()->create());
});

it('isolates vehicles', function () {
    assertTenantIsolated(Vehicle::class, fn (ProvisionedTenant $shop) => app(VehicleService::class)
        ->create(Customer::factory()->create(), Vehicle::factory()->raw()));
});

it('isolates vehicle ownerships', function () {
    assertTenantIsolated(VehicleOwnership::class, function (ProvisionedTenant $shop) {
        $vehicle = app(VehicleService::class)->create(Customer::factory()->create(), Vehicle::factory()->raw());

        return VehicleOwnership::query()->where('vehicle_id', $vehicle->id)->firstOrFail();
    });
});

it('isolates service catalog items', function () {
    assertTenantIsolated(ServiceItem::class, fn (ProvisionedTenant $shop) => ServiceItem::factory()->create());
});

it('isolates mechanics', function () {
    assertTenantIsolated(Mechanic::class, fn (ProvisionedTenant $shop) => Mechanic::factory()->create());
});

it('isolates suppliers', function () {
    assertTenantIsolated(Supplier::class, fn (ProvisionedTenant $shop) => Supplier::factory()->create());
});

it('isolates parts', function () {
    assertTenantIsolated(Part::class, fn (ProvisionedTenant $shop) => Part::factory()->create());
});

it('isolates branch stock', function () {
    assertTenantIsolated(PartBranchStock::class, fn (ProvisionedTenant $shop) => app(StockService::class)
        ->configure(Part::factory()->create(), 100, null));
});

it('isolates stock movements', function () {
    assertTenantIsolated(StockMovement::class, fn (ProvisionedTenant $shop) => app(StockService::class)
        ->move(Part::factory()->create(), StockMovementType::Purchase, 100));
});

it('isolates document sequences', function () {
    assertTenantIsolated(DocumentSequence::class, function (ProvisionedTenant $shop) {
        DocumentNumber::next($shop->branch, DocumentType::Invoice);

        return DocumentSequence::query()->latest('id')->firstOrFail();
    });
});
