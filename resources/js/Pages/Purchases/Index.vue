<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Plus, ReceiptText, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PurchaseStatusBadge from '@/Components/Purchases/PurchaseStatusBadge.vue';
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
import type { Purchase } from '@/types/purchases';

const props = defineProps<{
    purchases: Paginated<Purchase>;
    filters: { search: string; status: string };
    can: { create: boolean; viewDebts: boolean };
}>();

const columns = computed(() => [
    { key: 'number', label: trans('purchases.fields.number') },
    { key: 'supplier', label: trans('purchases.fields.supplier') },
    { key: 'date', label: trans('purchases.fields.purchase_date') },
    { key: 'status', label: trans('purchases.fields.status') },
    { key: 'total', label: trans('purchases.total'), class: 'text-right tabular-nums' },
]);

const status = ref(props.filters.status);
const statusOptions = computed(() => ['all', 'open', 'unpaid', 'partial', 'paid', 'void'].map((value) => ({ value, label: trans(`purchases.filter.${value}`) })));

function filter(value: string): void {
    status.value = value;
    router.get(
        route('purchases.index'),
        { search: props.filters.search || undefined, status: value === 'all' ? undefined : value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const filtered = computed(() => props.filters.search !== '' || props.filters.status !== 'all');
const rows = computed(() => props.purchases.data as unknown as Record<string, unknown>[]);
const asPurchase = (row: Record<string, unknown>): Purchase => row as unknown as Purchase;
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('purchases.title')" :description="$t('purchases.description')">
            <template v-if="can.create || can.viewDebts" #actions>
                <Link v-if="can.viewDebts" :href="route('supplier-debts.index')" :class="buttonVariants({ variant: 'outline' })">
                    <Wallet aria-hidden="true" /> {{ $t('supplier_debts.title') }}
                </Link>
                <Link v-if="can.create" :href="route('purchases.create')" :class="buttonVariants()"><Plus aria-hidden="true" /> {{ $t('purchases.add') }}</Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <SearchInput :initial="filters.search" :placeholder="$t('purchases.search_placeholder')" :only="['purchases', 'filters']" />
            </div>
            <Select id="status" :model-value="status" :options="statusOptions" :aria-label="$t('purchases.fields.status')" class="sm:w-48" @update:model-value="(value) => filter(String(value))" />
        </div>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('purchases.empty_title')">
            <template #empty>
                <EmptyState
                    :icon="ReceiptText"
                    :title="filtered ? $t('purchases.no_results_title') : $t('purchases.empty_title')"
                    :description="filtered ? $t('purchases.no_results_description') : $t('purchases.empty_description')"
                >
                    <Link v-if="can.create && !filtered" :href="route('purchases.create')" :class="buttonVariants()"><Plus aria-hidden="true" /> {{ $t('purchases.add') }}</Link>
                </EmptyState>
            </template>

            <template #card="{ item }">
                <Link :href="route('purchases.show', asPurchase(item).id)" class="-m-4 flex items-start gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ asPurchase(item).supplier.name }}</span>
                        <span class="block text-xs text-muted-foreground">{{ asPurchase(item).number }} · {{ formatDate(asPurchase(item).purchase_date) }}</span>
                        <span class="mt-1 block"><PurchaseStatusBadge :purchase="asPurchase(item)" /></span>
                    </span>
                    <span class="text-right font-medium tabular-nums">{{ asPurchase(item).total_label }}</span>
                </Link>
            </template>

            <template #cell-number="{ item }">
                <Link :href="route('purchases.show', asPurchase(item).id)" class="font-mono text-sm font-medium text-primary hover:underline">{{ asPurchase(item).number }}</Link>
            </template>
            <template #cell-supplier="{ item }">{{ asPurchase(item).supplier.name }}</template>
            <template #cell-date="{ item }">{{ formatDate(asPurchase(item).purchase_date) }}</template>
            <template #cell-status="{ item }"><PurchaseStatusBadge :purchase="asPurchase(item)" /></template>
            <template #cell-total="{ item }">{{ asPurchase(item).total_label }}</template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="purchases" />
        </div>
    </AppLayout>
</template>
