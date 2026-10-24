<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrders\ServiceLineRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderServiceLine;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Jobs (services) on a work order.
 */
class WorkOrderServiceLineController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function store(ServiceLineRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->workOrders->addService($workOrder, $request->lineData());

        return back()->with('success', __('work_orders.service_saved'));
    }

    public function update(ServiceLineRequest $request, WorkOrder $workOrder, WorkOrderServiceLine $service): RedirectResponse
    {
        $this->workOrders->updateService($workOrder, $service, $request->lineData());

        return back()->with('success', __('work_orders.service_saved'));
    }

    public function destroy(WorkOrder $workOrder, WorkOrderServiceLine $service): RedirectResponse
    {
        Gate::authorize('editLines', $workOrder);

        $this->workOrders->removeService($workOrder, $service);

        return back()->with('success', __('work_orders.service_removed'));
    }
}
