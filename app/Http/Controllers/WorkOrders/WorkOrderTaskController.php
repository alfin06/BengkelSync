<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderTask;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The checklist of a work order: add, tick and remove items.
 */
class WorkOrderTaskController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('editLines', $workOrder);

        $validated = $request->validate(['description' => ['required', 'string', 'max:255']]);
        $this->workOrders->addTask($workOrder, $validated['description']);

        return back();
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderTask $task): RedirectResponse
    {
        Gate::authorize('editLines', $workOrder);

        $validated = $request->validate(['is_done' => ['required', 'boolean']]);
        $this->workOrders->setTaskDone($workOrder, $task, (bool) $validated['is_done']);

        return back();
    }

    public function destroy(WorkOrder $workOrder, WorkOrderTask $task): RedirectResponse
    {
        Gate::authorize('editLines', $workOrder);

        $this->workOrders->removeTask($workOrder, $task);

        return back();
    }
}
