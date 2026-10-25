<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InvoiceLinesEditor from '@/Components/Invoices/InvoiceLinesEditor.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { invoiceTotals } from '@/lib/invoice';
import { formatRupiah } from '@/lib/money';
import { linePrice, quantityInput } from '@/lib/quantity';
import type { Invoice, InvoiceLineInput, TaxSetting } from '@/types/invoices';
import type { CatalogOption } from '@/types/workOrders';

/**
 * Edit an issued invoice (SPEC section 6): always with a reason. The number stays; the
 * previous version is kept as a revision. Parts follow the stock when saved.
 */
const props = defineProps<{
    invoice: Invoice;
    catalog: CatalogOption[];
    /** The invoice's own tax setting (kept on edits) */
    tax: TaxSetting;
    amountPaid: number;
    can: { discount: boolean; toggleTax: boolean };
}>();

const lines = ref<InvoiceLineInput[]>(
    (props.invoice.lines ?? []).map((line) => ({
        key: String(line.id),
        id: line.id,
        type: line.type,
        part_id: line.part_id,
        service_item_id: line.service_item_id,
        description: line.description,
        part_name: line.part_name,
        unit_label: line.unit_label,
        allows_decimals: line.allows_decimals,
        available: null,
        qty: quantityInput(line.qty),
        unit_price: line.unit_price,
        discount_amount: line.discount_amount || null,
    })),
);

const form = useForm({
    due_date: props.invoice.due_date ?? '',
    discount_amount: (props.invoice.discount_amount || null) as number | null,
    tax_enabled: props.tax.enabled,
    notes: props.invoice.notes ?? '',
    reason: '',
});

const lineTotals = computed(() => lines.value.map((line) => Math.max(linePrice(line.qty, line.unit_price) - (line.discount_amount ?? 0), 0)));
const taxOn = computed(() => (props.can.toggleTax ? form.tax_enabled : props.tax.enabled));
const totals = computed(() => invoiceTotals(lineTotals.value, form.discount_amount ?? 0, taxOn.value, props.tax.rate, props.tax.inclusive));
const belowPaid = computed(() => totals.value.total < props.amountPaid);
const taxRateLabel = computed(() => `${Number(props.tax.rate)}%`);

function submit(): void {
    form.transform((data) => ({
        due_date: data.due_date || null,
        notes: data.notes,
        discount_amount: data.discount_amount ?? 0,
        tax_enabled: props.can.toggleTax ? data.tax_enabled : null,
        reason: data.reason,
        lines: lines.value.map((line) => ({
            id: line.id ?? null,
            type: line.type,
            part_id: line.part_id,
            service_item_id: line.service_item_id,
            description: line.description,
            qty: line.qty,
            unit_price: line.unit_price,
            discount_amount: line.discount_amount ?? 0,
        })),
    }));
    form.put(route('invoices.update', props.invoice.id));
}

const errors = computed(() => form.errors as Partial<Record<string, string>>);
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('invoices.edit_title', { number: invoice.number })" :description="invoice.customer?.name ?? $t('invoices.walk_in_customer')" />

        <form class="grid max-w-3xl gap-6" novalidate @submit.prevent="submit">
            <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $t('invoices.edit_warning') }}</p>
            <p v-if="errors.business" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ errors.business }}</p>

            <Card>
                <CardHeader><CardTitle>{{ $t('invoices.lines') }}</CardTitle></CardHeader>
                <CardContent>
                    <InvoiceLinesEditor v-model="lines" :catalog="catalog" :can-discount="can.discount" :errors="errors" />
                </CardContent>
            </Card>

            <Card>
                <CardContent class="grid gap-5 pt-4 sm:grid-cols-2 sm:pt-6">
                    <FormField id="due_date" :label="$t('invoices.fields.due_date')" :error="form.errors.due_date">
                        <template #default="{ invalid, describedBy }">
                            <Input id="due_date" v-model="form.due_date" type="date" :min="invoice.issue_date" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField v-if="can.discount" id="discount_amount" :label="$t('invoices.fields.invoice_discount')" :error="form.errors.discount_amount">
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput id="discount_amount" v-model="form.discount_amount" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <div v-if="can.toggleTax" class="flex items-end">
                        <Checkbox id="tax_enabled" v-model="form.tax_enabled" :label="$t('invoices.use_tax', { name: tax.name, rate: taxRateLabel })" />
                    </div>
                    <FormField id="notes" class="sm:col-span-2" :label="$t('invoices.fields.notes')" :error="form.errors.notes">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="reason" class="sm:col-span-2" :label="$t('common.reason')" :hint="$t('invoices.reason_hint')" :error="form.errors.reason" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="reason" v-model="form.reason" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="grid gap-2 pt-4 text-sm sm:pt-6">
                    <div class="flex justify-between"><span>{{ $t('invoices.subtotal') }}</span><span class="tabular-nums">{{ formatRupiah(totals.subtotal) }}</span></div>
                    <div v-if="totals.discount > 0" class="flex justify-between"><span>{{ $t('invoices.fields.invoice_discount') }}</span><span class="tabular-nums">−{{ formatRupiah(totals.discount) }}</span></div>
                    <div v-if="taxOn" class="flex justify-between text-muted-foreground">
                        <span>{{ tax.inclusive ? $t('invoices.tax_included', { name: tax.name, rate: taxRateLabel }) : `${tax.name} ${taxRateLabel}` }}</span>
                        <span class="tabular-nums">{{ formatRupiah(totals.tax) }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 text-lg font-semibold"><span>{{ $t('invoices.new_total') }}</span><span class="tabular-nums">{{ formatRupiah(totals.total) }}</span></div>
                    <div class="flex justify-between text-muted-foreground"><span>{{ $t('invoices.old_total') }}</span><span class="tabular-nums">{{ invoice.total_label }}</span></div>
                    <p v-if="belowPaid" class="text-sm font-medium text-destructive" role="alert">{{ $t('invoices.total_below_paid', { paid: formatRupiah(amountPaid) }) }}</p>
                </CardContent>
            </Card>

            <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
                <Link :href="route('invoices.show', invoice.id)" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
                <Button type="submit" :loading="form.processing" :disabled="lines.length === 0 || belowPaid">{{ $t('invoices.save_revision') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
