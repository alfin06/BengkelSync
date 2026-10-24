<?php

namespace App\Http\Controllers\WorkOrders;

use App\Enums\InvoiceStatus;
use App\Enums\Permission;
use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrders\CheckInRequest;
use App\Http\Requests\WorkOrders\UpdateWorkOrderRequest;
use App\Http\Resources\WorkOrderResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Mechanic;
use App\Models\ServiceItem;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Customers\VehicleService;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WorkOrderController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', WorkOrder::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(WorkOrderService::FILTERS)],
        ]);
        $status = $filters['status'] ?? 'open';

        return Inertia::render('WorkOrders/Index', [
            'workOrders' => $this->workOrders
                ->search($filters['search'] ?? null, $status)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (WorkOrder $workOrder) => WorkOrderResource::make($workOrder)->resolve($request)),
            'filters' => ['search' => $filters['search'] ?? '', 'status' => $status],
            'counts' => $this->workOrders->counts(),
            'can' => ['create' => $request->user()?->can('create', WorkOrder::class) ?? false],
        ]);
    }

    public function create(Request $request, VehicleService $vehicles): Response
    {
        Gate::authorize('create', WorkOrder::class);

        /** @var User $user */
        $user = $request->user();
        // "Buat SPK" from a vehicle page starts with that vehicle chosen.
        $vehicle = $request->filled('vehicle') ? Vehicle::query()->with('customer')->find($request->integer('vehicle')) : null;

        return Inertia::render('WorkOrders/Create', [
            'vehicle' => $vehicle === null ? null : $this->vehicleOption($vehicle),
            'mechanics' => $this->activeMechanics(),
            'suggestions' => $vehicles->suggestions(),
            'can' => [
                'createVehicle' => $user->can('create', Vehicle::class),
                'createCustomer' => $user->can('create', Customer::class),
                'assign' => $user->can(Permission::WorkOrderAssign->value),
            ],
        ]);
    }

    public function store(CheckInRequest $request): RedirectResponse
    {
        $mechanicIds = $request->mechanicIds();

        $workOrder = $request->input('mode') === 'new'
            ? $this->workOrders->checkInNewVehicle(
                $request->customer(),
                $request->filled('customer_id') ? null : $request->customerData(),
                $request->vehicleData(),
                $request->workOrderData(),
                $request->tasks(),
                $mechanicIds,
                $request->boolean('draft'),
            )
            : $this->workOrders->checkIn(
                $request->vehicle() ?? abort(404),
                $request->workOrderData(),
                $request->tasks(),
                $mechanicIds,
                $request->boolean('draft'),
            );

        return redirect()->route('work-orders.show', $workOrder)->with('success', __('work_orders.created', ['number' => $workOrder->number]));
    }

    public function show(Request $request, WorkOrder $workOrder): Response
    {
        Gate::authorize('view', $workOrder);

        /** @var User $user */
        $user = $request->user();
        $workOrder->load(['vehicle', 'customer', 'tasks', 'mechanics', 'services.mechanic', 'parts.part']);
        $canEditLines = $user->can('editLines', $workOrder);

        return Inertia::render('WorkOrders/Show', [
            'workOrder' => WorkOrderResource::make($workOrder)->resolve($request),
            'nextStatuses' => $user->can('changeStatus', $workOrder)
                ? array_map(fn (WorkOrderStatus $status) => ['value' => $status->value, 'label' => $status->actionLabel()], $workOrder->status->nextManual())
                : [],
            // Only what the forms need: active catalog jobs and the branch's active mechanics.
            'catalog' => $canEditLines
                ? ServiceItem::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'default_price'])
                : [],
            'mechanics' => $canEditLines || $user->can('assign', $workOrder) ? $this->activeMechanics() : [],
            // The invoice that is not cancelled, once the work order is invoiced.
            'invoice' => $workOrder->invoices()->where('status', '!=', InvoiceStatus::Void)->first(['id', 'number'])?->only(['id', 'number']),
            'can' => [
                'invoice' => $workOrder->status === WorkOrderStatus::Completed && $user->can('create', Invoice::class),
                'update' => $user->can('update', $workOrder),
                'editLines' => $canEditLines,
                'assign' => $user->can('assign', $workOrder),
                'cancel' => $user->can('cancel', $workOrder),
            ],
        ]);
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->workOrders->update($workOrder, $request->workOrderData());

        return back()->with('success', __('work_orders.saved'));
    }

    /**
     * The job sheet for the mechanic's bay: no prices.
     */
    public function print(Request $request, WorkOrder $workOrder): Response
    {
        Gate::authorize('view', $workOrder);

        $workOrder->load(['vehicle', 'customer', 'tasks', 'mechanics', 'services.mechanic', 'parts.part', 'branch']);

        return Inertia::render('WorkOrders/Print', [
            'workOrder' => WorkOrderResource::make($workOrder)->resolve($request),
            'branchName' => $workOrder->branch->name,
            'logoUrl' => $workOrder->branch->logoUrl(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function vehicleOption(Vehicle $vehicle): array
    {
        return [
            'id' => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'name' => $vehicle->displayName(),
            'last_odometer' => $vehicle->last_odometer,
            'customer_name' => $vehicle->customer->name,
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function activeMechanics(): array
    {
        return Mechanic::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (Mechanic $mechanic) => ['id' => $mechanic->id, 'name' => $mechanic->name])
            ->all();
    }
}
