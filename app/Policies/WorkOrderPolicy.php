<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\WorkOrderStatus;
use App\Models\User;
use App\Models\WorkOrder;

/**
 * Tenant and branch isolation come from the WorkOrder global scopes (a work order of
 * another branch is never found); this policy checks permissions and the status.
 */
class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::WorkOrderView->value);
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrderView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WorkOrderCreate->value);
    }

    /**
     * Complaint, diagnosis, odometer and notes. A closed work order needs
     * work_order.update_closed (SPEC section 6).
     */
    public function update(User $user, WorkOrder $workOrder): bool
    {
        if (! $user->can(Permission::WorkOrderUpdate->value) || $workOrder->status === WorkOrderStatus::Cancelled) {
            return false;
        }

        return $workOrder->status !== WorkOrderStatus::Closed || $user->can(Permission::WorkOrderUpdateClosed->value);
    }

    /**
     * Services, parts and the checklist; only before the invoice is made.
     */
    public function editLines(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrderUpdate->value) && $workOrder->status->isEditable();
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrderAssign->value) && $workOrder->status->isEditable();
    }

    public function changeStatus(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrderChangeStatus->value) && $workOrder->status->nextManual() !== [];
    }

    public function cancel(User $user, WorkOrder $workOrder): bool
    {
        return $user->can(Permission::WorkOrderChangeStatus->value) && $workOrder->status->isCancellable();
    }
}
