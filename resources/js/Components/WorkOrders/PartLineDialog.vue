<script setup lang="ts">
import axios from 'axios';
import { router, useForm } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Search, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import QuantityInput from '@/Components/ui/QuantityInput.vue';
import { formatRupiah } from '@/lib/money';
import { linePrice, quantityInput } from '@/lib/quantity';
import { cn } from '@/lib/utils';
import type { WorkOrderPartLine } from '@/types/workOrders';

interface PartResult {
    id: number;
    name: string;
    sku: string | null;
    unit_label: string;
    allows_decimals: boolean;
    sell_price: number;
    /** Hundredths on the shelf in this branch */
    available: number;
    available_label: string;
}

/**
 * Add a part (it leaves the shelf right away) or change its quantity or price.
 * Out-of-stock parts cannot be picked.
 */
const props = defineProps<{ workOrderId: number; line: WorkOrderPartLine | null }>();
const open = defineModel<boolean>('open', { default: false });

const picked = ref<PartResult | null>(null);
const form = useForm({ part_id: null as number | null, qty: '', unit_price: null as number | null });

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    picked.value = null;
    term.value = '';
    results.value = [];
    form.defaults({
        part_id: null,
        qty: props.line ? quantityInput(props.line.qty) : '1',
        unit_price: props.line?.unit_price ?? null,
    });
    form.reset();
    form.clearErrors();
});

const term = ref('');
const results = ref<PartResult[]>([]);
const searching = ref(false);

const search = useDebounceFn(async () => {
    if (term.value.trim().length < 2) {
        results.value = [];

        return;
    }

    searching.value = true;
    try {
        const { data } = await axios.get<PartResult[]>(route('parts.lookup'), { params: { q: term.value } });
        results.value = data;
    } finally {
        searching.value = false;
    }
}, 300);

function pick(part: PartResult): void {
    picked.value = part;
    form.part_id = part.id;
    form.unit_price = part.sell_price;
    if (!part.allows_decimals) {
        form.qty = form.qty.split(/[.,]/)[0] || '1';
    }
}

const unitLabel = computed(() => props.line?.unit_label ?? picked.value?.unit_label);
const allowsDecimals = computed(() => props.line?.allows_decimals ?? picked.value?.allows_decimals ?? false);
const total = computed(() => linePrice(form.qty, form.unit_price));

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.line) {
        form.transform(({ qty, unit_price }) => ({ qty, unit_price })).put(route('work-orders.parts.update', [props.workOrderId, props.line.id]), options);
    } else {
        form.transform((data) => data).post(route('work-orders.parts.store', props.workOrderId), options);
    }
}

const removing = ref(false);

function remove(): void {
    if (!props.line) {
        return;
    }

    router.delete(route('work-orders.parts.destroy', [props.workOrderId, props.line.id]), {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ line ? line.part_name : $t('work_orders.add_part') }}</DialogTitle>
                    <DialogDescription>{{ line ? $t('work_orders.edit_part_hint') : $t('work_orders.add_part_hint') }}</DialogDescription>
                </DialogHeader>

                <template v-if="!line">
                    <div v-if="picked" class="flex items-center gap-3 rounded-md border bg-muted/40 p-3 text-sm">
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium">{{ picked.name }}</span>
                            <span class="block text-muted-foreground">{{ $t('work_orders.in_stock', { qty: picked.available_label, unit: picked.unit_label }) }}</span>
                        </span>
                        <button type="button" class="font-medium text-primary underline-offset-4 hover:underline" @click="picked = null; form.part_id = null">{{ $t('work_orders.change') }}</button>
                    </div>

                    <FormField v-else id="part-search" :label="$t('work_orders.fields.part')" :error="form.errors.part_id">
                        <template #default="{ invalid, describedBy }">
                            <div class="relative">
                                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                <input
                                    id="part-search"
                                    v-model="term"
                                    type="search"
                                    autocomplete="off"
                                    :placeholder="$t('purchases.search_part')"
                                    :aria-invalid="invalid || undefined"
                                    :aria-describedby="describedBy"
                                    class="h-11 w-full rounded-md border border-input bg-background pl-9 pr-3 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-10 sm:text-sm"
                                    @input="search"
                                />
                            </div>
                            <p v-if="searching" class="text-sm text-muted-foreground" role="status">{{ $t('common.searching') }}</p>
                            <ul v-if="results.length" class="max-h-64 divide-y overflow-y-auto rounded-md border">
                                <li v-for="part in results" :key="part.id">
                                    <button
                                        type="button"
                                        :disabled="part.available <= 0"
                                        class="flex min-h-12 w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="pick(part)"
                                    >
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium">{{ part.name }}</span>
                                            <span :class="cn('block text-xs', part.available <= 0 ? 'font-medium text-destructive' : 'text-muted-foreground')">
                                                {{ part.available <= 0 ? $t('work_orders.out_of_stock') : $t('work_orders.in_stock', { qty: part.available_label, unit: part.unit_label }) }}
                                            </span>
                                        </span>
                                        <span class="tabular-nums">{{ formatRupiah(part.sell_price) }}</span>
                                    </button>
                                </li>
                            </ul>
                        </template>
                    </FormField>
                </template>

                <div v-if="line || picked" class="grid grid-cols-2 gap-3">
                    <FormField id="part-qty" :label="$t('work_orders.fields.qty')" :error="form.errors.qty" required>
                        <template #default="{ invalid, describedBy }">
                            <QuantityInput id="part-qty" v-model="form.qty" :allow-decimals="allowsDecimals" :unit="unitLabel" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="part-price" :label="$t('work_orders.fields.price')" :error="form.errors.unit_price" required>
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput id="part-price" v-model="form.unit_price" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <p class="col-span-2 text-right text-sm">
                        <span class="text-muted-foreground">{{ $t('purchases.line_total') }}</span>
                        <span class="ml-2 font-semibold tabular-nums">{{ formatRupiah(total) }}</span>
                    </p>
                </div>

                <DialogFooter class="sm:justify-between">
                    <Button v-if="line" type="button" variant="ghost" class="text-destructive" :loading="removing" @click="remove">
                        <Trash2 aria-hidden="true" /> {{ $t('work_orders.return_part') }}
                    </Button>
                    <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="form.processing" :disabled="!line && !picked">{{ $t('common.save') }}</Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
