export interface ExpenseRow {
    id: number;
    expense_date: string;
    category_id: number;
    category_name: string;
    /** In a restricted category (salaries) */
    is_restricted: boolean;
    description: string;
    amount_label: string;
    account_name: string;
    created_by: string | null;
    has_receipt: boolean;
    voided: boolean;
    void_reason: string | null;
    voided_at_label: string | null;
    can: { update: boolean; void: boolean };
}

export interface ExpenseCategoryOption {
    id: number;
    name: string;
    is_active: boolean;
    is_restricted: boolean;
}
