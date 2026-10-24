<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ClipboardList, Plus } from 'lucide-vue-next';
import { computed } from 'vue';
import WorkOrderStatusBadge from '@/Components/WorkOrders/WorkOrderStatusBadge.vue';
import { buttonVariants } from '@/Components/ui/button';
import DataList from '@/Components/ui/DataList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { WorkOrder } from '@/types/workOrders';

const props = defineProps<{
    workOrders: Paginated<WorkOrder>;
    filters: { search: string; status: string };
    /** Work orders per status in this branch */
    counts: Record<string, number>;
    can: { create: boolean };
}>();

const columns = computed(() => [
    { key: 'number', label: trans('work_orders.fields.number') },
    { key: 'vehicle', label: trans('work_orders.fields.vehicle') },
    { key: 'customer', label: trans('work_orders.fields.customer') },
    { key: 'complaint', label: trans('work_orders.fields.complaint') },
    { key: 'status', label: trans('work_orders.fields.status') },
]);

const openCount = computed(() => ['draft', 'queued', 'in_progress', 'waiting_parts'].reduce((sum, status) => sum + (props.counts[status] ?? 0), 0));

// The chips staff use most; the rest (invoiced, closed, cancelled) are under "Semua".
const chips = computed(() => [
    { value: 'open', label: trans('work_orders.filter.open'), count: openCount.value },
    { value: 'queued', label: trans('work_orders.status.queued'), count: props.counts.queued ?? 0 },
    { value: 'in_progress', label: trans('work_orders.status.in_progress'), count: props.counts.in_progress ?? 0 },
    { value: 'waiting_parts', label: trans('work_orders.status.waiting_parts'), count: props.counts.waiting_parts ?? 0 },
    { value: 'completed', label: trans('work_orders.status.completed'), count: props.counts.completed ?? 0 },
    { value: 'all', label: trans('work_orders.filter.all'), count: null },
]);

function filter(value: string): void {
    router.get(
        route('work-orders.index'),
        { search: props.filters.search || undefined, status: value === 'open' ? undefined : value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const filtered = computed(() => props.filters.search !== '' || props.filters.status !== 'open');
const rows = computed(() => props.workOrders.data as unknown as Record<string, unknown>[]);
const asWorkOrder = (row: Record<string, unknown>): WorkOrder => row as unknown as WorkOrder;
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('work_orders.title')" :description="$t('work_orders.description')">
            <template v-if="can.create" #actions>
                <Link :href="route('work-orders.create')" :class="buttonVariants()"><Plus aria-hidden="true" /> {{ $t('work_orders.add') }}</Link>
            </template>
        </PageHeader>

        <div class="mb-3">
            <SearchInput :initial="filters.search" :placeholder="$t('work_orders.search_placeholder')" :only="['workOrders', 'filters', 'counts']" />
        </div>

        <nav class="-mx-4 mb-4 flex gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:px-0" :aria-label="$t('work_orders.fields.status')">
            <button
                v-for="chip in chips"
                :key="chip.value"
                type="button"
                :aria-pressed="filters.status === chip.value"
                :class="
                    cn(
                        'inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-4 text-sm font-medium',
                        filters.status === chip.value ? 'border-primary bg-primary text-primary-foreground' : 'bg-background hover:bg-muted',
                    )
                "
                @click="filter(chip.value)"
            >
                {{ chip.label }}
                <span v-if="chip.count !== null" class="tabular-nums opacity-80">{{ chip.count }}</span>
            </button>
        </nav>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('work_orders.empty_title')">
            <template #empty>
                <EmptyState
                    :icon="ClipboardList"
                    :title="filtered ? $t('work_orders.no_results_title') : $t('work_orders.empty_title')"
                    :description="filtered ? $t('work_orders.no_results_description') : $t('work_orders.empty_description')"
                >
                    <Link v-if="can.create && !filtered" :href="route('work-orders.create')" :class="buttonVariants()"><Plus aria-hidden="true" /> {{ $t('work_orders.add') }}</Link>
                </EmptyState>
            </template>

            <template #card="{ item }">
                <Link :href="route('work-orders.show', asWorkOrder(item).id)" class="-m-4 flex items-start gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ asWorkOrder(item).vehicle.plate_number }}</span>
                        <span class="block text-sm">{{ asWorkOrder(item).vehicle.name }} · {{ asWorkOrder(item).customer.name }}</span>
                        <span class="mt-1 line-clamp-2 block text-sm text-muted-foreground">{{ asWorkOrder(item).complaint }}</span>
                        <span class="mt-1 block text-xs text-muted-foreground">{{ asWorkOrder(item).number }} · {{ asWorkOrder(item).created_at_label }}</span>
                    </span>
                    <WorkOrderStatusBadge :status="asWorkOrder(item).status" :label="asWorkOrder(item).status_label" />
                </Link>
            </template>

            <template #cell-number="{ item }">
                <Link :href="route('work-orders.show', asWorkOrder(item).id)" class="font-mono text-sm font-medium text-primary hover:underline">{{ asWorkOrder(item).number }}</Link>
                <span class="block text-xs text-muted-foreground">{{ asWorkOrder(item).created_at_label }}</span>
            </template>
            <template #cell-vehicle="{ item }">
                <span class="font-medium">{{ asWorkOrder(item).vehicle.plate_number }}</span>
                <span class="block text-xs text-muted-foreground">{{ asWorkOrder(item).vehicle.name }}</span>
            </template>
            <template #cell-customer="{ item }">{{ asWorkOrder(item).customer.name }}</template>
            <template #cell-complaint="{ item }"><span class="line-clamp-2 max-w-xs">{{ asWorkOrder(item).complaint }}</span></template>
            <template #cell-status="{ item }"><WorkOrderStatusBadge :status="asWorkOrder(item).status" :label="asWorkOrder(item).status_label" /></template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="workOrders" />
        </div>
    </AppLayout>
</template>
