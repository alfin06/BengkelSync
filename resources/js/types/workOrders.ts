export type WorkOrderStatus = 'draft' | 'queued' | 'in_progress' | 'waiting_parts' | 'completed' | 'invoiced' | 'closed' | 'cancelled';

export interface WorkOrderTask {
    id: number;
    description: string;
    is_done: boolean;
}

export interface WorkOrderServiceLine {
    id: number;
    service_item_id: number | null;
    description: string;
    qty: number;
    unit_price: number;
    unit_price_label: string;
    line_total_label: string;
    mechanic_id: number | null;
    mechanic_name: string | null;
}

export interface WorkOrderPartLine {
    id: number;
    part_id: number;
    part_name: string;
    sku: string | null;
    unit_label: string;
    allows_decimals: boolean;
    /** Plain value for inputs, e.g. "4.2" */
    qty: string;
    /** Display, e.g. "4,2" */
    qty_label: string;
    unit_price: number;
    unit_price_label: string;
    line_total_label: string;
}

/** App\Http\Resources\WorkOrderResource */
export interface WorkOrder {
    id: number;
    number: string;
    status: WorkOrderStatus;
    status_label: string;
    complaint: string;
    diagnosis: string | null;
    odometer_in: number | null;
    /** datetime-local value in branch time, e.g. "2026-10-24T15:30" */
    estimated_completion: string | null;
    estimated_completion_label: string | null;
    started_at_label: string | null;
    completed_at_label: string | null;
    created_at_label: string;
    cancel_reason: string | null;
    notes: string | null;
    vehicle: { id: number; plate_number: string; name: string; last_odometer: number | null };
    customer: { id: number; name: string; phone: string | null };
    tasks?: WorkOrderTask[];
    mechanics?: MechanicOption[];
    services?: WorkOrderServiceLine[];
    parts?: WorkOrderPartLine[];
    estimated_total_label?: string;
}

export interface MechanicOption {
    id: number;
    name: string;
}

export interface CatalogOption {
    id: number;
    name: string;
    default_price: number;
}

/** App\Http\Controllers\Customers\VehicleLookupController */
export interface VehicleOption {
    id: number;
    plate_number: string;
    name: string;
    last_odometer: number | null;
    customer_name: string;
    /** Number of an unfinished work order of this vehicle in the branch */
    open_work_order?: string | null;
}

/** A row of the vehicle's service history */
export interface ServiceHistoryRow {
    id: number;
    number: string;
    date_label: string;
    complaint: string;
    odometer_in: number | null;
    status: WorkOrderStatus;
    status_label: string;
    branch_name: string;
    /** Can be opened from the active branch */
    in_branch: boolean;
}
