<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ChevronRight, HardHat, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MechanicDialog from '@/Components/Mechanics/MechanicDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button } from '@/Components/ui/button';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import type { Mechanic, MechanicAbilities } from '@/types/mechanics';

const props = defineProps<{
    mechanics: Paginated<Mechanic>;
    filters: { search: string; status: string };
    can: MechanicAbilities;
}>();

const page = usePage();
const status = ref(props.filters.status);
const statusOptions = computed(() => [
    { value: 'active', label: trans('mechanics.status.active') },
    { value: 'inactive', label: trans('mechanics.status.inactive') },
    { value: 'all', label: trans('mechanics.status.all') },
]);

function changeStatus(): void {
    router.get(
        route('mechanics.index'),
        { search: props.filters.search || undefined, status: status.value === 'active' ? undefined : status.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const dialogOpen = ref(false);
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="$t('mechanics.title')"
            :description="page.props.branch ? $t('mechanics.description', { branch: page.props.branch.name }) : undefined"
        >
            <template v-if="can.create" #actions>
                <Button @click="dialogOpen = true"><Plus aria-hidden="true" /> {{ $t('mechanics.add') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4 flex flex-col gap-2 sm:flex-row">
            <div class="flex-1">
                <SearchInput :initial="filters.search" :placeholder="$t('mechanics.search_placeholder')" :only="['mechanics', 'filters']" />
            </div>
            <Select id="status" v-model="status" :options="statusOptions" :aria-label="$t('mechanics.status_label')" class="sm:w-48" @change="changeStatus" />
        </div>

        <EmptyState
            v-if="mechanics.data.length === 0"
            :icon="HardHat"
            :title="filters.search ? $t('mechanics.no_results_title') : $t('mechanics.empty_title')"
            :description="filters.search ? $t('mechanics.no_results_description') : $t('mechanics.empty_description')"
        >
            <Button v-if="can.create && !filters.search" @click="dialogOpen = true"><Plus aria-hidden="true" /> {{ $t('mechanics.add') }}</Button>
        </EmptyState>

        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="mechanic in mechanics.data" :key="mechanic.id">
                <Link :href="route('mechanics.show', mechanic.id)" class="flex min-h-16 items-center gap-3 rounded-lg border bg-card p-4 hover:bg-muted/50">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <HardHat class="size-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-medium">{{ mechanic.name }}</span>
                        <span class="block text-sm text-muted-foreground">{{ mechanic.phone ?? $t('mechanics.no_phone') }}</span>
                    </span>
                    <Badge v-if="!mechanic.is_active" tone="neutral">{{ $t('mechanics.status.inactive') }}</Badge>
                    <ChevronRight class="size-4 text-muted-foreground" aria-hidden="true" />
                </Link>
            </li>
        </ul>

        <div class="mt-4">
            <Pagination :meta="mechanics" />
        </div>

        <MechanicDialog v-model:open="dialogOpen" :mechanic="null" :can-manage-identity="can.identity" />
    </AppLayout>
</template>
