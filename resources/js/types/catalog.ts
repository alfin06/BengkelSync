/** App\Http\Resources\ServiceItemResource */
export interface ServiceItem {
    id: number;
    code: string | null;
    name: string;
    category: string | null;
    /** Integer Rupiah */
    default_price: number;
    /** Formatted on the server, e.g. "Rp 50.000" */
    price_label: string;
    estimated_minutes: number | null;
    is_active: boolean;
}
