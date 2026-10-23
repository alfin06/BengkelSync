export interface AuthUser {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    locale: string | null;
}

export interface BranchSummary {
    id: number;
    name: string;
    code: string;
}

export interface TenantSummary {
    id: number;
    name: string;
}

export interface FlashMessages {
    success: string | null;
    error: string | null;
}

/**
 * Props shared with every page by App\Http\Middleware\HandleInertiaRequests.
 */
export interface SharedProps {
    appName: string;
    locale: string;
    auth: {
        user: AuthUser | null;
        permissions: string[];
    };
    tenant: TenantSummary | null;
    branch: BranchSummary | null;
    branches: BranchSummary[];
    flash: FlashMessages;
    errors: Record<string, string>;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
}
