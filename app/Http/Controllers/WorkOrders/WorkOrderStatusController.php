<?php

namespace App\Http\Controllers\WorkOrders;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrders\WorkOrderReasonRequest;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class WorkOrderStatusController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('changeStatus', $workOrder);

        $validated = $request->validate(['status' => ['required', Rule::enum(WorkOrderStatus::class)]]);
        $status = WorkOrderStatus::from($validated['status']);

        $this->workOrders->changeStatus($workOrder, $status);

        return back()->with('success', __('work_orders.status_changed', ['status' => $status->label()]));
    }

    public function cancel(WorkOrderReasonRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->workOrders->cancel($workOrder, $request->string('reason')->toString());

        return back()->with('success', __('work_orders.cancelled', ['number' => $workOrder->number]));
    }
}
