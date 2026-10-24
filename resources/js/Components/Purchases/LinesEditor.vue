<script setup lang="ts">
import { Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import PartSearch from '@/Components/Purchases/PartSearch.vue';
import { Button } from '@/Components/ui/button';
import FormField from '@/Components/ui/FormField.vue';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import QuantityInput from '@/Components/ui/QuantityInput.vue';
import { formatRupiah } from '@/lib/money';
import { linePrice } from '@/lib/quantity';
import type { PartOption, PurchaseLineInput } from '@/types/purchases';

/**
 * The goods received: part, quantity and purchase price per unit. The total shown is a
 * preview; the server calculates the real one.
 */
const props = defineProps<{ errors: Partial<Record<string, string>> }>();
const lines = defineModel<PurchaseLineInput[]>({ required: true });

const pickedIds = computed(() => lines.value.map((line) => line.part.id));

function add(part: PartOption): void {
    lines.value = [...lines.value, { key: `${part.id}-${Date.now()}`, part, qty: '', unit_cost: part.cost_price }];
}

function remove(index: number): void {
    lines.value = lines.value.filter((_, i) => i !== index);
}

const lineTotal = (line: PurchaseLineInput): number => linePrice(line.qty, line.unit_cost);
const total = computed(() => lines.value.reduce((sum, line) => sum + lineTotal(line), 0));

const error = (index: number, field: string): string | undefined => props.errors[`lines.${index}.${field}`];
</script>

<template>
    <div class="grid gap-3">
        <ol v-if="lines.length" class="grid gap-3">
            <li v-for="(line, index) in lines" :key="line.key" class="rounded-md border bg-card p-3">
                <div class="mb-3 flex items-start gap-2">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ line.part.name }}</span>
                        <span v-if="line.part.sku" class="block font-mono text-xs text-muted-foreground">{{ line.part.sku }}</span>
                        <span v-if="error(index, 'part_id')" class="block text-sm font-medium text-destructive" role="alert">{{ error(index, 'part_id') }}</span>
                    </span>
                    <Button type="button" variant="ghost" size="icon" :aria-label="$t('purchases.remove_line', { part: line.part.name })" @click="remove(index)">
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>

                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <FormField :id="`line-${index}-qty`" :label="$t('purchases.fields.qty')" :error="error(index, 'qty')" required>
                        <template #default="{ invalid, describedBy }">
                            <QuantityInput
                                :id="`line-${index}-qty`"
                                v-model="line.qty"
                                :allow-decimals="line.part.allows_decimals"
                                :unit="line.part.unit_label"
                                :invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                    <FormField :id="`line-${index}-cost`" :label="$t('purchases.fields.unit_cost')" :error="error(index, 'unit_cost')" required>
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput :id="`line-${index}-cost`" v-model="line.unit_cost" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <p class="col-span-2 text-right text-sm sm:col-span-1 sm:self-end sm:pb-2">
                        <span class="text-muted-foreground">{{ $t('purchases.line_total') }}</span>
                        <span class="ml-2 font-semibold tabular-nums">{{ formatRupiah(lineTotal(line)) }}</span>
                    </p>
                </div>
            </li>
        </ol>

        <FormField id="part-search" :label="lines.length ? $t('purchases.add_another_part') : $t('purchases.add_part')" :error="errors.lines">
            <template #default="{ invalid, describedBy }">
                <PartSearch id="part-search" :picked-ids="pickedIds" :invalid="invalid" :described-by="describedBy" @pick="add" />
            </template>
        </FormField>

        <div class="flex items-center justify-between rounded-md bg-muted px-3 py-3">
            <span class="font-medium">{{ $t('purchases.total') }}</span>
            <span class="text-lg font-semibold tabular-nums">{{ formatRupiah(total) }}</span>
        </div>
    </div>
</template>
