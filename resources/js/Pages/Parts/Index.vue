<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { AlertTriangle, Package, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import StockBadge from '@/Components/Parts/StockBadge.vue';
import Badge from '@/Components/ui/Badge.vue';
import { buttonVariants } from '@/Components/ui/button';
import DataList from '@/Components/ui/DataList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import type { Part } from '@/types/inventory';

const props = defineProps<{
    parts: Paginated<Part>;
    filters: { search: string; status: string };
    lowStockCount: number;
    can: { create: boolean };
}>();

const columns = computed(() => [
    { key: 'name', label: trans('parts.fields.name') },
    { key: 'brand', label: trans('parts.fields.brand') },
    { key: 'stock', label: trans('parts.stock') },
    { key: 'sell_price', label: trans('parts.fields.sell_price'), class: 'text-right tabular-nums' },
]);

const status = ref(props.filters.status);
const statusOptions = computed(() => [
    { value: 'active', label: trans('parts.status.active') },
    { value: 'low', label: trans('parts.status.low') },
    { value: 'inactive', label: trans('parts.status.inactive') },
    { value: 'all', label: trans('parts.status.all') },
]);

function filter(value: string): void {
    status.value = value;
    router.get(
        route('parts.index'),
        { search: props.filters.search || undefined, status: value === 'active' ? undefined : value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const rows = computed(() => props.parts.data as unknown as Record<string, unknown>[]);
const asPart = (row: Record<string, unknown>): Part => row as unknown as Part;
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('parts.title')" :description="$t('parts.description')">
            <template v-if="can.create" #actions>
                <Link :href="route('parts.create')" :class="buttonVariants()"><Plus aria-hidden="true" /> {{ $t('parts.add') }}</Link>
            </template>
        </PageHeader>

        <button
            v-if="lowStockCount > 0 && filters.status !== 'low'"
            type="button"
            class="mb-4 flex w-full items-center gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-left text-sm text-amber-900 hover:bg-amber-100"
            @click="filter('low')"
        >
            <AlertTriangle class="size-5 shrink-0" aria-hidden="true" />
            <span class="flex-1">{{ $tChoice('parts.low_stock_banner', lowStockCount, { count: String(lowStockCount) }) }}</span>
            <span class="font-medium underline underline-offset-4">{{ $t('parts.show_low') }}</span>
        </button>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <SearchInput :initial="filters.search" :placeholder="$t('parts.search_placeholder')" :only="['parts', 'filters', 'lowStockCount']" />
            </div>
            <Select id="status" :model-value="status" :options="statusOptions" :aria-label="$t('parts.status_label')" class="sm:w-48" @update:model-value="(value) => filter(String(value))" />
        </div>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('parts.empty_title')">
            <template #empty>
                <EmptyState
                    :icon="Package"
                    :title="filters.search || filters.status !== 'active' ? $t('parts.no_results_title') : $t('parts.empty_title')"
                    :description="filters.search || filters.status !== 'active' ? $t('parts.no_results_description') : $t('parts.empty_description')"
                >
                    <Link v-if="can.create && !filters.search && filters.status === 'active'" :href="route('parts.create')" :class="buttonVariants()">
                        <Plus aria-hidden="true" /> {{ $t('parts.add') }}
                    </Link>
                </EmptyState>
            </template>

            <template #card="{ item }">
                <Link :href="route('parts.show', asPart(item).id)" class="-m-4 flex items-start gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ asPart(item).name }}</span>
                        <span class="block text-xs text-muted-foreground">
                            <span v-if="asPart(item).sku" class="font-mono">{{ asPart(item).sku }}</span>
                            <span v-if="asPart(item).sku && asPart(item).brand"> · </span>{{ asPart(item).brand }}
                        </span>
                        <span class="mt-1 block text-sm"><StockBadge :part="asPart(item)" /></span>
                    </span>
                    <span class="text-right font-medium tabular-nums">{{ asPart(item).price_label }}</span>
                </Link>
            </template>

            <template #cell-name="{ item }">
                <Link :href="route('parts.show', asPart(item).id)" class="font-medium text-primary hover:underline">{{ asPart(item).name }}</Link>
                <span v-if="asPart(item).sku" class="ml-2 font-mono text-xs text-muted-foreground">{{ asPart(item).sku }}</span>
                <Badge v-if="!asPart(item).is_active" class="ml-2">{{ $t('parts.status.inactive') }}</Badge>
            </template>
            <template #cell-brand="{ item }">{{ asPart(item).brand ?? '–' }}</template>
            <template #cell-stock="{ item }"><StockBadge :part="asPart(item)" /></template>
            <template #cell-sell_price="{ item }">{{ asPart(item).price_label }}</template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="parts" />
        </div>
    </AppLayout>
</template>
