import type { Component } from 'vue';
import { LayoutDashboard } from 'lucide-vue-next';

export interface NavItem {
    /** Translation key of the label */
    label: string;
    /** Ziggy route name; items whose route does not exist yet are hidden */
    route: string;
    /** Route name pattern used to highlight the active item */
    activePattern: string;
    icon: Component;
    /** Permission needed to see the item; null = every tenant member */
    permission: string | null;
    /** Shown in the phone bottom bar (max 4) */
    primary: boolean;
}

/**
 * Main navigation. Phase 1 modules add their entry here (customers, work orders, ...).
 */
export const navigation: NavItem[] = [
    {
        label: 'nav.dashboard',
        route: 'dashboard',
        activePattern: 'dashboard',
        icon: LayoutDashboard,
        permission: null,
        primary: true,
    },
];
