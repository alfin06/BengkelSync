<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import LinesEditor from '@/Components/Purchases/LinesEditor.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatRupiah } from '@/lib/money';
import { linePrice, quantityInput } from '@/lib/quantity';
import type { Purchase, PurchaseLineInput } from '@/types/purchases';

const props = defineProps<{ purchase: Purchase }>();

// Revising keeps the purchase number and payments; stock moves by the difference.
const lines = ref<PurchaseLineInput[]>(
    (props.purchase.lines ?? []).map((line) => ({
        key: String(line.id),
        part: { id: line.part_id, name: line.part_name, sku: line.sku, unit_label: line.unit_label, allows_decimals: line.allows_decimals, cost_price: null },
        qty: quantityInput(line.qty),
        unit_cost: line.unit_cost,
    })),
);
const total = computed(() => lines.value.reduce((sum, line) => sum + linePrice(line.qty, line.unit_cost), 0));

const form = useForm({
    supplier_ref: props.purchase.supplier_ref ?? '',
    due_date: props.purchase.due_date ?? '',
    notes: props.purchase.notes ?? '',
    reason: '',
});

function submit(): void {
    form.transform((data) => ({
        ...data,
        due_date: data.due_date || null,
        lines: lines.value.map((line) => ({ part_id: line.part.id, qty: line.qty, unit_cost: line.unit_cost })),
    }));
    form.put(route('purchases.update', props.purchase.id));
}

const errors = computed(() => form.errors as Partial<Record<string, string>>);
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('purchases.edit_title', { number: purchase.number })" :description="purchase.supplier.name" />

        <form class="grid max-w-3xl gap-6" novalidate @submit.prevent="submit">
            <p class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $t('purchases.edit_warning') }}</p>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('purchases.goods') }}</CardTitle>
                </CardHeader>
                <CardContent>
                    <LinesEditor v-model="lines" :errors="errors" />
                </CardContent>
            </Card>

            <Card>
                <CardContent class="grid gap-5 pt-4 sm:grid-cols-2 sm:pt-6">
                    <FormField id="supplier_ref" :label="$t('purchases.fields.supplier_ref')" :error="form.errors.supplier_ref">
                        <template #default="{ invalid, describedBy }">
                            <Input id="supplier_ref" v-model="form.supplier_ref" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="due_date" :label="$t('purchases.fields.due_date')" :error="form.errors.due_date">
                        <template #default="{ invalid, describedBy }">
                            <Input id="due_date" v-model="form.due_date" type="date" :min="purchase.purchase_date" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="notes" class="sm:col-span-2" :label="$t('purchases.fields.notes')" :error="form.errors.notes">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="reason" class="sm:col-span-2" :label="$t('common.reason')" :hint="$t('purchases.reason_hint')" :error="form.errors.reason" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="reason" v-model="form.reason" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
                <Link :href="route('purchases.show', purchase.id)" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
                <Button type="submit" :loading="form.processing" :disabled="lines.length === 0">{{ $t('purchases.save', { total: formatRupiah(total) }) }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
