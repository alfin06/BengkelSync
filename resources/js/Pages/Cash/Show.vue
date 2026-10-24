<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { History } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { CashAccountSummary, CashMovement } from '@/types/cash';

const props = defineProps<{
    account: CashAccountSummary;
    movements: Paginated<CashMovement>;
    filters: { from: string; to: string };
}>();

const from = ref(props.filters.from);
const to = ref(props.filters.to);

function applyFilter(): void {
    router.get(route('cash.show', props.account.id), { from: from.value || undefined, to: to.value || undefined }, { preserveScroll: true, replace: true });
}

function resetFilter(): void {
    from.value = '';
    to.value = '';
    applyFilter();
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="account.name" :description="account.type_label" />

        <Card class="mb-6">
            <CardContent class="pt-4 sm:pt-6">
                <p class="text-sm text-muted-foreground">{{ $t('cash.current_balance') }}</p>
                <p :class="cn('text-3xl font-semibold tabular-nums', account.balance < 0 && 'text-destructive')">{{ account.balance_label }}</p>
            </CardContent>
        </Card>

        <form class="mb-4 grid grid-cols-2 gap-3 sm:flex sm:items-end" @submit.prevent="applyFilter">
            <FormField id="from" :label="$t('cash.filter_from')">
                <Input id="from" v-model="from" type="date" />
            </FormField>
            <FormField id="to" :label="$t('cash.filter_to')">
                <Input id="to" v-model="to" type="date" />
            </FormField>
            <Button type="submit" class="col-span-1">{{ $t('cash.filter') }}</Button>
            <Button v-if="filters.from || filters.to" type="button" variant="outline" class="col-span-1" @click="resetFilter">{{ $t('cash.reset') }}</Button>
        </form>

        <EmptyState v-if="movements.data.length === 0" :icon="History" :title="$t('cash.history_empty')" />

        <ol v-else class="divide-y rounded-lg border bg-card">
            <li v-for="movement in movements.data" :key="movement.id" class="flex items-start gap-3 p-4 text-sm">
                <span class="min-w-0 flex-1">
                    <span class="block font-medium">{{ movement.type_label }}</span>
                    <span v-if="movement.note" class="block">{{ movement.note }}</span>
                    <span class="block text-xs text-muted-foreground">{{ movement.created_at_label }}<template v-if="movement.user"> · {{ movement.user }}</template></span>
                </span>
                <span class="text-right tabular-nums">
                    <span :class="['block font-semibold', movement.amount < 0 ? 'text-destructive' : 'text-green-700']">{{ movement.amount_label }}</span>
                    <span class="block text-xs text-muted-foreground">{{ $t('cash.balance_after', { amount: movement.balance_after_label }) }}</span>
                </span>
            </li>
        </ol>

        <div class="mt-4">
            <Pagination :meta="movements" />
        </div>
    </AppLayout>
</template>
