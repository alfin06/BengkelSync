/** App\Http\Resources\PartResource */
export interface Part {
    id: number;
    sku: string | null;
    name: string;
    brand: string | null;
    category: string | null;
    unit: string;
    unit_label: string;
    allows_decimals: boolean;
    /** null when the user may not see purchase costs */
    cost_price: number | null;
    cost_label: string | null;
    sell_price: number;
    price_label: string;
    is_active: boolean;
    notes: string | null;
    stock: {
        /** Plain value for inputs, e.g. "4.2" */
        qty: string;
        /** Display, e.g. "4,2" */
        qty_label: string;
        min_stock: string;
        min_label: string;
        location: string | null;
        is_low: boolean;
        is_empty: boolean;
    };
}

/** App\Http\Resources\StockMovementResource */
export interface StockMovement {
    id: number;
    type: string;
    type_label: string;
    /** Signed hundredths */
    qty: number;
    qty_label: string;
    qty_after_label: string;
    note: string | null;
    user: string | null;
    created_at_label: string;
}

export interface UnitOption {
    value: string;
    label: string;
    allows_decimals: boolean;
}

/** App\Http\Resources\SupplierResource */
export interface Supplier {
    id: number;
    name: string;
    phone: string | null;
    phone_e164: string | null;
    whatsapp: string | null;
    whatsapp_link: string | null;
    sales_name: string | null;
    email: string | null;
    address: string | null;
    npwp: string | null;
    notes: string | null;
}

export interface AppNotification {
    id: string;
    title: string;
    body: string;
    url: string;
    read: boolean;
    created_at_label: string;
}
