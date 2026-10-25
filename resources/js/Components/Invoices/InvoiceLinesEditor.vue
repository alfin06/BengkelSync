<script setup lang="ts">
import axios from 'axios';
import { useDebounceFn } from '@vueuse/core';
import { Package, Plus, Search, Trash2, Wrench } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import QuantityInput from '@/Components/ui/QuantityInput.vue';
import Select from '@/Components/ui/Select.vue';
import { formatRupiah } from '@/lib/money';
import { linePrice } from '@/lib/quantity';
import { cn } from '@/lib/utils';
import type { InvoiceLineInput } from '@/types/invoices';
import type { CatalogOption } from '@/types/workOrders';

interface PartResult {
    id: number;
    name: string;
    sku: string | null;
    unit_label: string;
    allows_decimals: boolean;
    sell_price: number;
    available: number;
    available_label: string;
}

/**
 * Invoice lines being typed (a counter sale, an invoice being edited, or the jobs of a work
 * order being invoiced): parts (stock follows when saved), services and other charges.
 * Totals shown are a preview; the server calculates the real ones.
 */
const props = withDefaults(
    defineProps<{
        catalog: CatalogOption[];
        canDiscount: boolean;
        errors: Partial<Record<string, string>>;
        /** false: only jobs and other charges (a work order's parts are listed separately) */
        withParts?: boolean;
    }>(),
    { withParts: true },
);
const lines = defineModel<InvoiceLineInput[]>({ required: true });

const key = (): string => `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;

// Part search
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

function addPart(part: PartResult): void {
    lines.value = [
        ...lines.value,
        {
            key: key(),
            type: 'part',
            part_id: part.id,
            service_item_id: null,
            description: part.name,
            part_name: part.name,
            unit_label: part.unit_label,
            allows_decimals: part.allows_decimals,
            available: part.available,
            qty: '1',
            unit_price: part.sell_price,
            discount_amount: null,
        },
    ];
    term.value = '';
    results.value = [];
}

function addLine(type: 'service' | 'other'): void {
    lines.value = [
        ...lines.value,
        { key: key(), type, part_id: null, service_item_id: null, description: '', unit_label: null, allows_decimals: false, available: null, qty: '1', unit_price: null, discount_amount: null },
    ];
}

function pickCatalog(line: InvoiceLineInput, value: string | number | null | undefined): void {
    const item = props.catalog.find((option) => option.id === Number(value));
    line.service_item_id = item?.id ?? null;
    if (item) {
        line.description = item.name;
        line.unit_price = item.default_price;
    }
}

function remove(index: number): void {
    lines.value = lines.value.filter((_, i) => i !== index);
}

const error = (index: number, field: string): string | undefined => props.errors[`lines.${index}.${field}`];
const lineTotal = (line: InvoiceLineInput): number => Math.max(linePrice(line.qty, line.unit_price) - (line.discount_amount ?? 0), 0);
const catalogOptions = props.catalog.map((item) => ({ value: item.id, label: `${item.name} · ${formatRupiah(item.default_price)}` }));
</script>

<template>
    <div class="grid gap-3">
        <ol v-if="lines.length" class="grid gap-3">
            <li v-for="(line, index) in lines" :key="line.key" class="rounded-md border bg-card p-3">
                <div class="mb-3 flex items-start gap-2">
                    <component :is="line.type === 'part' ? Package : Wrench" class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="min-w-0 flex-1 text-sm font-medium">
                        {{ line.type === 'part' ? (line.part_name ?? line.description) : $t(`invoices.line_type.${line.type}`) }}
                    </span>
                    <Button type="button" variant="ghost" size="icon" :aria-label="$t('invoices.remove_line')" @click="remove(index)"><Trash2 aria-hidden="true" /></Button>
                </div>

                <div class="grid gap-3">
                    <FormField v-if="line.type === 'service' && catalog.length" :id="`line-${index}-catalog`" :label="$t('work_orders.from_catalog')">
                        <Select :id="`line-${index}-catalog`" :model-value="line.service_item_id" :options="catalogOptions" :placeholder="$t('common.choose')" @update:model-value="(value) => pickCatalog(line, value)" />
                    </FormField>
                    <FormField
                        :id="`line-${index}-description`"
                        :label="line.type === 'part' ? $t('invoices.fields.name_on_invoice') : $t('invoices.fields.description')"
                        :hint="line.type === 'part' ? $t('invoices.part_name_hint') : undefined"
                        :error="error(index, 'description')"
                        :required="line.type !== 'part'"
                    >
                        <template #default="{ invalid, describedBy }">
                            <Input :id="`line-${index}-description`" v-model="line.description" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <div :class="cn('grid gap-3', canDiscount ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-2 sm:grid-cols-3')">
                        <FormField :id="`line-${index}-qty`" :label="$t('invoices.fields.qty')" :error="error(index, 'qty')" required>
                            <template #default="{ invalid, describedBy }">
                                <QuantityInput :id="`line-${index}-qty`" v-model="line.qty" :allow-decimals="line.allows_decimals" :unit="line.unit_label ?? undefined" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <FormField :id="`line-${index}-price`" :label="$t('invoices.fields.unit_price')" :error="error(index, 'unit_price')" required>
                            <template #default="{ invalid, describedBy }">
                                <MoneyInput :id="`line-${index}-price`" v-model="line.unit_price" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <FormField v-if="canDiscount" :id="`line-${index}-discount`" :label="$t('invoices.fields.discount')" :error="error(index, 'discount_amount')">
                            <template #default="{ invalid, describedBy }">
                                <MoneyInput :id="`line-${index}-discount`" v-model="line.discount_amount" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <p class="col-span-2 self-end pb-2 text-right text-sm sm:col-span-1">
                            <span class="font-semibold tabular-nums">{{ formatRupiah(lineTotal(line)) }}</span>
                        </p>
                    </div>
                </div>
            </li>
        </ol>

        <p v-if="errors.lines" class="text-sm font-medium text-destructive" role="alert">{{ errors.lines }}</p>

        <div v-if="withParts" class="grid gap-2">
            <label for="invoice-part-search" class="text-sm font-medium">{{ $t('invoices.add_part') }}</label>
            <div class="relative">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                <input
                    id="invoice-part-search"
                    v-model="term"
                    type="search"
                    autocomplete="off"
                    :placeholder="$t('purchases.search_part')"
                    class="h-11 w-full rounded-md border border-input bg-background pl-9 pr-3 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-10 sm:text-sm"
                    @input="search"
                />
            </div>
            <p v-if="searching" class="text-sm text-muted-foreground" role="status">{{ $t('common.searching') }}</p>
            <ul v-if="results.length" class="max-h-64 divide-y overflow-y-auto rounded-md border bg-card">
                <li v-for="part in results" :key="part.id">
                    <button
                        type="button"
                        :disabled="part.available <= 0"
                        class="flex min-h-12 w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                        @click="addPart(part)"
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
        </div>

        <div class="flex flex-wrap gap-2">
            <Button type="button" variant="outline" size="sm" @click="addLine('service')"><Plus aria-hidden="true" /> {{ $t('invoices.add_service') }}</Button>
            <Button type="button" variant="outline" size="sm" @click="addLine('other')"><Plus aria-hidden="true" /> {{ $t('invoices.add_other') }}</Button>
        </div>
    </div>
</template>
