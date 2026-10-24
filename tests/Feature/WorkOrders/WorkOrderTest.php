<?php

use App\Enums\RoleName;
use App\Enums\StockMovementType;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Part;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderServiceLine;
use App\Models\WorkOrderTask;
use App\Services\Catalog\ServiceItemService;
use App\Services\Inventory\StockService;
use App\Services\Mechanics\MechanicService;
use App\Services\Tenancy\ProvisionedTenant;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

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

describe('check-in', function () {
    it('numbers the work order, takes the owner from the vehicle and keeps the checklist and mechanics', function () {
        $shop = provisionTenant();
        $vehicle = vehicleFor($shop, customerIn($shop), ['last_odometer' => 40_000]);
        $joko = mechanicIn($shop);

        $workOrder = workOrders($shop, fn (WorkOrderService $service) => $service->checkIn(
            $vehicle,
            ['complaint' => 'Servis 45.000 km', 'odometer_in' => 45_100],
            ['Cek bunyi kaki-kaki', '  ', 'Cek aki'],
            [$joko->id],
        ));

        expect($workOrder->number)->toStartWith($shop->branch->code.'/WO/')
            ->and($workOrder->status)->toBe(WorkOrderStatus::Queued)
            ->and($workOrder->customer_id)->toBe($vehicle->customer_id)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $workOrder->tasks()->pluck('description')->all()))->toBe(['Cek bunyi kaki-kaki', 'Cek aki'])
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $workOrder->mechanics()->pluck('mechanics.id')->all()))->toBe([$joko->id])
            ->and($vehicle->fresh()?->last_odometer)->toBe(45_100);
    });

    it('starts as a draft when asked, and never lowers the vehicle odometer', function () {
        $shop = provisionTenant();
        $vehicle = vehicleFor($shop, customerIn($shop), ['last_odometer' => 40_000]);

        $draft = workOrders($shop, fn (WorkOrderService $service) => $service->checkIn($vehicle, ['complaint' => 'Estimasi', 'odometer_in' => 4_000], draft: true));

        expect($draft->status)->toBe(WorkOrderStatus::Draft)
            ->and($vehicle->fresh()?->last_odometer)->toBe(40_000);
    });

    it('checks in a new vehicle of a new customer in one step', function () {
        $shop = provisionTenant();
        $mechanic = mechanicIn($shop);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->post(route('work-orders.store'), [
            'mode' => 'new',
            'customer' => ['name' => 'Budi Santoso', 'phone' => '0812-3456-7890'],
            'vehicle' => ['plate_number' => 'h 1234 ab', 'make' => 'Toyota', 'model' => 'Avanza', 'year' => 2019],
            'complaint' => 'AC kurang dingin',
            'odometer_in' => 82_000,
            'estimated_completion' => '2026-10-24T15:30',
            'tasks' => ['Cek freon'],
            'mechanic_ids' => [$mechanic->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        inTenant($shop->tenant, $shop->branch, function () use ($mechanic) {
            $workOrder = WorkOrder::query()->with(['vehicle', 'customer', 'mechanics'])->sole();

            expect($workOrder->vehicle->plate_number)->toBe('H 1234 AB')
                ->and($workOrder->vehicle->last_odometer)->toBe(82_000)
                ->and($workOrder->customer->name)->toBe('Budi Santoso')
                ->and($workOrder->customer->phone)->toBe('+6281234567890')
                ->and($workOrder->mechanics->pluck('id')->all())->toBe([$mechanic->id])
                // Typed in Jakarta time, stored in UTC (rule 11).
                ->and($workOrder->estimated_completion?->toDateTimeString())->toBe('2026-10-24 08:30:00');
        });
    });

    it('checks in a new vehicle for an existing customer', function () {
        $shop = provisionTenant();
        $customer = customerIn($shop);

        $this->actingAs($shop->owner)->post(route('work-orders.store'), [
            'mode' => 'new',
            'customer_id' => $customer->id,
            'vehicle' => ['plate_number' => 'B 77 CD', 'make' => 'Honda', 'model' => 'Jazz'],
            'complaint' => 'Ganti oli',
        ])->assertSessionHasNoErrors();

        expect(inTenant($shop->tenant, null, fn () => Customer::query()->count()))->toBe(1)
            ->and(inTenant($shop->tenant, null, fn () => Vehicle::query()->sole()->customer_id))->toBe($customer->id);
    });

    it('refuses a new vehicle whose plate is already registered', function () {
        $shop = provisionTenant();
        vehicleFor($shop, customerIn($shop), ['plate_number' => 'B 1234 XYZ']);

        $this->actingAs($shop->owner)->post(route('work-orders.store'), [
            'mode' => 'new',
            'customer' => ['name' => 'Dobel'],
            'vehicle' => ['plate_number' => 'b1234xyz', 'make' => 'Toyota', 'model' => 'Avanza'],
            'complaint' => 'x',
        ])->assertSessionHasErrors('vehicle.plate_number');
    });

    it('finds vehicles by plate however it is typed, with their open work order', function () {
        $shop = provisionTenant();
        $vehicle = vehicleFor($shop, customerIn($shop), ['plate_number' => 'B 1234 XYZ']);
        $workOrder = workOrderIn($shop, $vehicle);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->getJson(route('vehicles.lookup', ['q' => 'b1234']))
            ->assertOk()
            ->assertJsonPath('0.plate_number', 'B 1234 XYZ')
            ->assertJsonPath('0.open_work_order', $workOrder->number);
    });
});

describe('status flow', function () {
    it('records start and finish times and can be reopened before invoicing', function () {
        $shop = provisionTenant();
        $workOrder = workOrderIn($shop);

        workOrders($shop, function (WorkOrderService $service) use ($workOrder) {
            $service->changeStatus($workOrder, WorkOrderStatus::InProgress);
            expect($workOrder->started_at)->not->toBeNull();

            $service->changeStatus($workOrder, WorkOrderStatus::WaitingParts);
            $service->changeStatus($workOrder, WorkOrderStatus::InProgress);
            $service->changeStatus($workOrder, WorkOrderStatus::Completed);
            expect($workOrder->completed_at)->not->toBeNull();

            $service->changeStatus($workOrder, WorkOrderStatus::InProgress);
            expect($workOrder->completed_at)->toBeNull()
                ->and($workOrder->status)->toBe(WorkOrderStatus::InProgress);
        });
    });

    it('refuses moves outside the flow, including the ones only invoices make', function (WorkOrderStatus $from, WorkOrderStatus $to) {
        $shop = provisionTenant();
        $workOrder = workOrderIn($shop);
        $workOrder->forceFill(['status' => $from])->saveQuietly();

        expect(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->changeStatus($workOrder, $to)))
            ->toThrow(BusinessRuleViolation::class);
    })->with([
        'queued to waiting parts' => [WorkOrderStatus::Queued, WorkOrderStatus::WaitingParts],
        'draft to completed' => [WorkOrderStatus::Draft, WorkOrderStatus::Completed],
        'completed to invoiced' => [WorkOrderStatus::Completed, WorkOrderStatus::Invoiced],
        'cancelled to queued' => [WorkOrderStatus::Cancelled, WorkOrderStatus::Queued],
    ]);

    it('lets a quick job go straight from the queue to completed', function () {
        $shop = provisionTenant();
        $workOrder = workOrderIn($shop);

        workOrders($shop, fn (WorkOrderService $service) => $service->changeStatus($workOrder, WorkOrderStatus::Completed));

        expect($workOrder->started_at)->not->toBeNull()->and($workOrder->completed_at)->not->toBeNull();
    });
});

describe('parts and stock', function () {
    it('takes parts from the shelf when added and puts the difference back when changed or removed', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop, 1000);
        $workOrder = workOrderIn($shop);

        $line = workOrders($shop, fn (WorkOrderService $service) => $service->addPart($workOrder, $part, 400, 55_000));

        expect(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part)))->toBe(600)
            ->and($line->unit_cost)->toBe(35_000)
            ->and($line->line_total)->toBe(220_000);

        workOrders($shop, fn (WorkOrderService $service) => $service->updatePart($workOrder, $line, 100, 55_000));
        expect(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part)))->toBe(900);

        workOrders($shop, fn (WorkOrderService $service) => $service->removePart($workOrder, $line));
        expect(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part)))->toBe(1000)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => WorkOrderPart::query()->count()))->toBe(0);
    });

    it('refuses a part the branch does not have enough of', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop, 200);
        $workOrder = workOrderIn($shop);

        expect(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->addPart($workOrder, $part, 300, 50_000)))
            ->toThrow(BusinessRuleViolation::class);

        expect(inTenant($shop->tenant, $shop->branch, fn () => WorkOrderPart::query()->count()))->toBe(0);
    });

    it('takes oil by the liter with decimals and prices it rounded half up', function () {
        $shop = provisionTenant();
        $oil = stockedPart($shop, 2000, ['unit' => 'liter']);
        $workOrder = workOrderIn($shop);

        $line = workOrders($shop, fn (WorkOrderService $service) => $service->addPart($workOrder, $oil, 420, 80_333));

        // 4,2 × 80.333 = 337.398,6
        expect($line->line_total)->toBe(337_399)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($oil)))->toBe(1580);
    });

    it('puts every part back when the work order is cancelled, with a reason in the audit log', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop, 1000);
        $workOrder = workOrderIn($shop);
        workOrders($shop, fn (WorkOrderService $service) => $service->addPart($workOrder, $part, 300, 50_000));

        workOrders($shop, fn (WorkOrderService $service) => $service->cancel($workOrder, 'Pelanggan batal'));

        expect($workOrder->status)->toBe(WorkOrderStatus::Cancelled)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => app(StockService::class)->available($part)))->toBe(1000)
            ->and(inTenant($shop->tenant, null, fn () => AuditLog::query()->where('action', 'work_order_cancelled')->value('reason')))->toBe('Pelanggan batal')
            ->and(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->cancel($workOrder, 'Lagi')))->toThrow(BusinessRuleViolation::class);
    });

    it('locks services, parts and the checklist once invoiced', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop);
        $workOrder = workOrderIn($shop);
        $workOrder->forceFill(['status' => WorkOrderStatus::Invoiced])->saveQuietly();

        expect(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->addPart($workOrder, $part, 100, 1)))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->addService($workOrder, ['description' => 'x', 'qty' => 1, 'unit_price' => 1])))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->addTask($workOrder, 'x')))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->cancel($workOrder, 'x')))->toThrow(BusinessRuleViolation::class);
    });
});

describe('services and mechanics', function () {
    it('keeps the typed price, adds the service mechanic to the job, and blocks deleting used records', function () {
        $shop = provisionTenant();
        $item = serviceIn($shop, ['default_price' => 75_000]);
        $joko = mechanicIn($shop);
        $workOrder = workOrderIn($shop);

        $line = workOrders($shop, fn (WorkOrderService $service) => $service->addService($workOrder, [
            'service_item_id' => $item->id, 'description' => 'Spooring', 'qty' => 2, 'unit_price' => 90_000, 'mechanic_id' => $joko->id,
        ]));

        expect($line->line_total)->toBe(180_000)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $workOrder->mechanics()->pluck('mechanics.id')->all()))->toBe([$joko->id])
            ->and(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(MechanicService::class)->delete($joko)))->toThrow(BusinessRuleViolation::class)
            ->and(fn () => inTenant($shop->tenant, $shop->branch, fn () => app(ServiceItemService::class)->delete($item)))->toThrow(BusinessRuleViolation::class);
    });

    it('refuses inactive catalog jobs and mechanics of another branch', function () {
        $shop = provisionTenant();
        $inactive = serviceIn($shop, ['is_active' => false]);
        $east = addBranch($shop);
        $stranger = mechanicIn($shop, $east);
        $workOrder = workOrderIn($shop);

        expect(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->addService($workOrder, ['service_item_id' => $inactive->id, 'description' => 'x', 'qty' => 1, 'unit_price' => 1])))
            ->toThrow(BusinessRuleViolation::class)
            ->and(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->assignMechanics($workOrder, [$stranger->id])))
            ->toThrow(BusinessRuleViolation::class);
    });

    it('keeps a mechanic who was deactivated after being assigned, but does not add inactive ones', function () {
        $shop = provisionTenant();
        $joko = mechanicIn($shop);
        $retired = mechanicIn($shop, null, ['is_active' => false]);
        $workOrder = workOrderIn($shop);
        workOrders($shop, fn (WorkOrderService $service) => $service->assignMechanics($workOrder, [$joko->id]));
        $joko->forceFill(['is_active' => false])->saveQuietly();

        workOrders($shop, fn (WorkOrderService $service) => $service->assignMechanics($workOrder, [$joko->id]));

        expect(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->assignMechanics($workOrder, [$joko->id, $retired->id])))
            ->toThrow(BusinessRuleViolation::class)
            ->and(inTenant($shop->tenant, $shop->branch, fn () => $workOrder->mechanics()->pluck('mechanics.id')->all()))->toBe([$joko->id]);
    });
});

describe('HTTP', function () {
    it('lets Front Desk run a job from check-in to completed, adding services, parts and ticks', function () {
        $shop = provisionTenant();
        $frontDesk = userWithRole($shop, RoleName::FrontDesk);
        $part = stockedPart($shop);
        $workOrder = workOrderIn($shop);
        $task = inTenant($shop->tenant, $shop->branch, fn () => WorkOrderTask::query()->create(['work_order_id' => $workOrder->id, 'description' => 'Cek rem']));

        $this->actingAs($frontDesk);
        $this->post(route('work-orders.services.store', $workOrder), ['description' => 'Ganti kampas rem', 'qty' => 1, 'unit_price' => 60_000])->assertSessionHasNoErrors();
        $this->post(route('work-orders.parts.store', $workOrder), ['part_id' => $part->id, 'qty' => '2', 'unit_price' => 45_000])->assertSessionHasNoErrors();
        $this->put(route('work-orders.tasks.update', [$workOrder, $task]), ['is_done' => true])->assertSessionHasNoErrors();
        $this->put(route('work-orders.status', $workOrder), ['status' => 'in_progress'])->assertSessionHasNoErrors();
        $this->put(route('work-orders.status', $workOrder), ['status' => 'completed'])->assertSessionHasNoErrors();

        $this->get(route('work-orders.show', $workOrder))->assertInertia(fn (Assert $page) => $page
            ->component('WorkOrders/Show')
            ->where('workOrder.status', 'completed')
            ->where('workOrder.tasks.0.is_done', true)
            ->where('workOrder.estimated_total_label', 'Rp 150.000')
            ->where('nextStatuses.0.value', 'in_progress'));
    });

    it('rejects decimals for counted parts and quantities above the stock', function () {
        $shop = provisionTenant();
        $part = stockedPart($shop, 300);
        $workOrder = workOrderIn($shop);

        $this->actingAs($shop->owner)->post(route('work-orders.parts.store', $workOrder), ['part_id' => $part->id, 'qty' => '1,5', 'unit_price' => 1])
            ->assertSessionHasErrors('qty');
        $this->actingAs($shop->owner)->post(route('work-orders.parts.store', $workOrder), ['part_id' => $part->id, 'qty' => '5', 'unit_price' => 1])
            ->assertSessionHasErrors('business');
    });

    it('needs work_order.update_closed to edit a closed work order', function () {
        $shop = provisionTenant();
        $workOrder = workOrderIn($shop);
        $workOrder->forceFill(['status' => WorkOrderStatus::Closed])->saveQuietly();
        $data = ['complaint' => 'Rem bunyi', 'diagnosis' => 'Kampas habis'];

        $this->actingAs(userWithRole($shop, RoleName::Finance))->put(route('work-orders.update', $workOrder), $data)->assertForbidden();
        $this->actingAs(userWithRole($shop, RoleName::BranchManager))->put(route('work-orders.update', $workOrder), $data)->assertSessionHasNoErrors();

        expect($workOrder->fresh()?->diagnosis)->toBe('Kampas habis');
    });

    it('keeps work orders away from roles without access', function () {
        $shop = provisionTenant();
        $workOrder = workOrderIn($shop);
        $warehouse = userWithRole($shop, RoleName::Warehouse);

        $this->actingAs($warehouse)->get(route('work-orders.index'))->assertForbidden();
        $this->actingAs($warehouse)->get(route('work-orders.show', $workOrder))->assertForbidden();
        $this->actingAs($warehouse)->get(route('work-orders.print', $workOrder))->assertForbidden();
    });

    it('only reaches a line through its own work order, branch and tenant', function () {
        $shop = provisionTenant();
        $first = workOrderIn($shop);
        $second = workOrderIn($shop);
        $line = workOrders($shop, fn (WorkOrderService $service) => $service->addService($first, ['description' => 'x', 'qty' => 1, 'unit_price' => 1]));

        // Another work order's URL with this line: not found.
        $this->actingAs($shop->owner)->delete(route('work-orders.services.destroy', [$second, $line]))->assertNotFound();

        // A work order of another branch: not found, even for the same tenant.
        $east = addBranch($shop);
        $eastOrder = inTenant($shop->tenant, $east, fn () => app(WorkOrderService::class)->checkIn(vehicleFor($shop, customerIn($shop)), ['complaint' => 'x']));
        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('work-orders.show', $eastOrder))->assertNotFound();

        // Another tenant: not found.
        $this->actingAs(provisionTenant('Bengkel Lain')->owner)->get(route('work-orders.show', $first))->assertNotFound();

        expect(inTenant($shop->tenant, $shop->branch, fn () => WorkOrderServiceLine::query()->count()))->toBe(1);
    });

    it('prints the job sheet without prices', function () {
        $shop = provisionTenant();
        $workOrder = workOrderIn($shop);

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('work-orders.print', $workOrder))
            ->assertInertia(fn (Assert $page) => $page->component('WorkOrders/Print')->where('workOrder.number', $workOrder->number));
    });

    it('shows the service history of a vehicle across branches, linking only this branch', function () {
        $shop = provisionTenant();
        $vehicle = vehicleFor($shop, customerIn($shop));
        $here = workOrderIn($shop, $vehicle);
        $east = addBranch($shop);
        inTenant($shop->tenant, $east, fn () => app(WorkOrderService::class)->checkIn($vehicle, ['complaint' => 'Di cabang lain']));

        $this->actingAs($shop->owner)->get(route('vehicles.show', $vehicle))
            ->assertInertia(fn (Assert $page) => $page
                ->has('history', 2)
                ->where('history.0.complaint', 'Di cabang lain')
                ->where('history.0.in_branch', false)
                ->where('history.1.number', $here->number)
                ->where('history.1.in_branch', true));
    });

    it('lists open work orders with counts per status', function () {
        $shop = provisionTenant();
        workOrderIn($shop);
        $done = workOrderIn($shop);
        $done->forceFill(['status' => WorkOrderStatus::Completed])->saveQuietly();

        $this->actingAs(userWithRole($shop, RoleName::FrontDesk))->get(route('work-orders.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('workOrders.data', 1)
                ->where('counts.queued', 1)
                ->where('counts.completed', 1));
    });
});

it('keeps work orders and their lines isolated per tenant', function () {
    assertTenantIsolated(WorkOrder::class, fn (ProvisionedTenant $shop) => workOrderIn($shop));
    assertTenantIsolated(WorkOrderTask::class, fn (ProvisionedTenant $shop) => app(WorkOrderService::class)->addTask(workOrderIn($shop), 'Cek'));
    assertTenantIsolated(WorkOrderServiceLine::class, fn (ProvisionedTenant $shop) => app(WorkOrderService::class)->addService(workOrderIn($shop), ['description' => 'x', 'qty' => 1, 'unit_price' => 1]));
    assertTenantIsolated(WorkOrderPart::class, fn (ProvisionedTenant $shop) => app(WorkOrderService::class)->addPart(workOrderIn($shop), stockedPart($shop), 100, 1));
});

it('never lets a work order line belong to another work order than the one in the URL', function () {
    $shop = provisionTenant();
    $first = workOrderIn($shop);
    $second = workOrderIn($shop);
    $task = workOrders($shop, fn (WorkOrderService $service) => $service->addTask($first, 'Cek'));

    expect(fn () => workOrders($shop, fn (WorkOrderService $service) => $service->removeTask($second, $task)))->toThrow(BusinessRuleViolation::class)
        ->and(DB::table('work_order_tasks')->where('id', $task->id)->exists())->toBeTrue();
});
