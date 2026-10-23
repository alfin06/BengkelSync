<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ClipboardList, Pencil, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ServiceItemDialog from '@/Components/ServiceItems/ServiceItemDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button } from '@/Components/ui/button';
import DataList from '@/Components/ui/DataList.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import type { ServiceItem } from '@/types/catalog';

const props = defineProps<{
    items: Paginated<ServiceItem>;
    filters: { search: string; status: string };
    categories: string[];
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const columns = computed(() => [
    { key: 'name', label: trans('service_items.fields.name') },
    { key: 'category', label: trans('service_items.fields.category') },
    { key: 'estimated_minutes', label: trans('service_items.fields.estimated_minutes'), class: 'text-right tabular-nums' },
    { key: 'default_price', label: trans('service_items.fields.default_price'), class: 'text-right tabular-nums' },
]);

const status = ref(props.filters.status);
const statusOptions = computed(() => [
    { value: 'active', label: trans('service_items.status.active') },
    { value: 'inactive', label: trans('service_items.status.inactive') },
    { value: 'all', label: trans('service_items.status.all') },
]);

function changeStatus(): void {
    router.get(
        route('service-items.index'),
        { search: props.filters.search || undefined, status: status.value === 'active' ? undefined : status.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

/** Phone view: one section per category, in the order the server sorted them. */
const groups = computed(() => {
    const result: { category: string; items: ServiceItem[] }[] = [];

    for (const item of props.items.data) {
        const category = item.category ?? trans('service_items.no_category');
        const last = result[result.length - 1];

        if (last && last.category === category) {
            last.items.push(item);
        } else {
            result.push({ category, items: [item] });
        }
    }

    return result;
});

const dialogOpen = ref(false);
const editing = ref<ServiceItem | null>(null);

function openDialog(item: ServiceItem | null): void {
    editing.value = item;
    dialogOpen.value = true;
}

const rows = computed(() => props.items.data as unknown as Record<string, unknown>[]);
const asItem = (row: Record<string, unknown>): ServiceItem => row as unknown as ServiceItem;
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('service_items.title')" :description="$t('service_items.description')">
            <template v-if="can.create" #actions>
                <Button @click="openDialog(null)"><Plus aria-hidden="true" /> {{ $t('service_items.add') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <SearchInput :initial="filters.search" :placeholder="$t('service_items.search_placeholder')" :only="['items', 'filters']" />
            </div>
            <Select id="status" v-model="status" :options="statusOptions" :aria-label="$t('service_items.status_label')" class="sm:w-48" @change="changeStatus" />
        </div>

        <EmptyState
            v-if="items.data.length === 0"
            :icon="ClipboardList"
            :title="filters.search ? $t('service_items.no_results_title') : $t('service_items.empty_title')"
            :description="filters.search ? $t('service_items.no_results_description') : $t('service_items.empty_description')"
        >
            <Button v-if="can.create && !filters.search" @click="openDialog(null)"><Plus aria-hidden="true" /> {{ $t('service_items.add') }}</Button>
        </EmptyState>

        <template v-else>
            <!-- Phones: grouped by category, tap a row to edit -->
            <div class="grid gap-5 md:hidden">
                <section v-for="group in groups" :key="group.category">
                    <h2 class="mb-2 text-sm font-semibold text-muted-foreground">{{ group.category }}</h2>
                    <ul class="divide-y rounded-lg border bg-card">
                        <li v-for="item in group.items" :key="item.id">
                            <component
                                :is="can.update ? 'button' : 'div'"
                                :type="can.update ? 'button' : undefined"
                                class="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left"
                                @click="can.update && openDialog(item)"
                            >
                                <span class="flex-1">
                                    <span class="block font-medium">{{ item.name }}</span>
                                    <span class="block text-xs text-muted-foreground">
                                        <span v-if="item.code" class="font-mono">{{ item.code }}</span>
                                        <span v-if="item.code && item.estimated_minutes"> · </span>
                                        <span v-if="item.estimated_minutes">{{ $t('service_items.minutes', { minutes: String(item.estimated_minutes) }) }}</span>
                                    </span>
                                </span>
                                <span class="text-right">
                                    <span class="block font-medium tabular-nums">{{ item.price_label }}</span>
                                    <Badge v-if="!item.is_active" tone="neutral">{{ $t('service_items.status.inactive') }}</Badge>
                                </span>
                            </component>
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Tablet and desktop: table -->
            <div class="hidden md:block">
                <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('service_items.empty_title')">
                    <template #cell-name="{ item }">
                        <span class="font-medium">{{ asItem(item).name }}</span>
                        <span v-if="asItem(item).code" class="ml-2 font-mono text-xs text-muted-foreground">{{ asItem(item).code }}</span>
                        <Badge v-if="!asItem(item).is_active" tone="neutral" class="ml-2">{{ $t('service_items.status.inactive') }}</Badge>
                    </template>
                    <template #cell-category="{ item }">{{ asItem(item).category ?? '–' }}</template>
                    <template #cell-estimated_minutes="{ item }">
                        {{ asItem(item).estimated_minutes ? $t('service_items.minutes', { minutes: String(asItem(item).estimated_minutes) }) : '–' }}
                    </template>
                    <template #cell-default_price="{ item }">{{ asItem(item).price_label }}</template>
                    <template v-if="can.update" #actions="{ item }">
                        <Button variant="ghost" size="icon" :aria-label="$t('common.edit') + ' ' + asItem(item).name" @click="openDialog(asItem(item))">
                            <Pencil aria-hidden="true" />
                        </Button>
                    </template>
                </DataList>
            </div>
        </template>

        <div class="mt-4">
            <Pagination :meta="items" />
        </div>

        <ServiceItemDialog v-model:open="dialogOpen" :item="editing" :categories="categories" :can-delete="can.delete" />
    </AppLayout>
</template>
