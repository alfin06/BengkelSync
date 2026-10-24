/** A staff account in user management */
export interface StaffUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string | null;
    role_id: number | null;
    role_label: string | null;
    all_branches: boolean;
    branch_ids: number[];
    branch_names: string[];
    is_active: boolean;
    /** The person looking at the list (edit yourself on the profile page) */
    is_self: boolean;
    last_login_label: string | null;
    can: { update: boolean; delete: boolean };
}

export interface RoleSummary {
    id: number;
    label: string;
    is_system: boolean;
    is_owner: boolean;
    users_count: number;
    permissions_count: number;
    can: { update: boolean; delete: boolean };
}

/** One row of the permission matrix: a resource, its usual actions and any others */
export interface MatrixRow {
    resource: string;
    /** view/create/update/delete → permission name, or null when the resource lacks it */
    standard: Record<'view' | 'create' | 'update' | 'delete', string | null>;
    extra: string[];
}
