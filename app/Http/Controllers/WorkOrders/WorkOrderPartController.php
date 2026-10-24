<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrders\PartLineRequest;
use App\Models\Part;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Parts on a work order. Adding takes them from the branch stock; removing puts them back.
 */
class WorkOrderPartController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function store(PartLineRequest $request, WorkOrder $workOrder): RedirectResponse
    {
        /** @var Part $part */
        $part = $request->part();
        $this->workOrders->addPart($workOrder, $part, $request->qtyHundredths(), $request->integer('unit_price'));

        return back()->with('success', __('work_orders.part_added', ['part' => $part->name]));
    }

    public function update(PartLineRequest $request, WorkOrder $workOrder, WorkOrderPart $part): RedirectResponse
    {
        $this->workOrders->updatePart($workOrder, $part, $request->qtyHundredths(), $request->integer('unit_price'));

        return back()->with('success', __('work_orders.part_saved'));
    }

    public function destroy(WorkOrder $workOrder, WorkOrderPart $part): RedirectResponse
    {
        Gate::authorize('editLines', $workOrder);

        $this->workOrders->removePart($workOrder, $part);

        return back()->with('success', __('work_orders.part_removed'));
    }
}
