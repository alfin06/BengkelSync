<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { FileImage, Lock, Plus, Receipt, Tags } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CategoriesDialog from '@/Components/Expenses/CategoriesDialog.vue';
import ExpenseDialog from '@/Components/Expenses/ExpenseDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { ExpenseCategoryOption, ExpenseRow } from '@/types/expenses';
import type { SelectOption } from '@/types/ui';

const props = defineProps<{
    expenses: Paginated<ExpenseRow>;
    filters: { month: string; category_id: number | null; cash_account_id: number | null };
    totalLabel: string;
    today: string;
    categories: ExpenseCategoryOption[];
    accounts: { id: number; name: string }[];
    payAccounts: SelectOption[];
    cashLimit: number | null;
    can: { create: boolean; manageCategories: boolean; restricted: boolean };
}>();

const categoryFilter = computed(() => [{ value: '', label: trans('expenses.all_categories') }, ...props.categories.map((category) => ({ value: category.id, label: category.name }))]);
const accountFilter = computed(() => [{ value: '', label: trans('expenses.all_accounts') }, ...props.accounts.map((account) => ({ value: account.id, label: account.name }))]);

function filter(changes: Partial<typeof props.filters>): void {
    const next = { ...props.filters, ...changes };
    router.get(
        route('expenses.index'),
        { month: next.month || undefined, category_id: next.category_id || undefined, cash_account_id: next.cash_account_id || undefined },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const dialogOpen = ref(false);
const editing = ref<ExpenseRow | null>(null);

function openDialog(expense: ExpenseRow | null): void {
    editing.value = expense;
    dialogOpen.value = true;
}

const categoriesOpen = ref(false);

const toVoid = ref<ExpenseRow | null>(null);
const voidOpen = ref(false);
const voiding = ref(false);

function askVoid(expense: ExpenseRow): void {
    toVoid.value = expense;
    voidOpen.value = true;
}

function voidExpense(reason: string | null): void {
    if (!toVoid.value) {
        return;
    }

    router.post(route('expenses.void', toVoid.value.id), { reason }, {
        preserveScroll: true,
        onStart: () => (voiding.value = true),
        onFinish: () => (voiding.value = false),
        onSuccess: () => (voidOpen.value = false),
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('expenses.title')" :description="$t('expenses.description')">
            <template #actions>
                <Button v-if="can.manageCategories" variant="outline" @click="categoriesOpen = true"><Tags aria-hidden="true" /> {{ $t('expenses.categories') }}</Button>
                <Button v-if="can.create" @click="openDialog(null)"><Plus aria-hidden="true" /> {{ $t('expenses.add') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4 grid gap-2 sm:grid-cols-3">
            <Input type="month" :model-value="filters.month" :aria-label="$t('expenses.fields.date')" @update:model-value="(value) => filter({ month: String(value) })" />
            <Select :model-value="filters.category_id ?? ''" :options="categoryFilter" :aria-label="$t('expenses.fields.category')" @update:model-value="(value) => filter({ category_id: value ? Number(value) : null })" />
            <Select :model-value="filters.cash_account_id ?? ''" :options="accountFilter" :aria-label="$t('expenses.fields.account')" @update:model-value="(value) => filter({ cash_account_id: value ? Number(value) : null })" />
        </div>

        <Card class="mb-4">
            <CardContent class="pt-4 sm:pt-6">
                <p class="text-sm text-muted-foreground">{{ $t('expenses.total_filtered') }}</p>
                <p class="text-3xl font-semibold tabular-nums">{{ totalLabel }}</p>
            </CardContent>
        </Card>

        <EmptyState v-if="expenses.data.length === 0" :icon="Receipt" :title="$t('expenses.empty_title')" :description="$t('expenses.empty_description')" />

        <ul v-else class="divide-y rounded-lg border bg-card">
            <li v-for="expense in expenses.data" :key="expense.id" class="flex items-start gap-3 p-4 text-sm">
                <span :class="cn('min-w-0 flex-1', expense.voided && 'text-muted-foreground')">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ expense.description }}</span>
                        <Badge v-if="expense.is_restricted" tone="warning"><Lock class="size-3" aria-hidden="true" /> {{ $t('expenses.restricted') }}</Badge>
                    </span>
                    <span class="block text-muted-foreground">
                        {{ formatDate(expense.expense_date) }} · {{ expense.category_name }} · {{ expense.account_name }}
                        <template v-if="expense.created_by"> · {{ $t('expenses.by', { name: expense.created_by }) }}</template>
                    </span>
                    <span v-if="expense.voided" class="block text-xs text-destructive">{{ $t('expenses.voided_notice', { reason: expense.void_reason ?? '', at: expense.voided_at_label ?? '' }) }}</span>
                    <span class="mt-1 flex flex-wrap gap-3 text-xs">
                        <a v-if="expense.has_receipt" :href="route('expenses.receipt', expense.id)" target="_blank" rel="noopener" class="inline-flex items-center gap-1 font-medium text-primary hover:underline">
                            <FileImage class="size-3.5" aria-hidden="true" /> {{ $t('expenses.view_receipt') }}
                        </a>
                        <button v-if="expense.can.update" type="button" class="font-medium text-primary hover:underline" @click="openDialog(expense)">{{ $t('common.edit') }}</button>
                        <button v-if="expense.can.void" type="button" class="font-medium text-destructive hover:underline" @click="askVoid(expense)">{{ $t('expenses.void') }}</button>
                    </span>
                </span>
                <span :class="cn('font-semibold tabular-nums', expense.voided && 'text-muted-foreground line-through')">{{ expense.amount_label }}</span>
            </li>
        </ul>

        <div class="mt-4">
            <Pagination :meta="expenses" />
        </div>

        <ExpenseDialog v-model:open="dialogOpen" :expense="editing" :categories="categories" :pay-accounts="payAccounts" :today="today" :cash-limit="cashLimit" />
        <CategoriesDialog v-if="can.manageCategories" v-model:open="categoriesOpen" :categories="categories" :can-restrict="can.restricted" />

        <ConfirmDialog
            v-model:open="voidOpen"
            :title="$t('expenses.void_title', { amount: toVoid?.amount_label ?? '' })"
            :description="$t('expenses.void_description', { account: toVoid?.account_name ?? '' })"
            :confirm-label="$t('expenses.void')"
            destructive
            require-reason
            :processing="voiding"
            @confirm="voidExpense"
        />
    </AppLayout>
</template>
