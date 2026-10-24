<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { PartyPopper } from 'lucide-vue-next';
import { computed } from 'vue';
import PurchaseStatusBadge from '@/Components/Purchases/PurchaseStatusBadge.vue';
import { Card, CardContent } from '@/Components/ui/card';
import DataList from '@/Components/ui/DataList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { Paginated } from '@/types';
import type { Purchase } from '@/types/purchases';

const props = defineProps<{
    debts: Paginated<Purchase>;
    outstandingLabel: string;
}>();

const columns = computed(() => [
    { key: 'supplier', label: trans('purchases.fields.supplier') },
    { key: 'number', label: trans('purchases.fields.number') },
    { key: 'due', label: trans('purchases.fields.due_date') },
    { key: 'status', label: trans('purchases.fields.status') },
    { key: 'outstanding', label: trans('purchases.outstanding'), class: 'text-right tabular-nums' },
]);

const rows = computed(() => props.debts.data as unknown as Record<string, unknown>[]);
const asPurchase = (row: Record<string, unknown>): Purchase => row as unknown as Purchase;
const dueLabel = (purchase: Purchase): string => (purchase.due_date ? formatDate(purchase.due_date) : trans('supplier_debts.no_due_date'));
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('supplier_debts.title')" :description="$t('supplier_debts.description')" />

        <Card class="mb-6">
            <CardContent class="pt-4 sm:pt-6">
                <p class="text-sm text-muted-foreground">{{ $t('supplier_debts.total') }}</p>
                <p class="text-3xl font-semibold tabular-nums">{{ outstandingLabel }}</p>
            </CardContent>
        </Card>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('supplier_debts.empty_title')">
            <template #empty>
                <EmptyState :icon="PartyPopper" :title="$t('supplier_debts.empty_title')" :description="$t('supplier_debts.empty_description')" />
            </template>

            <template #card="{ item }">
                <Link :href="route('purchases.show', asPurchase(item).id)" class="-m-4 flex items-start gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ asPurchase(item).supplier.name }}</span>
                        <span :class="['block text-xs', asPurchase(item).is_overdue ? 'font-medium text-destructive' : 'text-muted-foreground']">
                            {{ $t('supplier_debts.due', { date: dueLabel(asPurchase(item)) }) }}
                        </span>
                        <span class="mt-1 block"><PurchaseStatusBadge :purchase="asPurchase(item)" /></span>
                    </span>
                    <span class="text-right">
                        <span class="block font-semibold tabular-nums">{{ asPurchase(item).outstanding_label }}</span>
                        <span class="block text-xs text-muted-foreground">{{ asPurchase(item).number }}</span>
                    </span>
                </Link>
            </template>

            <template #cell-supplier="{ item }">
                <Link :href="route('purchases.show', asPurchase(item).id)" class="font-medium text-primary hover:underline">{{ asPurchase(item).supplier.name }}</Link>
            </template>
            <template #cell-number="{ item }"><span class="font-mono text-sm">{{ asPurchase(item).number }}</span></template>
            <template #cell-due="{ item }">
                <span :class="asPurchase(item).is_overdue && 'font-medium text-destructive'">{{ dueLabel(asPurchase(item)) }}</span>
            </template>
            <template #cell-status="{ item }"><PurchaseStatusBadge :purchase="asPurchase(item)" /></template>
            <template #cell-outstanding="{ item }"><span class="font-semibold">{{ asPurchase(item).outstanding_label }}</span></template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="debts" />
        </div>
    </AppLayout>
</template>
