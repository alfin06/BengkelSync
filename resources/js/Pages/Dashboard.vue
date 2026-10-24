<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, ChevronRight, LayoutDashboard, Lock } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import SalesChart from '@/Components/Dashboard/SalesChart.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

interface Financials {
    sales_label: string;
    invoice_count: number;
    tax_label: string | null;
    received_label: string;
    surcharge_label: string;
    expenses_label: string;
    expenses_include_restricted: boolean;
    profit: { parts_cost_label: string; expenses_label: string; profit: number; profit_label: string } | null;
    monthly: { month: string; sales: number }[];
    top: { services: { name: string; count: number; revenue_label: string }[]; parts: { name: string; qty_label: string; revenue_label: string }[] };
    mechanics: { name: string; jobs: number }[];
}

/**
 * The home page. Every section is only sent by the server when the user may see it, so
 * the page simply shows what it receives (SPEC section 7, single-branch view).
 */
const props = defineProps<{
    workshop: { statuses: Record<string, number>; checked_in_today: number; due_today: { id: number; plate_number: string; status_label: string; time: string | null }[] } | null;
    lowStock: { count: number; items: { part_id: number; name: string; qty_label: string; empty: boolean }[] } | null;
    receivables: { count: number; total_label: string; overdue_count: number; overdue_label: string } | null;
    supplierDue: { count: number; total_label: string } | null;
    cash: { id: number; name: string; balance: number; balance_label: string }[] | null;
    financials: Financials | null;
    filters: { period: string; from: string | null; to: string | null };
}>();

const page = usePage();
const nothing = computed(() => !props.workshop && !props.lowStock && !props.receivables && !props.cash && !props.financials);

// Period of the money section.
const periods = ['today', 'week', 'month', 'custom'] as const;
const customOpen = ref(props.filters.period === 'custom');
const from = ref(props.filters.from ?? '');
const to = ref(props.filters.to ?? '');

function choose(period: (typeof periods)[number]): void {
    if (period === 'custom') {
        customOpen.value = true;

        return;
    }
    customOpen.value = false;
    router.get(route('dashboard'), { period: period === 'month' ? undefined : period }, { preserveScroll: true, preserveState: true, replace: true });
}

function applyCustom(): void {
    router.get(route('dashboard'), { period: 'custom', from: from.value, to: to.value }, { preserveScroll: true, preserveState: true, replace: true });
}

const statusTiles = ['queued', 'in_progress', 'waiting_parts', 'completed'] as const;
const tone = { queued: 'border-blue-200 bg-blue-50', in_progress: 'border-amber-200 bg-amber-50', waiting_parts: 'border-red-200 bg-red-50', completed: 'border-green-200 bg-green-50' } as const;
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="$t('dashboard.greeting', { name: page.props.auth.user?.name ?? '' })"
            :description="page.props.branch ? $t('dashboard.working_in', { branch: page.props.branch.name }) : undefined"
        />

        <EmptyState v-if="nothing" :icon="LayoutDashboard" :title="$t('dashboard.nothing_title')" :description="$t('dashboard.nothing_description')" />

        <div class="grid gap-6">
            <!-- Money: Finance, Branch Manager, Owner -->
            <section v-if="financials" class="grid gap-4" :aria-label="$t('dashboard.money')">
                <div class="-mx-4 flex gap-2 overflow-x-auto px-4 sm:mx-0 sm:px-0">
                    <button
                        v-for="period in periods"
                        :key="period"
                        type="button"
                        :aria-pressed="filters.period === period || (period === 'custom' && customOpen)"
                        :class="
                            cn(
                                'inline-flex min-h-10 shrink-0 items-center rounded-full border px-4 text-sm font-medium',
                                filters.period === period || (period === 'custom' && customOpen) ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted',
                            )
                        "
                        @click="choose(period)"
                    >
                        {{ $t(`dashboard.period.${period}`) }}
                    </button>
                </div>
                <form v-if="customOpen" class="flex flex-wrap items-end gap-2" @submit.prevent="applyCustom">
                    <Input v-model="from" type="date" class="w-auto" :aria-label="$t('cash.filter_from')" />
                    <Input v-model="to" type="date" :min="from" class="w-auto" :aria-label="$t('cash.filter_to')" />
                    <Button type="submit" :disabled="!from || !to">{{ $t('cash.filter') }}</Button>
                </form>
                <p v-if="filters.from && filters.to" class="-mt-2 text-sm text-muted-foreground">
                    {{ filters.from === filters.to ? formatDate(filters.from) : `${formatDate(filters.from)} – ${formatDate(filters.to)}` }}
                </p>

                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card>
                        <CardContent class="pt-4 sm:pt-6">
                            <p class="text-sm text-muted-foreground">{{ $t('dashboard.sales') }}</p>
                            <p class="whitespace-nowrap text-lg font-semibold tabular-nums xl:text-2xl">{{ financials.sales_label }}</p>
                            <p class="text-xs text-muted-foreground">{{ $tChoice('dashboard.invoices_count', financials.invoice_count, { count: String(financials.invoice_count) }) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4 sm:pt-6">
                            <p class="text-sm text-muted-foreground">{{ $t('dashboard.received') }}</p>
                            <p class="whitespace-nowrap text-lg font-semibold tabular-nums text-green-700 xl:text-2xl">{{ financials.received_label }}</p>
                            <p class="text-xs text-muted-foreground">{{ $t('dashboard.card_fees', { amount: financials.surcharge_label }) }}</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent class="pt-4 sm:pt-6">
                            <p class="text-sm text-muted-foreground">{{ $t('dashboard.expenses') }}</p>
                            <p class="whitespace-nowrap text-lg font-semibold tabular-nums xl:text-2xl">{{ financials.expenses_label }}</p>
                            <p v-if="!financials.expenses_include_restricted" class="text-xs text-muted-foreground">{{ $t('dashboard.expenses_without_restricted') }}</p>
                        </CardContent>
                    </Card>
                    <Card v-if="financials.profit" class="border-primary/40">
                        <CardContent class="pt-4 sm:pt-6">
                            <p class="flex items-center gap-1 text-sm text-muted-foreground"><Lock class="size-3.5" aria-hidden="true" /> {{ $t('dashboard.profit') }}</p>
                            <p :class="cn('whitespace-nowrap text-lg font-semibold tabular-nums xl:text-2xl', financials.profit.profit < 0 && 'text-destructive')">{{ financials.profit.profit_label }}</p>
                            <p class="text-xs text-muted-foreground">{{ $t('dashboard.profit_breakdown', { parts: financials.profit.parts_cost_label, expenses: financials.profit.expenses_label }) }}</p>
                        </CardContent>
                    </Card>
                </div>
                <p v-if="financials.tax_label" class="text-xs text-muted-foreground">{{ $t('dashboard.tax_note', { amount: financials.tax_label }) }}</p>
            </section>

            <!-- Workshop -->
            <section v-if="workshop" class="grid gap-3" :aria-label="$t('dashboard.workshop')">
                <h2 class="font-semibold">{{ $t('dashboard.workshop') }}</h2>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Link
                        v-for="status in statusTiles"
                        :key="status"
                        :href="route('work-orders.index', { status })"
                        :class="cn('rounded-lg border p-4 hover:shadow-sm', tone[status])"
                    >
                        <p class="text-sm">{{ $t(`work_orders.status.${status}`) }}</p>
                        <p class="text-3xl font-semibold tabular-nums">{{ workshop.statuses[status] ?? 0 }}</p>
                    </Link>
                </div>
                <p class="text-sm text-muted-foreground">{{ $tChoice('dashboard.checked_in_today', workshop.checked_in_today, { count: String(workshop.checked_in_today) }) }}</p>
                <Card v-if="workshop.due_today.length">
                    <CardHeader class="pb-2"><CardTitle class="text-base">{{ $t('dashboard.due_today') }}</CardTitle></CardHeader>
                    <CardContent class="p-0 sm:p-0">
                        <ul class="divide-y border-t">
                            <li v-for="job in workshop.due_today" :key="job.id">
                                <Link :href="route('work-orders.show', job.id)" class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-muted/50 sm:px-6">
                                    <span class="w-12 tabular-nums text-muted-foreground">{{ job.time }}</span>
                                    <span class="flex-1 font-medium">{{ job.plate_number }}</span>
                                    <span class="text-muted-foreground">{{ job.status_label }}</span>
                                    <ChevronRight class="size-4 text-muted-foreground" aria-hidden="true" />
                                </Link>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </section>

            <!-- Needs attention -->
            <section v-if="lowStock || receivables || supplierDue" class="grid gap-3 md:grid-cols-3" :aria-label="$t('dashboard.attention')">
                <Card v-if="receivables">
                    <CardContent class="pt-4 sm:pt-6">
                        <Link :href="route('customer-debts.index')" class="block hover:underline">
                            <p class="text-sm text-muted-foreground">{{ $t('dashboard.receivables') }}</p>
                            <p class="whitespace-nowrap text-xl font-semibold tabular-nums">{{ receivables.total_label }}</p>
                        </Link>
                        <p class="text-xs text-muted-foreground">{{ $tChoice('dashboard.invoices_count', receivables.count, { count: String(receivables.count) }) }}</p>
                        <p v-if="receivables.overdue_count" class="mt-1 flex items-center gap-1 text-sm font-medium text-destructive">
                            <AlertTriangle class="size-4" aria-hidden="true" /> {{ $t('dashboard.overdue', { amount: receivables.overdue_label }) }}
                        </p>
                    </CardContent>
                </Card>
                <Card v-if="supplierDue">
                    <CardContent class="pt-4 sm:pt-6">
                        <Link :href="route('supplier-debts.index')" class="block hover:underline">
                            <p class="text-sm text-muted-foreground">{{ $t('dashboard.supplier_due') }}</p>
                            <p class="whitespace-nowrap text-xl font-semibold tabular-nums">{{ supplierDue.total_label }}</p>
                        </Link>
                        <p class="text-xs text-muted-foreground">{{ $tChoice('dashboard.bills_count', supplierDue.count, { count: String(supplierDue.count) }) }}</p>
                    </CardContent>
                </Card>
                <Card v-if="lowStock">
                    <CardContent class="pt-4 sm:pt-6">
                        <Link :href="route('parts.index', { status: 'low' })" class="block hover:underline">
                            <p class="text-sm text-muted-foreground">{{ $t('dashboard.low_stock') }}</p>
                            <p :class="cn('text-xl font-semibold tabular-nums', lowStock.count > 0 && 'text-amber-700')">{{ lowStock.count }}</p>
                        </Link>
                        <ul v-if="lowStock.items.length" class="mt-1 grid gap-0.5 text-sm">
                            <li v-for="item in lowStock.items" :key="item.part_id" class="flex justify-between gap-2">
                                <span class="truncate">{{ item.name }}</span>
                                <span :class="cn('whitespace-nowrap tabular-nums', item.empty ? 'font-medium text-destructive' : 'text-muted-foreground')">{{ item.qty_label }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </section>

            <!-- Cash -->
            <section v-if="cash && cash.length" class="grid gap-3" :aria-label="$t('dashboard.cash')">
                <h2 class="font-semibold">{{ $t('dashboard.cash') }}</h2>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Link v-for="account in cash" :key="account.id" :href="route('cash.show', account.id)" class="rounded-lg border bg-card p-4 hover:shadow-sm">
                        <p class="truncate text-sm text-muted-foreground">{{ account.name }}</p>
                        <p :class="cn('text-lg font-semibold tabular-nums', account.balance < 0 && 'text-destructive')">{{ account.balance_label }}</p>
                    </Link>
                </div>
            </section>

            <!-- Trends -->
            <section v-if="financials" class="grid gap-4 lg:grid-cols-3">
                <Card class="lg:col-span-3">
                    <CardHeader class="pb-2"><CardTitle class="text-base">{{ $t('dashboard.sales_12_months') }}</CardTitle></CardHeader>
                    <CardContent><SalesChart :months="financials.monthly" :label="$t('dashboard.sales')" /></CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-base">{{ $t('dashboard.top_services') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p v-if="!financials.top.services.length" class="text-sm text-muted-foreground">{{ $t('dashboard.none_yet') }}</p>
                        <ol class="grid gap-1 text-sm">
                            <li v-for="item in financials.top.services" :key="item.name" class="flex justify-between gap-2">
                                <span class="truncate">{{ item.name }} <span class="text-muted-foreground">×{{ item.count }}</span></span>
                                <span class="tabular-nums">{{ item.revenue_label }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-base">{{ $t('dashboard.top_parts') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p v-if="!financials.top.parts.length" class="text-sm text-muted-foreground">{{ $t('dashboard.none_yet') }}</p>
                        <ol class="grid gap-1 text-sm">
                            <li v-for="item in financials.top.parts" :key="item.name" class="flex justify-between gap-2">
                                <span class="truncate">{{ item.name }} <span class="text-muted-foreground">{{ item.qty_label }}</span></span>
                                <span class="tabular-nums">{{ item.revenue_label }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="pb-2"><CardTitle class="text-base">{{ $t('dashboard.mechanics') }}</CardTitle></CardHeader>
                    <CardContent>
                        <p v-if="!financials.mechanics.length" class="text-sm text-muted-foreground">{{ $t('dashboard.none_yet') }}</p>
                        <ol class="grid gap-1 text-sm">
                            <li v-for="mechanic in financials.mechanics" :key="mechanic.name" class="flex justify-between gap-2">
                                <span class="truncate">{{ mechanic.name }}</span>
                                <span class="tabular-nums">{{ $tChoice('dashboard.jobs', mechanic.jobs, { count: String(mechanic.jobs) }) }}</span>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
