<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import LinesEditor from '@/Components/Purchases/LinesEditor.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatRupiah } from '@/lib/money';
import { linePrice } from '@/lib/quantity';
import type { PayAccount, PurchaseLineInput } from '@/types/purchases';

const props = defineProps<{
    suppliers: { id: number; name: string }[];
    /** Accounts this user may pay from right away */
    payAccounts: PayAccount[];
    /** false: Front Desk, who may only pay small purchases in full from cash */
    canPayAnyAmount: boolean;
    cashLimit: number | null;
    today: string;
}>();

const lines = ref<PurchaseLineInput[]>([]);
const total = computed(() => lines.value.reduce((sum, line) => sum + linePrice(line.qty, line.unit_cost), 0));

const form = useForm({
    supplier_id: null as number | null,
    supplier_ref: '',
    purchase_date: props.today,
    due_date: '',
    notes: '',
    pay_now: false,
    pay_amount: null as number | null,
    pay_account_id: (props.payAccounts[0]?.id ?? null) as number | null,
    pay_reference: '',
});

const supplierOptions = computed(() => props.suppliers.map((supplier) => ({ value: supplier.id, label: supplier.name })));
const accountOptions = computed(() => props.payAccounts.map((account) => ({ value: account.id, label: account.name })));

// Front Desk pays the whole bill or nothing, and only up to the branch limit.
const overLimit = computed(() => !props.canPayAnyAmount && props.cashLimit !== null && total.value > props.cashLimit);
const payHint = computed(() => {
    if (props.canPayAnyAmount) {
        return trans('purchases.pay_now_hint');
    }

    return props.cashLimit === null ? trans('purchases.pay_now_cash_hint') : trans('purchases.pay_now_limit_hint', { limit: formatRupiah(props.cashLimit) });
});

// Paying in full is the usual case: start from the total.
watch(
    () => form.pay_now,
    (payNow) => {
        if (payNow && form.pay_amount === null) {
            form.pay_amount = total.value || null;
        }
    },
);

function submit(): void {
    form.transform((data) => ({
        supplier_id: data.supplier_id,
        supplier_ref: data.supplier_ref,
        purchase_date: data.purchase_date,
        due_date: data.due_date || null,
        notes: data.notes,
        lines: lines.value.map((line) => ({ part_id: line.part.id, qty: line.qty, unit_cost: line.unit_cost })),
        pay_amount: data.pay_now ? (props.canPayAnyAmount ? data.pay_amount : total.value) : null,
        pay_account_id: data.pay_now ? data.pay_account_id : null,
        pay_reference: data.pay_now ? data.pay_reference : null,
    }));
    form.post(route('purchases.store'));
}

const errors = computed(() => form.errors as Partial<Record<string, string>>);
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('purchases.create_title')" :description="$t('purchases.create_description')" />

        <form class="grid max-w-3xl gap-6" novalidate @submit.prevent="submit">
            <Card>
                <CardContent class="grid gap-5 pt-4 sm:grid-cols-2 sm:pt-6">
                    <FormField id="supplier_id" class="sm:col-span-2" :label="$t('purchases.fields.supplier')" :error="form.errors.supplier_id" required>
                        <template #default="{ invalid, describedBy }">
                            <Select
                                id="supplier_id"
                                v-model="form.supplier_id"
                                :options="supplierOptions"
                                :placeholder="$t('common.choose')"
                                :invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                            <p v-if="suppliers.length === 0" class="text-sm text-muted-foreground">
                                {{ $t('purchases.no_suppliers') }}
                                <Link :href="route('suppliers.index')" class="font-medium text-primary underline underline-offset-4">{{ $t('purchases.add_supplier') }}</Link>
                            </p>
                        </template>
                    </FormField>

                    <FormField id="purchase_date" :label="$t('purchases.fields.purchase_date')" :error="form.errors.purchase_date" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="purchase_date" v-model="form.purchase_date" type="date" :max="today" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="supplier_ref" :label="$t('purchases.fields.supplier_ref')" :hint="$t('purchases.supplier_ref_hint')" :error="form.errors.supplier_ref">
                        <template #default="{ invalid, describedBy }">
                            <Input id="supplier_ref" v-model="form.supplier_ref" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('purchases.goods') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <LinesEditor v-model="lines" :errors="errors" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('purchases.payment') }}</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-5">
                    <template v-if="payAccounts.length">
                        <Checkbox id="pay_now" v-model="form.pay_now" :label="canPayAnyAmount ? $t('purchases.pay_now') : $t('purchases.pay_now_full')" />
                        <p class="-mt-3 text-sm text-muted-foreground">{{ payHint }}</p>

                        <p v-if="form.pay_now && overLimit" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900" role="alert">
                            {{ $t('purchases.pay_over_limit', { limit: formatRupiah(cashLimit) }) }}
                        </p>

                        <div v-if="form.pay_now" class="grid gap-5 sm:grid-cols-2">
                            <FormField v-if="canPayAnyAmount" id="pay_amount" :label="$t('purchases.fields.pay_amount')" :error="form.errors.pay_amount" required>
                                <template #default="{ invalid, describedBy }">
                                    <MoneyInput id="pay_amount" v-model="form.pay_amount" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                            <p v-else-if="form.errors.pay_amount" class="text-sm font-medium text-destructive sm:col-span-2" role="alert">{{ form.errors.pay_amount }}</p>

                            <FormField id="pay_account_id" :label="$t('purchases.fields.pay_account')" :error="form.errors.pay_account_id" required>
                                <template #default="{ invalid, describedBy }">
                                    <Select id="pay_account_id" v-model="form.pay_account_id" :options="accountOptions" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>

                            <FormField v-if="canPayAnyAmount" id="pay_reference" :label="$t('purchases.fields.reference')" :hint="$t('purchases.reference_hint')" :error="form.errors.pay_reference">
                                <template #default="{ invalid, describedBy }">
                                    <Input id="pay_reference" v-model="form.pay_reference" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                        </div>
                    </template>

                    <FormField id="due_date" :label="$t('purchases.fields.due_date')" :hint="$t('purchases.due_date_hint')" :error="form.errors.due_date">
                        <template #default="{ invalid, describedBy }">
                            <Input id="due_date" v-model="form.due_date" type="date" :min="form.purchase_date" class="sm:max-w-xs" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="notes" :label="$t('purchases.fields.notes')" :error="form.errors.notes">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
                <Link :href="route('purchases.index')" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
                <Button type="submit" :loading="form.processing" :disabled="lines.length === 0">{{ $t('purchases.save', { total: formatRupiah(total) }) }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
