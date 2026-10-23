<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { Plus, UsersRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
import type { Customer } from '@/types/customers';

const props = defineProps<{
    customers: Paginated<Customer>;
    filters: { search: string; sort: string };
    can: { create: boolean };
}>();

const columns = computed(() => [
    { key: 'name', label: trans('customers.fields.name') },
    { key: 'phone', label: trans('fields.phone') },
    { key: 'vehicles', label: trans('customers.vehicles') },
]);

const sort = ref(props.filters.sort);
const sortOptions = computed(() => [
    { value: 'name', label: trans('customers.sort.name') },
    { value: 'latest', label: trans('customers.sort.latest') },
]);

function changeSort(): void {
    router.get(
        route('customers.index'),
        { search: props.filters.search || undefined, sort: sort.value === 'name' ? undefined : sort.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const rows = computed(() => props.customers.data as unknown as Record<string, unknown>[]);
const asCustomer = (item: Record<string, unknown>): Customer => item as unknown as Customer;
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('customers.title')" :description="$t('customers.description')">
            <template v-if="can.create" #actions>
                <Link :href="route('customers.create')" :class="buttonVariants()">
                    <Plus aria-hidden="true" /> {{ $t('customers.add') }}
                </Link>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <SearchInput :initial="filters.search" :placeholder="$t('customers.search_placeholder')" :only="['customers', 'filters']" />
            </div>
            <Select
                id="sort"
                v-model="sort"
                :options="sortOptions"
                :aria-label="$t('customers.sort_label')"
                class="sm:w-48"
                @change="changeSort"
            />
        </div>

        <DataList :items="rows" :columns="columns" row-key="id" :empty-title="$t('customers.empty_title')">
            <template #empty>
                <EmptyState
                    :icon="UsersRound"
                    :title="filters.search ? $t('customers.no_results_title') : $t('customers.empty_title')"
                    :description="filters.search ? $t('customers.no_results_description') : $t('customers.empty_description')"
                >
                    <Link v-if="can.create && !filters.search" :href="route('customers.create')" :class="buttonVariants()">
                        <Plus aria-hidden="true" /> {{ $t('customers.add') }}
                    </Link>
                </EmptyState>
            </template>

            <template #card="{ item }">
                <Link :href="route('customers.show', asCustomer(item).id)" class="-m-4 block p-4">
                    <p class="font-medium">{{ asCustomer(item).name }}</p>
                    <p v-if="asCustomer(item).phone" class="text-sm text-muted-foreground">{{ asCustomer(item).phone }}</p>
                    <div v-if="asCustomer(item).vehicles?.length" class="mt-2 flex flex-wrap gap-1">
                        <Badge v-for="vehicle in asCustomer(item).vehicles" :key="vehicle.id">{{ vehicle.plate_number }}</Badge>
                    </div>
                </Link>
            </template>

            <template #cell-name="{ item }">
                <Link :href="route('customers.show', asCustomer(item).id)" class="font-medium text-primary hover:underline">
                    {{ asCustomer(item).name }}
                </Link>
                <span v-if="asCustomer(item).type === 'company'" class="ml-2 text-xs text-muted-foreground">{{ asCustomer(item).type_label }}</span>
            </template>
            <template #cell-phone="{ item }">{{ asCustomer(item).phone ?? '–' }}</template>
            <template #cell-vehicles="{ item }">
                <div class="flex flex-wrap gap-1">
                    <Badge v-for="vehicle in asCustomer(item).vehicles" :key="vehicle.id">{{ vehicle.plate_number }}</Badge>
                    <span v-if="!asCustomer(item).vehicles?.length" class="text-muted-foreground">–</span>
                </div>
            </template>
        </DataList>

        <div class="mt-4">
            <Pagination :meta="customers" />
        </div>
    </AppLayout>
</template>
