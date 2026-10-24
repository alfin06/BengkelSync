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
    /** Not fully paid and past its due date (branch time) */
    is_overdue: boolean;
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

/** A payment method offered in the "Bayar" dialog */
export interface PaymentMethodOption {
    id: number;
    name: string;
    type: 'cash' | 'transfer' | 'qris' | 'debit' | 'credit_card' | 'other';
    /** e.g. "3.00" */
    surcharge_percent: string;
    needs_reference: boolean;
}

export interface ReceiptLine {
    id: number;
    method: string;
    amount_label: string;
    surcharge_label: string | null;
    reference: string | null;
    voided: boolean;
    void_reason: string | null;
    voided_by: string | null;
    voided_at_label: string | null;
}

/** One checkout (kwitansi) of an invoice */
export interface Receipt {
    id: number;
    number: string;
    paid_on: string;
    /** Still counted: cancelled lines excluded */
    amount_label: string;
    received_by: string | null;
    fully_voided: boolean;
    lines: ReceiptLine[];
}
