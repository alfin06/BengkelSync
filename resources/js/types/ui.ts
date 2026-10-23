export interface SelectOption {
    value: string | number;
    label: string;
}

export interface DataListColumn {
    key: string;
    label: string;
    /** Extra classes for the cell, e.g. 'text-right tabular-nums' for amounts */
    class?: string;
}
