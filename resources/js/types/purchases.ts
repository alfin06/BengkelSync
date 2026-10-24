/** A line of App\Http\Resources\PurchaseResource */
export interface PurchaseLine {
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
    unit_cost: number;
    unit_cost_label: string;
    line_total_label: string;
}

export interface SupplierPaymentRow {
    id: number;
    paid_on: string;
    amount_label: string;
    account_name: string;
    reference: string | null;
    note: string | null;
    payer: string | null;
    voided: boolean;
    void_reason: string | null;
    can_void: boolean;
}

/** App\Http\Resources\PurchaseResource */
export interface Purchase {
    id: number;
    number: string;
    supplier: { id: number; name: string };
    supplier_ref: string | null;
    purchase_date: string;
    due_date: string | null;
    is_overdue: boolean;
    total: number;
    total_label: string;
    amount_paid_label: string;
    outstanding: number;
    outstanding_label: string;
    status: 'unpaid' | 'partial' | 'paid' | 'void';
    status_label: string;
    void_reason: string | null;
    notes: string | null;
    lines?: PurchaseLine[];
    payments?: SupplierPaymentRow[];
}

/** An account the user may pay suppliers from */
export interface PayAccount {
    id: number;
    name: string;
    /** Cash drawer or petty cash */
    is_physical: boolean;
}

/** App\Http\Controllers\Inventory\PartLookupController */
export interface PartOption {
    id: number;
    name: string;
    sku: string | null;
    unit_label: string;
    allows_decimals: boolean;
    /** Current average cost; null when the user may not see costs */
    cost_price: number | null;
}

/** A line being edited in the purchase form */
export interface PurchaseLineInput {
    /** Stable key for v-for */
    key: string;
    part: PartOption;
    qty: string;
    unit_cost: number | null;
}
