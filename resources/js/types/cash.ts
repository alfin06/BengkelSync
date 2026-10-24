export interface CashAccountSummary {
    id: number;
    name: string;
    type: string;
    type_label: string;
    /** Drawer and petty cash: can never go below zero */
    is_physical: boolean;
    /** Integer Rupiah */
    balance: number;
    balance_label: string;
    can?: { transfer: boolean; adjust: boolean };
}

/** App\Http\Resources\CashMovementResource */
export interface CashMovement {
    id: number;
    type: string;
    type_label: string;
    amount: number;
    amount_label: string;
    balance_after_label: string;
    note: string | null;
    user: string | null;
    created_at_label: string;
}

export interface CashAccountSetting {
    id: number;
    name: string;
    type: string;
    type_label: string;
    is_active: boolean;
    sort: number;
}

export interface PaymentMethodSetting {
    id: number;
    name: string;
    type: string;
    type_label: string;
    cash_account_id: number;
    account_name: string;
    /** e.g. "3" or "2.5" */
    surcharge_percent: string;
    needs_reference: boolean;
    is_active: boolean;
    sort: number;
}
