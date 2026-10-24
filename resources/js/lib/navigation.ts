import type { Component } from 'vue';
import { ClipboardList, FileText, HandCoins, Receipt, Wrench, HardHat, LayoutDashboard, Package, ReceiptText, Settings, Truck, UsersRound, Wallet } from 'lucide-vue-next';

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
        label: 'nav.work_orders',
        route: 'work-orders.index',
        activePattern: 'work-orders.*',
        icon: Wrench,
        permission: 'work_order.view',
        primary: true,
    },
    {
        label: 'nav.invoices',
        route: 'invoices.index',
        activePattern: 'invoices.*',
        icon: FileText,
        permission: 'invoice.view',
        primary: false,
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
        label: 'nav.parts',
        route: 'parts.index',
        activePattern: 'parts.*',
        icon: Package,
        permission: 'part.view',
        primary: true,
    },
    {
        label: 'nav.purchases',
        route: 'purchases.index',
        activePattern: 'purchases.*',
        icon: ReceiptText,
        permission: 'purchase.view',
        primary: false,
    },
    {
        label: 'nav.supplier_debts',
        route: 'supplier-debts.index',
        activePattern: 'supplier-debts.*',
        icon: HandCoins,
        permission: 'supplier_payment.view',
        primary: false,
    },
    {
        label: 'nav.suppliers',
        route: 'suppliers.index',
        activePattern: 'suppliers.*',
        icon: Truck,
        permission: 'supplier.view',
        primary: false,
    },
    {
        label: 'nav.cash',
        route: 'cash.index',
        activePattern: 'cash.*',
        icon: Wallet,
        permission: 'cash.view',
        primary: false,
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
    {
        label: 'nav.invoice_settings',
        route: 'settings.invoice.edit',
        activePattern: 'settings.invoice.*',
        icon: Receipt,
        permission: 'branch.update',
        primary: false,
    },
    {
        label: 'nav.payment_settings',
        route: 'settings.payments.index',
        activePattern: 'settings.payments.*',
        icon: Settings,
        permission: 'branch.update',
        primary: false,
    },
];
