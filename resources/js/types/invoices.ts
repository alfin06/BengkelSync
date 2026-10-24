export type InvoiceStatus = 'unpaid' | 'partial' | 'paid' | 'void';

export interface InvoiceLine {
    id: number;
    type: 'service' | 'part' | 'other';
    description: string;
    qty_label: string;
    unit_label: string | null;
    unit_price_label: string;
    discount_amount: number;
    discount_label: string;
    line_total_label: string;
}

/** App\Http\Resources\InvoiceResource */
export interface Invoice {
    id: number;
    number: string;
    issue_date: string;
    due_date: string | null;
    /** null = "Pelanggan umum" */
    customer: { id: number; name: string; phone: string | null; address: string | null } | null;
    work_order: { id: number; number: string; odometer_in: number | null } | null;
    vehicle: { id: number; plate_number: string; name: string } | null;
    subtotal_label: string;
    discount_amount: number;
    discount_label: string;
    tax_enabled: boolean;
    tax_name: string;
    tax_rate_label: string;
    tax_inclusive: boolean;
    tax_label: string;
    total: number;
    total_label: string;
    amount_paid_label: string;
    outstanding: number;
    outstanding_label: string;
    status: InvoiceStatus;
    status_label: string;
    void_reason: string | null;
    revision_no: number;
    notes: string | null;
    created_at_label: string;
    lines?: InvoiceLine[];
}

/** The branch tax setting new invoices start from */
export interface TaxSetting {
    enabled: boolean;
    name: string;
    /** e.g. "11.00" */
    rate: string;
    inclusive: boolean;
}

/** A work order line as the invoice form prices it */
export interface WorkOrderInvoiceLine {
    source: 'service' | 'part';
    id: number;
    description: string;
    qty: string;
    qty_label: string;
    unit_label: string | null;
    unit_price: number;
}

/** A line of a walk-in sale being typed */
export interface WalkInLine {
    key: string;
    type: 'service' | 'part' | 'other';
    part_id: number | null;
    service_item_id: number | null;
    description: string;
    unit_label: string | null;
    allows_decimals: boolean;
    /** Hundredths on the shelf (parts) */
    available: number | null;
    qty: string;
    unit_price: number | null;
    discount_amount: number | null;
}
