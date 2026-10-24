<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import QuantityInput from '@/Components/ui/QuantityInput.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import type { Part, UnitOption } from '@/types/inventory';

const props = defineProps<{
    units: UnitOption[];
    categories: string[];
    brands: string[];
    /** Existing part when editing */
    part?: Part;
    canViewCost: boolean;
    /** Opening stock needs stock.adjust (only when adding) */
    canAdjustStock?: boolean;
    cancelUrl: string;
}>();

const form = useForm({
    sku: props.part?.sku ?? '',
    name: props.part?.name ?? '',
    brand: props.part?.brand ?? '',
    category: props.part?.category ?? '',
    unit: props.part?.unit ?? 'pcs',
    cost_price: props.part?.cost_price ?? (null as number | null),
    sell_price: props.part?.sell_price ?? (null as number | null),
    is_active: props.part?.is_active ?? true,
    notes: props.part?.notes ?? '',
    opening_stock: '',
    min_stock: '',
    location: '',
});

const unit = computed(() => props.units.find((option) => option.value === form.unit));
const allowDecimals = computed(() => unit.value?.allows_decimals ?? false);

// Switching to a counted unit drops decimals that were typed for a measured unit.
watch(allowDecimals, (decimals) => {
    if (!decimals) {
        form.opening_stock = form.opening_stock.split(/[.,]/)[0] ?? '';
        form.min_stock = form.min_stock.split(/[.,]/)[0] ?? '';
    }
});

function submit(): void {
    form.transform((data) => {
        const payload: Record<string, unknown> = { ...data };

        if (!props.canViewCost) {
            delete payload.cost_price;
        }
        if (props.part) {
            // Stock fields are only sent when adding; later changes go through stock correction.
            delete payload.opening_stock;
            delete payload.min_stock;
            delete payload.location;
        } else if (!props.canAdjustStock) {
            delete payload.opening_stock;
        }

        return payload;
    });

    if (props.part) {
        form.put(route('parts.update', props.part.id));
    } else {
        form.post(route('parts.store'));
    }
}
</script>

<template>
    <form class="grid gap-5" novalidate @submit.prevent="submit">
        <FormField id="name" :label="$t('parts.fields.name')" :error="form.errors.name" required>
            <template #default="{ invalid, describedBy }">
                <Input id="name" v-model="form.name" autocomplete="off" autofocus placeholder="Oli Shell Helix 10W-40" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField id="sku" :label="$t('parts.fields.sku')" :hint="$t('parts.sku_hint')" :error="form.errors.sku">
                <template #default="{ invalid, describedBy }">
                    <Input id="sku" v-model="form.sku" autocomplete="off" autocapitalize="characters" class="font-mono uppercase" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="unit" :label="$t('parts.fields.unit')" :hint="allowDecimals ? $t('parts.unit_decimal_hint') : undefined" :error="form.errors.unit" required>
                <template #default="{ invalid, describedBy }">
                    <Select id="unit" v-model="form.unit" :options="units" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="brand" :label="$t('parts.fields.brand')" :error="form.errors.brand">
                <template #default="{ invalid, describedBy }">
                    <Input id="brand" v-model="form.brand" list="brand-suggestions" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    <datalist id="brand-suggestions"><option v-for="brand in brands" :key="brand" :value="brand" /></datalist>
                </template>
            </FormField>

            <FormField id="category" :label="$t('parts.fields.category')" :error="form.errors.category">
                <template #default="{ invalid, describedBy }">
                    <Input id="category" v-model="form.category" list="category-suggestions" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    <datalist id="category-suggestions"><option v-for="category in categories" :key="category" :value="category" /></datalist>
                </template>
            </FormField>

            <FormField v-if="canViewCost" id="cost_price" :label="$t('parts.fields.cost_price')" :hint="$t('parts.cost_hint')" :error="form.errors.cost_price">
                <template #default="{ invalid, describedBy }">
                    <MoneyInput id="cost_price" v-model="form.cost_price" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="sell_price" :label="$t('parts.fields.sell_price')" :error="form.errors.sell_price" required>
                <template #default="{ invalid, describedBy }">
                    <MoneyInput id="sell_price" v-model="form.sell_price" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>
        </div>

        <fieldset v-if="!part" class="grid gap-5 rounded-md border p-3 sm:grid-cols-3">
            <legend class="px-1 text-sm font-medium">{{ $t('parts.stock_in_branch') }}</legend>

            <FormField v-if="canAdjustStock" id="opening_stock" :label="$t('parts.fields.opening_stock')" :error="form.errors.opening_stock">
                <template #default="{ invalid, describedBy }">
                    <QuantityInput id="opening_stock" v-model="form.opening_stock" :allow-decimals="allowDecimals" :unit="unit?.label" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="min_stock" :label="$t('parts.fields.min_stock')" :hint="$t('parts.min_stock_hint')" :error="form.errors.min_stock">
                <template #default="{ invalid, describedBy }">
                    <QuantityInput id="min_stock" v-model="form.min_stock" :allow-decimals="allowDecimals" :unit="unit?.label" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="location" :label="$t('parts.fields.location')" :error="form.errors.location">
                <template #default="{ invalid, describedBy }">
                    <Input id="location" v-model="form.location" autocomplete="off" placeholder="Rak A3" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>
        </fieldset>

        <FormField id="notes" :label="$t('parts.fields.notes')" :error="form.errors.notes">
            <template #default="{ invalid, describedBy }">
                <Textarea id="notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <Checkbox v-if="part" id="is_active" v-model="form.is_active" :label="$t('parts.fields.is_active')" />

        <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
            <Link :href="cancelUrl" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
            <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
        </div>
    </form>
</template>
