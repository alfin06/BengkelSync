<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Camera } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import { formatRupiah } from '@/lib/money';
import type { ExpenseCategoryOption, ExpenseRow } from '@/types/expenses';
import type { SelectOption } from '@/types/ui';

/**
 * Record an expense (amount and account only here) or edit its details. On a phone the
 * receipt field offers the camera or the gallery.
 */
const props = defineProps<{
    expense: ExpenseRow | null;
    categories: ExpenseCategoryOption[];
    payAccounts: SelectOption[];
    today: string;
    /** Front Desk: cash only, up to this amount; null = no limit */
    cashLimit: number | null;
}>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    expense_date: '',
    category_id: null as number | null,
    description: '',
    amount: null as number | null,
    cash_account_id: null as number | null,
    receipt: null as File | null,
    remove_receipt: false,
});
const fileInput = ref<HTMLInputElement | null>(null);

// Active categories, plus the current one when editing an expense in a switched-off category.
const categoryOptions = computed(() =>
    props.categories.filter((category) => category.is_active || category.id === props.expense?.category_id).map((category) => ({ value: category.id, label: category.name })),
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.defaults({
        expense_date: props.expense?.expense_date ?? props.today,
        category_id: props.expense?.category_id ?? null,
        description: props.expense?.description ?? '',
        amount: null,
        cash_account_id: (props.payAccounts[0]?.value as number | undefined) ?? null,
        receipt: null,
        remove_receipt: false,
    });
    form.reset();
    form.clearErrors();
    if (fileInput.value) {
        fileInput.value.value = '';
    }
});

function pickFile(event: Event): void {
    form.receipt = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit(): void {
    const options = { preserveScroll: true, forceFormData: true, onSuccess: () => (open.value = false) };

    if (props.expense) {
        // Uploads need POST; Laravel reads the real method from _method.
        form.transform(({ amount: _amount, cash_account_id: _account, ...data }) => ({ ...data, _method: 'put' })).post(route('expenses.update', props.expense.id), options);
    } else {
        form.transform(({ remove_receipt: _remove, ...data }) => data).post(route('expenses.store'), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ expense ? $t('expenses.edit_title') : $t('expenses.add_title') }}</DialogTitle>
                    <DialogDescription v-if="expense">{{ $t('expenses.edit_hint') }}</DialogDescription>
                    <DialogDescription v-else-if="cashLimit !== null">{{ $t('expenses.limit_hint', { limit: formatRupiah(cashLimit) }) }}</DialogDescription>
                </DialogHeader>

                <FormField id="expense-category" :label="$t('expenses.fields.category')" :error="form.errors.category_id" required>
                    <template #default="{ invalid, describedBy }">
                        <Select id="expense-category" v-model="form.category_id" :options="categoryOptions" :placeholder="$t('common.choose')" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="expense-description" :label="$t('expenses.fields.description')" :error="form.errors.description" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="expense-description" v-model="form.description" autocomplete="off" :placeholder="$t('expenses.description_placeholder')" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div v-if="!expense" class="grid grid-cols-2 gap-3">
                    <FormField id="expense-amount" :label="$t('expenses.fields.amount')" :error="form.errors.amount" required>
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput id="expense-amount" v-model="form.amount" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="expense-account" :label="$t('expenses.fields.account')" :error="form.errors.cash_account_id" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="expense-account" v-model="form.cash_account_id" :options="payAccounts" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <FormField id="expense-date" :label="$t('expenses.fields.date')" :error="form.errors.expense_date" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="expense-date" v-model="form.expense_date" type="date" :max="today" class="sm:max-w-xs" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="expense-receipt" :label="$t('expenses.fields.receipt')" :hint="$t('expenses.receipt_hint')" :error="form.errors.receipt">
                    <template #default="{ describedBy }">
                        <label
                            for="expense-receipt"
                            class="flex min-h-11 cursor-pointer items-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm hover:bg-muted/50"
                        >
                            <Camera class="size-5 text-muted-foreground" aria-hidden="true" />
                            <span class="truncate">{{ form.receipt?.name ?? $t('expenses.fields.receipt') }}</span>
                        </label>
                        <input
                            id="expense-receipt"
                            ref="fileInput"
                            type="file"
                            accept="image/*,application/pdf"
                            class="sr-only"
                            :aria-describedby="describedBy"
                            @change="pickFile"
                        />
                    </template>
                </FormField>
                <Checkbox v-if="expense?.has_receipt && !form.receipt" id="expense-remove-receipt" v-model="form.remove_receipt" :label="$t('expenses.remove_receipt')" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
