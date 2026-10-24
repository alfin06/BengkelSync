<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { FileText, ShoppingBag } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import { buttonVariants } from '@/Components/ui/button';
import DataList from '@/Components/ui/DataList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types';
import type { Invoice } from '@/types/invoices';

const props = defineProps<{
    invoices: Paginated<Invoice>;
    filters: { search: string; status: string };
    can: { create: boolean };
}>();

const columns = computed(() => [
    { key: 'number', label: trans('invoices.fields.number') },
    { key: 'customer', label: trans('invoices.fields.customer') },
    { key: 'date', label: trans('invoices.fields.issue_date') },
    { key: 'status', label: trans('invoices.fields.status') },
    { key: 'total', label: trans('invoices.total'), class: 'text-right tabular-nums' },
]);

const status = ref(props.filters.status);
const statusOptions = computed(() => ['all', 'open', 'paid', 'void'].map((value) => ({ value, label: trans(`invoices.filter.${value}`) })));

function filter(value: string): void {
    status.value = value;
    router.get(
        route('invoices.index'),
        { search: props.filters.search || undefined, status: value === 'all' ? undefined : value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const filtered = computed(() => props.filters.search !== '' || props.filters.status !== 'all');
const rows = computed(() => props.invoices.data as unknown as Record<string, unknown>[]);
const asInvoice = (row: Record<string, unknown>): Invoice => row as unknown as Invoice;
const customerName = (invoice: Invoice): string => invoice.customer?.name ?? trans('invoices.walk_in_customer');
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('invoices.title')" :description="$t('invoices.description')">
            <template v-if="can.create" #actions>
                <Link :href="route('invoices.create')" :class="buttonVariants({ variant: 'outline' })"><ShoppingBag aria-hidden="true" /> {{ $t('invoices.walk_in') }}</Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <SearchInput :initial="filters.search" :placeholder="$t('invoices.search_placeholder')" :only="['invoices', 'filters']" />
            </div>
            <Select id="status" :model-value="status" :options="statusOptions" :aria-label="$t('invoices.fields.status')" class="sm:w-48" @update:model-value="(value) => filter(String(value))" />
        </div>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('invoices.empty_title')">
            <template #empty>
                <EmptyState
                    :icon="FileText"
                    :title="filtered ? $t('invoices.no_results_title') : $t('invoices.empty_title')"
                    :description="filtered ? $t('invoices.no_results_description') : $t('invoices.empty_description')"
                />
            </template>

            <template #card="{ item }">
                <Link :href="route('invoices.show', asInvoice(item).id)" class="-m-4 flex items-start gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ customerName(asInvoice(item)) }}</span>
                        <span v-if="asInvoice(item).vehicle" class="block text-sm">{{ asInvoice(item).vehicle?.plate_number }}</span>
                        <span class="block text-xs text-muted-foreground">{{ asInvoice(item).number }} · {{ formatDate(asInvoice(item).issue_date) }}</span>
                        <span class="mt-1 block"><InvoiceStatusBadge :status="asInvoice(item).status" :label="asInvoice(item).status_label" /></span>
                    </span>
                    <span class="text-right font-medium tabular-nums">{{ asInvoice(item).total_label }}</span>
                </Link>
            </template>

            <template #cell-number="{ item }">
                <Link :href="route('invoices.show', asInvoice(item).id)" class="font-mono text-sm font-medium text-primary hover:underline">{{ asInvoice(item).number }}</Link>
            </template>
            <template #cell-customer="{ item }">
                {{ customerName(asInvoice(item)) }}
                <span v-if="asInvoice(item).vehicle" class="block text-xs text-muted-foreground">{{ asInvoice(item).vehicle?.plate_number }}</span>
            </template>
            <template #cell-date="{ item }">{{ formatDate(asInvoice(item).issue_date) }}</template>
            <template #cell-status="{ item }"><InvoiceStatusBadge :status="asInvoice(item).status" :label="asInvoice(item).status_label" /></template>
            <template #cell-total="{ item }">{{ asInvoice(item).total_label }}</template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="invoices" />
        </div>
    </AppLayout>
</template>
