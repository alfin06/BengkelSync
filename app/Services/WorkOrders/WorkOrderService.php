<?php

namespace App\Services\WorkOrders;

use App\Enums\DocumentType;
use App\Enums\StockMovementType;
use App\Enums\WorkOrderStatus;
use App\Exceptions\BusinessRuleViolation;
use App\Models\Customer;
use App\Models\Mechanic;
use App\Models\Part;
use App\Models\ServiceItem;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderServiceLine;
use App\Models\WorkOrderTask;
use App\Services\Audit\AuditLogger;
use App\Services\Customers\CustomerService;
use App\Services\Customers\VehicleService;
use App\Services\Inventory\StockService;
use App\Support\DocumentNumber;
use App\Support\Quantity;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Work orders ("SPK"): check-in, status flow, checklist, mechanics, and the services and
 * parts that will go on the invoice. Parts leave the branch stock as soon as they are
 * added (pilot decision) and go back when removed or when the work order is cancelled.
 */
class WorkOrderService
{
    /** Filters of the list; "open" = still in the workshop. */
    public const FILTERS = ['open', 'draft', 'queued', 'in_progress', 'waiting_parts', 'completed', 'invoiced', 'closed', 'cancelled', 'all'];

    public function __construct(
        private readonly TenantContext $context,
        private readonly StockService $stock,
        private readonly CustomerService $customers,
        private readonly VehicleService $vehicles,
        private readonly AuditLogger $audit,
        private readonly AuthFactory $auth,
    ) {}

    /**
     * @return Builder<WorkOrder>
     */
    public function search(?string $term, string $filter = 'open'): Builder
    {
        $term = trim((string) $term);

        return WorkOrder::query()
            ->with(['vehicle', 'customer'])
            ->when($filter === 'open', fn (Builder $query) => $query->whereIn('status', $this->openStatuses()))
            ->when(! in_array($filter, ['open', 'all'], true), fn (Builder $query) => $query->where('status', $filter))
            ->when($term !== '', fn (Builder $query) => $query->where(fn (Builder $where) => $where
                ->where('number', 'like', '%'.$term.'%')
                ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('plate_number', 'like', '%'.$this->plateTerm($term).'%'))
                ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', '%'.$term.'%'))))
            ->latest('id');
    }

    /**
     * How many work orders of the active branch are in each status (for the filter chips).
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        /** @var array<string, int> $counts */
        $counts = WorkOrder::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total)
            ->all();

        return $counts;
    }

    /**
     * Check in a known vehicle.
     *
     * @param  array{complaint: string, odometer_in?: int|null, estimated_completion?: string|null, notes?: string|null}  $data
     * @param  list<string>  $tasks
     * @param  list<int>  $mechanicIds
     */
    public function checkIn(Vehicle $vehicle, array $data, array $tasks = [], array $mechanicIds = [], bool $draft = false): WorkOrder
    {
        return DB::transaction(function () use ($vehicle, $data, $tasks, $mechanicIds, $draft): WorkOrder {
            $branch = $this->context->requireBranch();

            $workOrder = new WorkOrder([
                'complaint' => trim($data['complaint']),
                'odometer_in' => $data['odometer_in'] ?? null,
                'estimated_completion' => $data['estimated_completion'] ?? null,
                'notes' => $this->nullIfBlank($data['notes'] ?? null),
            ]);
            $workOrder->forceFill([
                'number' => DocumentNumber::next($branch, DocumentType::WorkOrder),
                'vehicle_id' => $vehicle->id,
                'customer_id' => $vehicle->customer_id,
                'status' => $draft ? WorkOrderStatus::Draft : WorkOrderStatus::Queued,
                'created_by' => $this->userId(),
            ])->save();

            foreach (array_values(array_filter(array_map('trim', $tasks), fn (string $task) => $task !== '')) as $sort => $description) {
                WorkOrderTask::query()->create(['work_order_id' => $workOrder->id, 'description' => $description, 'sort' => $sort]);
            }

            if ($mechanicIds !== []) {
                $workOrder->mechanics()->sync($this->activeMechanicIds($mechanicIds));
            }

            $this->recordOdometer($vehicle, $workOrder->odometer_in);

            return $workOrder;
        });
    }

    /**
     * Check in a vehicle that is not in the system yet, for a known or a new customer,
     * all in one step.
     *
     * @param  array<string, mixed>|null  $customerData  validated customer fields when the customer is new
     * @param  array<string, mixed>  $vehicleData  validated vehicle fields
     * @param  array{complaint: string, odometer_in?: int|null, estimated_completion?: string|null, notes?: string|null}  $data
     * @param  list<string>  $tasks
     * @param  list<int>  $mechanicIds
     */
    public function checkInNewVehicle(
        ?Customer $owner,
        ?array $customerData,
        array $vehicleData,
        array $data,
        array $tasks = [],
        array $mechanicIds = [],
        bool $draft = false,
    ): WorkOrder {
        return DB::transaction(function () use ($owner, $customerData, $vehicleData, $data, $tasks, $mechanicIds, $draft): WorkOrder {
            $owner ??= $this->customers->create($customerData ?? throw new BusinessRuleViolation(__('work_orders.customer_required')));
            $vehicle = $this->vehicles->create($owner, [...$vehicleData, 'last_odometer' => $data['odometer_in'] ?? null]);

            return $this->checkIn($vehicle, $data, $tasks, $mechanicIds, $draft);
        });
    }

    /**
     * Complaint, diagnosis, odometer, estimate and notes.
     *
     * @param  array{complaint: string, diagnosis?: string|null, odometer_in?: int|null, estimated_completion?: string|null, notes?: string|null}  $data
     */
    public function update(WorkOrder $workOrder, array $data): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $data): WorkOrder {
            $locked = $this->lock($workOrder);

            if ($locked->status === WorkOrderStatus::Cancelled) {
                throw new BusinessRuleViolation(__('work_orders.is_cancelled'));
            }

            $locked->fill([
                'complaint' => trim($data['complaint']),
                'diagnosis' => $this->nullIfBlank($data['diagnosis'] ?? null),
                'odometer_in' => $data['odometer_in'] ?? null,
                'estimated_completion' => $data['estimated_completion'] ?? null,
                'notes' => $this->nullIfBlank($data['notes'] ?? null),
            ])->save();

            $this->recordOdometer($locked->vehicle, $locked->odometer_in);

            return $locked;
        });
    }

    public function changeStatus(WorkOrder $workOrder, WorkOrderStatus $next): WorkOrder
    {
        return DB::transaction(function () use ($workOrder, $next): WorkOrder {
            $locked = $this->lock($workOrder);

            if (! $locked->status->canMoveTo($next)) {
                throw new BusinessRuleViolation(__('work_orders.invalid_transition', [
                    'from' => $locked->status->label(),
                    'to' => $next->label(),
                ]));
            }

            $changes = ['status' => $next];

            if (in_array($next, [WorkOrderStatus::InProgress, WorkOrderStatus::Completed], true) && $locked->started_at === null) {
                $changes['started_at'] = now();
            }

            $changes['completed_at'] = $next === WorkOrderStatus::Completed ? now() : null;

            $locked->forceFill($changes)->save();
            $workOrder->setRawAttributes($locked->getAttributes(), true);

            return $locked;
        });
    }

    /**
     * Cancel before invoicing: every part goes back on the shelf.
     */
    public function cancel(WorkOrder $workOrder, string $reason): void
    {
        if (trim($reason) === '') {
            throw new BusinessRuleViolation(__('cash.reason_required'));
        }

        DB::transaction(function () use ($workOrder, $reason): void {
            $locked = $this->lock($workOrder);

            if (! $locked->status->isCancellable()) {
                throw new BusinessRuleViolation(__('work_orders.not_editable'));
            }

            foreach ($locked->parts()->with('part')->get() as $line) {
                $this->stock->move($line->part, StockMovementType::WorkOrderUse, $line->qtyHundredths(), null, $locked, __('work_orders.cancel_note', ['number' => $locked->number]));
            }

            $locked->forceFill(['status' => WorkOrderStatus::Cancelled, 'cancel_reason' => trim($reason)])->save();
            $this->audit->record('work_order_cancelled', $locked, reason: trim($reason));
        });

        $workOrder->refresh();
    }

    public function addTask(WorkOrder $workOrder, string $description): WorkOrderTask
    {
        return DB::transaction(function () use ($workOrder, $description): WorkOrderTask {
            $locked = $this->lockEditable($workOrder);

            return WorkOrderTask::query()->create([
                'work_order_id' => $locked->id,
                'description' => trim($description),
                'sort' => (int) $locked->tasks()->max('sort') + 1,
            ]);
        });
    }

    public function setTaskDone(WorkOrder $workOrder, WorkOrderTask $task, bool $done): void
    {
        $this->ensureLineOf($workOrder, $task->work_order_id);

        DB::transaction(function () use ($workOrder, $task, $done): void {
            $this->lockEditable($workOrder);
            $task->forceFill(['is_done' => $done])->save();
        });
    }

    public function removeTask(WorkOrder $workOrder, WorkOrderTask $task): void
    {
        $this->ensureLineOf($workOrder, $task->work_order_id);

        DB::transaction(function () use ($workOrder, $task): void {
            $this->lockEditable($workOrder);
            $task->delete();
        });
    }

    /**
     * Mechanics working on the car. Newly added ones must be active mechanics of this
     * branch; ones already on the job may stay even if they were deactivated since.
     *
     * @param  list<int>  $mechanicIds
     */
    public function assignMechanics(WorkOrder $workOrder, array $mechanicIds): void
    {
        DB::transaction(function () use ($workOrder, $mechanicIds): void {
            $locked = $this->lockEditable($workOrder);
            $current = $locked->mechanics()->pluck('mechanics.id')->map(fn ($id) => (int) $id)->all();
            $added = array_values(array_diff($mechanicIds, $current));
            $kept = array_values(array_intersect($mechanicIds, $current));
            $new = [...$kept, ...$this->activeMechanicIds($added)];

            sort($current);
            sort($new);
            if ($current === $new) {
                return;
            }

            $locked->mechanics()->sync($new);
            $this->audit->record('work_order_mechanics', $locked, ['mechanic_ids' => $current], ['mechanic_ids' => $new]);
        });
    }

    /**
     * @param  array{service_item_id?: int|null, description: string, qty: int, unit_price: int, mechanic_id?: int|null}  $data
     */
    public function addService(WorkOrder $workOrder, array $data): WorkOrderServiceLine
    {
        return DB::transaction(function () use ($workOrder, $data): WorkOrderServiceLine {
            $locked = $this->lockEditable($workOrder);
            $itemId = $data['service_item_id'] ?? null;

            if ($itemId !== null && ! ServiceItem::query()->whereKey($itemId)->where('is_active', true)->exists()) {
                throw new BusinessRuleViolation(__('work_orders.service_unavailable'));
            }

            $mechanicId = $this->serviceMechanic($locked, $data['mechanic_id'] ?? null, null);

            return WorkOrderServiceLine::query()->create([
                'work_order_id' => $locked->id,
                'service_item_id' => $itemId,
                'description' => trim($data['description']),
                'qty' => $data['qty'],
                'unit_price' => $data['unit_price'],
                'line_total' => $data['qty'] * $data['unit_price'],
                'mechanic_id' => $mechanicId,
                'sort' => (int) $locked->services()->max('sort') + 1,
            ]);
        });
    }

    /**
     * @param  array{description: string, qty: int, unit_price: int, mechanic_id?: int|null}  $data
     */
    public function updateService(WorkOrder $workOrder, WorkOrderServiceLine $line, array $data): WorkOrderServiceLine
    {
        $this->ensureLineOf($workOrder, $line->work_order_id);

        return DB::transaction(function () use ($workOrder, $line, $data): WorkOrderServiceLine {
            $locked = $this->lockEditable($workOrder);

            $line->fill([
                'description' => trim($data['description']),
                'qty' => $data['qty'],
                'unit_price' => $data['unit_price'],
                'line_total' => $data['qty'] * $data['unit_price'],
                'mechanic_id' => $this->serviceMechanic($locked, $data['mechanic_id'] ?? null, $line->mechanic_id),
            ])->save();

            return $line;
        });
    }

    public function removeService(WorkOrder $workOrder, WorkOrderServiceLine $line): void
    {
        $this->ensureLineOf($workOrder, $line->work_order_id);

        DB::transaction(function () use ($workOrder, $line): void {
            $this->lockEditable($workOrder);
            $line->delete();
        });
    }

    /**
     * Take a part from the shelf for this job. Refused when the branch does not have it.
     *
     * @param  int  $qty  hundredths
     */
    public function addPart(WorkOrder $workOrder, Part $part, int $qty, int $unitPrice): WorkOrderPart
    {
        $this->ensurePositive($part, $qty);

        return DB::transaction(function () use ($workOrder, $part, $qty, $unitPrice): WorkOrderPart {
            $locked = $this->lockEditable($workOrder);

            if (! $part->is_active) {
                throw new BusinessRuleViolation(__('work_orders.part_inactive', ['part' => $part->name]));
            }

            $line = WorkOrderPart::query()->create([
                'work_order_id' => $locked->id,
                'part_id' => $part->id,
                'qty' => Quantity::toDecimal($qty),
                'unit_price' => $unitPrice,
                'unit_cost' => $part->cost_price,
                'line_total' => Quantity::priceOf($qty, $unitPrice),
                'sort' => (int) $locked->parts()->max('sort') + 1,
            ]);

            $this->stock->move($part, StockMovementType::WorkOrderUse, -$qty, $part->cost_price, $locked, $locked->number);

            return $line;
        });
    }

    /**
     * Change the quantity or price of a part line; the stock moves by the difference.
     *
     * @param  int  $qty  hundredths
     */
    public function updatePart(WorkOrder $workOrder, WorkOrderPart $line, int $qty, int $unitPrice): WorkOrderPart
    {
        $this->ensureLineOf($workOrder, $line->work_order_id);
        $part = $line->part;
        $this->ensurePositive($part, $qty);

        return DB::transaction(function () use ($workOrder, $line, $part, $qty, $unitPrice): WorkOrderPart {
            $locked = $this->lockEditable($workOrder);
            $delta = $qty - $line->qtyHundredths();

            if ($delta !== 0) {
                // More used: take it from the shelf. Less used: put it back.
                $this->stock->move($part, StockMovementType::WorkOrderUse, -$delta, $line->unit_cost, $locked, $locked->number);
            }

            $line->fill([
                'qty' => Quantity::toDecimal($qty),
                'unit_price' => $unitPrice,
                'line_total' => Quantity::priceOf($qty, $unitPrice),
            ])->save();

            return $line;
        });
    }

    /**
     * Remove a part from the job and put it back on the shelf.
     */
    public function removePart(WorkOrder $workOrder, WorkOrderPart $line): void
    {
        $this->ensureLineOf($workOrder, $line->work_order_id);

        DB::transaction(function () use ($workOrder, $line): void {
            $locked = $this->lockEditable($workOrder);
            $this->stock->move($line->part, StockMovementType::WorkOrderUse, $line->qtyHundredths(), $line->unit_cost, $locked, __('work_orders.return_note', ['number' => $locked->number]));
            $line->delete();
        });
    }

    /**
     * @return list<WorkOrderStatus>
     */
    private function openStatuses(): array
    {
        return array_values(array_filter(WorkOrderStatus::cases(), fn (WorkOrderStatus $status) => $status->isOpen()));
    }

    private function lock(WorkOrder $workOrder): WorkOrder
    {
        return WorkOrder::query()->with('vehicle')->whereKey($workOrder->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Lock the work order and check that its lines can still change (before the invoice).
     */
    private function lockEditable(WorkOrder $workOrder): WorkOrder
    {
        $locked = $this->lock($workOrder);

        if (! $locked->status->isEditable()) {
            throw new BusinessRuleViolation(__('work_orders.not_editable'));
        }

        return $locked;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    private function activeMechanicIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // Branch-scoped: mechanics of another branch are never found.
        $found = Mechanic::query()->whereKey($ids)->where('is_active', true)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (count($found) !== count(array_unique($ids))) {
            throw new BusinessRuleViolation(__('work_orders.mechanic_unavailable'));
        }

        return $found;
    }

    /**
     * The mechanic of a service line must be on the job; picking one adds them to it.
     */
    private function serviceMechanic(WorkOrder $workOrder, ?int $mechanicId, ?int $currentId): ?int
    {
        if ($mechanicId === null || $mechanicId === $currentId) {
            return $mechanicId;
        }

        $this->activeMechanicIds([$mechanicId]);
        $workOrder->mechanics()->syncWithoutDetaching([$mechanicId]);

        return $mechanicId;
    }

    private function ensureLineOf(WorkOrder $workOrder, int $workOrderId): void
    {
        if ($workOrder->id !== $workOrderId) {
            throw new BusinessRuleViolation(__('work_orders.not_editable'));
        }
    }

    private function ensurePositive(Part $part, int $qty): void
    {
        if ($qty <= 0) {
            throw new BusinessRuleViolation(__('stock.zero_change'));
        }

        $this->stock->ensureAllowedQuantity($part, $qty);
    }

    /**
     * The vehicle remembers the highest odometer reading seen; a lower reading (a typo or
     * a replaced dashboard) never lowers it.
     */
    private function recordOdometer(Vehicle $vehicle, ?int $odometer): void
    {
        if ($odometer !== null && ($vehicle->last_odometer === null || $odometer > $vehicle->last_odometer)) {
            $vehicle->forceFill(['last_odometer' => $odometer])->save();
        }
    }

    /**
     * Plates are stored as "B 1234 XYZ"; let "b1234xyz" find them too.
     */
    private function plateTerm(string $term): string
    {
        $upper = strtoupper(trim($term));

        return preg_replace('/\s+|(?<=[A-Z])(?=\d)|(?<=\d)(?=[A-Z])/', '%', $upper) ?? $upper;
    }

    private function nullIfBlank(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }

    private function userId(): ?int
    {
        $id = $this->auth->guard()->id();

        return is_int($id) ? $id : null;
    }
}
