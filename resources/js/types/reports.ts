/** App\Services\Reports\DailySummaryService::for() */
export interface DailySummary {
    date: string;
    sales: {
        count: number;
        sales_label: string;
        discount_label: string;
        tax_label: string | null;
        total_label: string;
        unpaid_count: number;
        unpaid_label: string;
        invoices: { id: number; number: string; customer: string | null; total_label: string; status: string; status_label: string }[];
    };
    received: {
        total_label: string;
        surcharge_label: string;
        methods: { name: string; count: number; amount_label: string; surcharge_label: string | null }[];
    };
    expenses: {
        total_label: string;
        includes_restricted: boolean;
        items: { category: string; description: string; account: string; amount_label: string }[];
    };
    supplier_payments: {
        total_label: string;
        items: { supplier: string; purchase: string; account: string; amount_label: string }[];
    };
    transfers: { from: string; to: string; amount_label: string; note: string | null }[];
    cash: { name: string; is_physical: boolean; opening_label: string; in_label: string; out_label: string; closing_label: string }[];
    workshop: { checked_in: number; completed: number };
    cancellations: { kind: 'invoice' | 'payment'; number: string; amount_label: string; reason: string | null }[];
}
