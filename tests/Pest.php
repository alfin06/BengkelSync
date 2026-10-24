<?php

use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\CashAccount;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\ServiceItem;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Customers\VehicleService;
use App\Services\Inventory\StockService;
use App\Services\Invoicing\InvoiceService;
use App\Services\Payments\PaymentService;
use App\Services\Tenancy\ProvisionedTenant;
use App\Services\Tenancy\TenantProvisioner;
use App\Services\Tenancy\TenantProvisioningData;
use App\Services\Users\UserService;
use App\Services\WorkOrders\WorkOrderService;
use App\Support\Tenancy\TenancyViolation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Tests\TestCase;

// TestCase uses RefreshDatabase (every test runs in a rolled-back transaction).
pest()->extend(TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Tenancy helpers
|--------------------------------------------------------------------------
*/

const TEST_PASSWORD = 'secret123';

/**
 * A complete tenant as `tenant:create` makes it: tenant, branch, default roles and owner.
 */
function provisionTenant(string $name = 'Bengkel Uji'): ProvisionedTenant
{
    $suffix = Str::lower(Str::random(6));

    return app(TenantProvisioner::class)->provision(new TenantProvisioningData(
        shopName: $name,
        branchName: 'Pusat',
        branchCode: Str::upper(Str::substr($suffix, 0, 4)),
        ownerName: 'Pemilik '.$name,
        ownerEmail: "owner-{$suffix}@example.test",
        ownerPassword: TEST_PASSWORD,
    ));
}

/**
 * @template TReturn
 *
 * @param  callable(): TReturn  $callback
 * @return TReturn
 */
function inTenant(Tenant $tenant, ?Branch $branch, callable $callback): mixed
{
    return app(TenantContext::class)->run($tenant, $branch, $callback);
}

/**
 * Create a user with a role in the given tenant, assigned to the given branches.
 *
 * @param  list<Branch>|null  $branches  defaults to the tenant's first branch
 * @param  array<string, mixed>  $attributes
 */
function userWithRole(ProvisionedTenant $shop, RoleName $role, ?array $branches = null, array $attributes = []): User
{
    return inTenant($shop->tenant, null, fn () => app(UserService::class)->create(
        attributes: [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => TEST_PASSWORD,
            ...$attributes,
        ],
        role: $role,
        branchIds: array_map(fn (Branch $branch) => $branch->id, $branches ?? [$shop->branch]),
    ));
}

function addBranch(ProvisionedTenant $shop, string $name = 'Cabang Dua'): Branch
{
    return inTenant($shop->tenant, null, fn () => Branch::query()->create([
        'name' => $name,
        'code' => Str::upper(Str::random(4)),
    ]));
}

/**
 * Proves the tenant isolation rules of CLAUDE.md rule 1 for a model:
 * tenant A cannot list, find, update, delete or create tenant B's rows.
 *
 * @param  class-string<Model>  $model
 * @param  callable(ProvisionedTenant): Model  $make  creates one row inside the given tenant's context
 */
function assertTenantIsolated(string $model, callable $make): void
{
    $a = provisionTenant('Bengkel A');
    $b = provisionTenant('Bengkel B');

    $rowA = inTenant($a->tenant, $a->branch, fn () => $make($a));
    $rowB = inTenant($b->tenant, $b->branch, fn () => $make($b));

    inTenant($a->tenant, $a->branch, function () use ($model, $rowA, $rowB, $b): void {
        $visible = $model::query()->pluck((new $model)->getKeyName())->all();

        expect($visible)->toContain($rowA->getKey())
            ->and($visible)->not->toContain($rowB->getKey())
            ->and($model::query()->find($rowB->getKey()))->toBeNull();

        $foreign = $model::query()->withoutGlobalScopes()->findOrFail($rowB->getKey());

        expect(fn () => $foreign->forceFill(['updated_at' => now()->addMinute()])->save())
            ->toThrow(TenancyViolation::class)
            ->and(fn () => $foreign->delete())
            ->toThrow(TenancyViolation::class);

        $attributes = $rowA->getAttributes();
        unset($attributes[$rowA->getKeyName()]);
        $attributes['tenant_id'] = $b->tenant->id;

        if (array_key_exists('email', $attributes)) {
            $attributes['email'] = 'copy-'.Str::random(6).'@example.test';
        }

        if (array_key_exists('code', $attributes)) {
            $attributes['code'] = Str::upper(Str::random(4));
        }

        expect(fn () => (new $model)->forceFill($attributes)->save())
            ->toThrow(TenancyViolation::class);
    });
}

/*
|--------------------------------------------------------------------------
| Model helpers shared by several test files
|--------------------------------------------------------------------------
*/

function customerIn(ProvisionedTenant $shop, array $attributes = []): Customer
{
    return inTenant($shop->tenant, $shop->branch, fn () => Customer::factory()->create($attributes));
}

function vehicleFor(ProvisionedTenant $shop, Customer $customer, array $attributes = []): Vehicle
{
    return inTenant($shop->tenant, $shop->branch, fn () => app(VehicleService::class)
        ->create($customer, [...Vehicle::factory()->raw(), ...$attributes]));
}

function partIn(ProvisionedTenant $shop, array $attributes = []): Part
{
    return inTenant($shop->tenant, $shop->branch, fn () => Part::factory()->create($attributes));
}

/**
 * Run StockService inside the shop's main branch.
 */
function stock(ProvisionedTenant $shop, callable $callback): mixed
{
    return inTenant($shop->tenant, $shop->branch, fn () => $callback(app(StockService::class)));
}

function accountIn(ProvisionedTenant $shop, string $state = 'drawer', array $attributes = []): CashAccount
{
    return inTenant($shop->tenant, $shop->branch, fn () => match ($state) {
        'bank' => CashAccount::factory()->bank()->create($attributes),
        'petty' => CashAccount::factory()->pettyCash()->create($attributes),
        default => CashAccount::factory()->create($attributes),
    });
}

function mechanicIn(ProvisionedTenant $shop, ?Branch $branch = null, array $attributes = []): Mechanic
{
    return inTenant($shop->tenant, $branch ?? $shop->branch, fn () => Mechanic::factory()->create($attributes));
}

function serviceIn(ProvisionedTenant $shop, array $attributes = []): ServiceItem
{
    return inTenant($shop->tenant, $shop->branch, fn () => ServiceItem::factory()->create($attributes));
}

/**
 * Run WorkOrderService inside the shop's main branch.
 */
function workOrders(ProvisionedTenant $shop, callable $callback): mixed
{
    return inTenant($shop->tenant, $shop->branch, fn () => $callback(app(WorkOrderService::class)));
}

/**
 * A queued work order for a fresh customer's vehicle (last odometer 40.000 km).
 */
function workOrderIn(ProvisionedTenant $shop, ?Vehicle $vehicle = null): WorkOrder
{
    $vehicle ??= vehicleFor($shop, customerIn($shop), ['last_odometer' => 40_000]);

    return workOrders($shop, fn (WorkOrderService $service) => $service->checkIn($vehicle, ['complaint' => 'Rem bunyi', 'odometer_in' => 41_000]));
}

/**
 * A part with stock on the shelf of the shop's main branch.
 *
 * @param  int  $qty  hundredths
 */
function stockedPart(ProvisionedTenant $shop, int $qty = 1000, array $attributes = []): Part
{
    $part = partIn($shop, ['sell_price' => 50_000, 'cost_price' => 35_000, ...$attributes]);
    stock($shop, fn (StockService $stock) => $stock->move($part, StockMovementType::Adjustment, $qty));

    return $part;
}

function invoices(ProvisionedTenant $shop, callable $callback): mixed
{
    return inTenant($shop->tenant, $shop->branch, fn () => $callback(app(InvoiceService::class)));
}

/**
 * A completed work order with one job (Rp 100.000) and 2 pcs of a part at Rp 50.000.
 */
function completedWorkOrder(ProvisionedTenant $shop): WorkOrder
{
    $part = stockedPart($shop);
    $workOrder = workOrderIn($shop);

    workOrders($shop, function (WorkOrderService $service) use ($workOrder, $part) {
        $service->addService($workOrder, ['description' => 'Ganti kampas rem', 'qty' => 1, 'unit_price' => 100_000]);
        $service->addPart($workOrder, $part, 200, 50_000);
        $service->changeStatus($workOrder, WorkOrderStatus::Completed);
    });

    return $workOrder;
}

/**
 * The price list of a work order as the invoice form sends it.
 *
 * @return list<array{source: string, id: int, unit_price: int, discount_amount: int}>
 */
function pricesOf(ProvisionedTenant $shop, WorkOrder $workOrder, array $override = []): array
{
    return inTenant($shop->tenant, $shop->branch, function () use ($workOrder, $override) {
        $workOrder->load(['services', 'parts']);

        return [
            ...$workOrder->services->map(fn ($line) => ['source' => 'service', 'id' => $line->id, 'unit_price' => $line->unit_price, 'discount_amount' => 0, ...($override['service'] ?? [])])->all(),
            ...$workOrder->parts->map(fn ($line) => ['source' => 'part', 'id' => $line->id, 'unit_price' => $line->unit_price, 'discount_amount' => 0, ...($override['part'] ?? [])])->all(),
        ];
    });
}

function invoiceInput(array $overrides = []): array
{
    return ['issue_date' => now('Asia/Jakarta')->toDateString(), 'discount_amount' => 0, ...$overrides];
}

function payments(ProvisionedTenant $shop, callable $callback): mixed
{
    return inTenant($shop->tenant, $shop->branch, fn () => $callback(app(PaymentService::class)));
}
