<?php

namespace App\Http\Controllers\WorkOrders;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Which mechanics work on the car.
 */
class WorkOrderMechanicController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrders) {}

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        Gate::authorize('assign', $workOrder);

        $validated = $request->validate([
            'mechanic_ids' => ['present', 'array', 'max:10'],
            'mechanic_ids.*' => ['integer', 'distinct'],
        ]);

        $this->workOrders->assignMechanics($workOrder, array_values(array_map('intval', $validated['mechanic_ids'])));

        return back()->with('success', __('work_orders.mechanics_saved'));
    }
}
