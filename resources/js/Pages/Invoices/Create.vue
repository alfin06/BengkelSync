<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CustomerPicker from '@/Components/Customers/CustomerPicker.vue';
import WalkInLinesEditor from '@/Components/Invoices/WalkInLinesEditor.vue';
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
import { linePrice } from '@/lib/quantity';
import { cn } from '@/lib/utils';
import type { TaxSetting, WalkInLine, WorkOrderInvoiceLine } from '@/types/invoices';
import type { CatalogOption } from '@/types/workOrders';

const props = defineProps<{
    /** Set when invoicing a finished work order; null = walk-in sale */
    workOrder: { id: number; number: string; customer_name: string; vehicle: string; lines: WorkOrderInvoiceLine[] } | null;
    catalog: CatalogOption[];
    tax: TaxSetting;
    today: string;
    can: { discount: boolean; toggleTax: boolean };
}>();

// From a work order: quantities are fixed, prices and discounts can be adjusted.
const priced = ref(
    (props.workOrder?.lines ?? []).map((line) => ({ ...line, price: line.unit_price as number | null, discount: null as number | null })),
);
const walkIn = ref<WalkInLine[]>([]);
const customerMode = ref<'general' | 'customer'>('general');

const form = useForm({
    customer_id: null as number | null,
    issue_date: props.today,
    due_date: '',
    discount_amount: null as number | null,
    tax_enabled: props.tax.enabled,
    notes: '',
});

const lineTotals = computed(() =>
    props.workOrder
        ? priced.value.map((line) => Math.max(linePrice(line.qty, line.price) - (line.discount ?? 0), 0))
        : walkIn.value.map((line) => Math.max(linePrice(line.qty, line.unit_price) - (line.discount_amount ?? 0), 0)),
);
const taxOn = computed(() => (props.can.toggleTax ? form.tax_enabled : props.tax.enabled));
const totals = computed(() => invoiceTotals(lineTotals.value, form.discount_amount ?? 0, taxOn.value, props.tax.rate, props.tax.inclusive));
const taxRateLabel = computed(() => `${Number(props.tax.rate)}%`);
const hasLines = computed(() => (props.workOrder ? priced.value.length : walkIn.value.length) > 0);

function submit(): void {
    form.transform((data) => ({
        mode: props.workOrder ? 'work_order' : 'walk_in',
        work_order_id: props.workOrder?.id,
        customer_id: !props.workOrder && customerMode.value === 'customer' ? data.customer_id : null,
        issue_date: data.issue_date,
        due_date: data.due_date || null,
        notes: data.notes,
        discount_amount: data.discount_amount ?? 0,
        tax_enabled: props.can.toggleTax ? data.tax_enabled : null,
        lines: props.workOrder
            ? priced.value.map((line) => ({ source: line.source, id: line.id, unit_price: line.price, discount_amount: line.discount ?? 0 }))
            : walkIn.value.map((line) => ({
                  type: line.type,
                  part_id: line.part_id,
                  service_item_id: line.service_item_id,
                  description: line.description,
                  qty: line.qty,
                  unit_price: line.unit_price,
                  discount_amount: line.discount_amount ?? 0,
              })),
    }));
    form.post(route('invoices.store'));
}

const errors = computed(() => form.errors as Partial<Record<string, string>>);
const error = (index: number, field: string): string | undefined => errors.value[`lines.${index}.${field}`];
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="workOrder ? $t('invoices.create_from_work_order', { number: workOrder.number }) : $t('invoices.walk_in')"
            :description="workOrder ? `${workOrder.vehicle} · ${workOrder.customer_name}` : $t('invoices.walk_in_description')"
        />

        <form class="grid max-w-3xl gap-6" novalidate @submit.prevent="submit">
            <p v-if="errors.business" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">{{ errors.business }}</p>

            <Card v-if="!workOrder">
                <CardHeader><CardTitle>{{ $t('invoices.fields.customer') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-3">
                    <div class="grid grid-cols-2 gap-2" role="radiogroup">
                        <button
                            v-for="option in (['general', 'customer'] as const)"
                            :key="option"
                            type="button"
                            role="radio"
                            :aria-checked="customerMode === option"
                            :class="cn('min-h-11 rounded-md border text-sm font-medium', customerMode === option ? 'border-primary bg-primary/10 text-primary' : 'hover:bg-muted')"
                            @click="customerMode = option"
                        >
                            {{ option === 'general' ? $t('invoices.walk_in_customer') : $t('invoices.pick_customer') }}
                        </button>
                    </div>
                    <FormField v-if="customerMode === 'customer'" id="customer-search" :label="$t('work_orders.find_customer')" :error="errors.customer_id">
                        <CustomerPicker id="customer-search" v-model="form.customer_id" />
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('invoices.lines') }}</CardTitle></CardHeader>
                <CardContent>
                    <ol v-if="workOrder" class="grid gap-3">
                        <li v-for="(line, index) in priced" :key="`${line.source}-${line.id}`" class="rounded-md border p-3">
                            <p class="mb-2 text-sm font-medium">
                                {{ line.description }}
                                <span class="font-normal text-muted-foreground"> · {{ line.qty_label }} {{ line.unit_label ?? '' }}</span>
                            </p>
                            <div :class="cn('grid gap-3', can.discount ? 'grid-cols-2 sm:grid-cols-3' : 'grid-cols-2')">
                                <FormField :id="`price-${index}`" :label="$t('invoices.fields.unit_price')" :error="error(index, 'unit_price')" required>
                                    <template #default="{ invalid, describedBy }">
                                        <MoneyInput :id="`price-${index}`" v-model="line.price" :invalid="invalid" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                                <FormField v-if="can.discount" :id="`discount-${index}`" :label="$t('invoices.fields.discount')" :error="error(index, 'discount_amount')">
                                    <template #default="{ invalid, describedBy }">
                                        <MoneyInput :id="`discount-${index}`" v-model="line.discount" :invalid="invalid" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                                <p class="self-end pb-2 text-right text-sm font-semibold tabular-nums">{{ formatRupiah(lineTotals[index]) }}</p>
                            </div>
                        </li>
                    </ol>
                    <WalkInLinesEditor v-else v-model="walkIn" :catalog="catalog" :can-discount="can.discount" :errors="errors" />
                    <p v-if="workOrder" class="mt-3 text-sm text-muted-foreground">
                        {{ $t('invoices.fix_on_work_order') }}
                        <Link :href="route('work-orders.show', workOrder.id)" class="font-medium text-primary underline-offset-4 hover:underline">{{ workOrder.number }}</Link>
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardContent class="grid gap-5 pt-4 sm:grid-cols-2 sm:pt-6">
                    <FormField id="issue_date" :label="$t('invoices.fields.issue_date')" :error="form.errors.issue_date" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="issue_date" v-model="form.issue_date" type="date" :max="today" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="due_date" :label="$t('invoices.fields.due_date')" :hint="$t('invoices.due_date_hint')" :error="form.errors.due_date">
                        <template #default="{ invalid, describedBy }">
                            <Input id="due_date" v-model="form.due_date" type="date" :min="form.issue_date" :invalid="invalid" :aria-describedby="describedBy" />
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
                    <FormField id="notes" class="sm:col-span-2" :label="$t('invoices.fields.notes')" :hint="$t('invoices.notes_hint')" :error="form.errors.notes">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
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
                    <div class="flex justify-between border-t pt-2 text-lg font-semibold"><span>{{ $t('invoices.total') }}</span><span class="tabular-nums">{{ formatRupiah(totals.total) }}</span></div>
                </CardContent>
            </Card>

            <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
                <Link :href="workOrder ? route('work-orders.show', workOrder.id) : route('invoices.index')" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
                <Button type="submit" :loading="form.processing" :disabled="!hasLines">{{ $t('invoices.save', { total: formatRupiah(totals.total) }) }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
