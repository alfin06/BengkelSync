import type { Component } from 'vue';
import { ClipboardList, HardHat, LayoutDashboard, UsersRound } from 'lucide-vue-next';

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
    {
        label: 'nav.customers',
        route: 'customers.index',
        activePattern: 'customers.*',
        icon: UsersRound,
        permission: 'customer.view',
        primary: true,
    },
    {
        label: 'nav.services',
        route: 'service-items.index',
        activePattern: 'service-items.*',
        icon: ClipboardList,
        permission: 'service.view',
        primary: false,
    },
    {
        label: 'nav.mechanics',
        route: 'mechanics.index',
        activePattern: 'mechanics.*',
        icon: HardHat,
        permission: 'mechanic.view',
        primary: false,
    },
];
