<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PartyPopper } from 'lucide-vue-next';
import { computed } from 'vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import { Card, CardContent } from '@/Components/ui/card';
import DataList from '@/Components/ui/DataList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types';
import type { Invoice } from '@/types/invoices';

const props = defineProps<{
    debts: Paginated<Invoice>;
    outstandingLabel: string;
    overdueLabel: string;
}>();

const columns = computed(() => [
    { key: 'customer', label: trans('invoices.fields.customer') },
    { key: 'number', label: trans('invoices.fields.number') },
    { key: 'due', label: trans('invoices.fields.due_date') },
    { key: 'status', label: trans('invoices.fields.status') },
    { key: 'outstanding', label: trans('invoices.outstanding'), class: 'text-right tabular-nums' },
]);

const rows = computed(() => props.debts.data as unknown as Record<string, unknown>[]);
const asInvoice = (row: Record<string, unknown>): Invoice => row as unknown as Invoice;
const customerName = (invoice: Invoice): string => invoice.customer?.name ?? trans('invoices.walk_in_customer');
const dueLabel = (invoice: Invoice): string => (invoice.due_date ? formatDate(invoice.due_date) : trans('supplier_debts.no_due_date'));
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('customer_debts.title')" :description="$t('customer_debts.description')" />

        <div class="mb-6 grid grid-cols-2 gap-4">
            <Card>
                <CardContent class="pt-4 sm:pt-6">
                    <p class="text-sm text-muted-foreground">{{ $t('customer_debts.total') }}</p>
                    <p class="text-2xl font-semibold tabular-nums sm:text-3xl">{{ outstandingLabel }}</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-4 sm:pt-6">
                    <p class="text-sm text-muted-foreground">{{ $t('customer_debts.overdue') }}</p>
                    <p class="text-2xl font-semibold tabular-nums text-destructive sm:text-3xl">{{ overdueLabel }}</p>
                </CardContent>
            </Card>
        </div>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('customer_debts.empty_title')">
            <template #empty>
                <EmptyState :icon="PartyPopper" :title="$t('customer_debts.empty_title')" :description="$t('customer_debts.empty_description')" />
            </template>

            <template #card="{ item }">
                <Link :href="route('invoices.show', asInvoice(item).id)" class="-m-4 flex items-start gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ customerName(asInvoice(item)) }}</span>
                        <span v-if="asInvoice(item).vehicle" class="block text-sm">{{ asInvoice(item).vehicle?.plate_number }}</span>
                        <span :class="['block text-xs', asInvoice(item).is_overdue ? 'font-medium text-destructive' : 'text-muted-foreground']">
                            {{ $t('supplier_debts.due', { date: dueLabel(asInvoice(item)) }) }}
                        </span>
                    </span>
                    <span class="text-right">
                        <span class="block font-semibold tabular-nums">{{ asInvoice(item).outstanding_label }}</span>
                        <span class="block text-xs text-muted-foreground">{{ asInvoice(item).number }}</span>
                    </span>
                </Link>
            </template>

            <template #cell-customer="{ item }">
                <Link :href="route('invoices.show', asInvoice(item).id)" class="font-medium text-primary hover:underline">{{ customerName(asInvoice(item)) }}</Link>
                <span v-if="asInvoice(item).vehicle" class="block text-xs text-muted-foreground">{{ asInvoice(item).vehicle?.plate_number }}</span>
            </template>
            <template #cell-number="{ item }"><span class="font-mono text-sm">{{ asInvoice(item).number }}</span></template>
            <template #cell-due="{ item }">
                <span :class="asInvoice(item).is_overdue && 'font-medium text-destructive'">{{ dueLabel(asInvoice(item)) }}</span>
            </template>
            <template #cell-status="{ item }"><InvoiceStatusBadge :status="asInvoice(item).status" :label="asInvoice(item).status_label" /></template>
            <template #cell-outstanding="{ item }"><span class="font-semibold">{{ asInvoice(item).outstanding_label }}</span></template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="debts" />
        </div>
    </AppLayout>
</template>
