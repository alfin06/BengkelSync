<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

/**
 * Stock quantity input. Measured units (liter, meter, kg) accept up to 2 decimals typed
 * with a comma or a dot ("4,2"); counted units (pcs, set) only accept whole numbers.
 * The server validates again (App\Rules\StockQuantity).
 */
const props = withDefaults(
    defineProps<{
        allowDecimals?: boolean;
        unit?: string;
        invalid?: boolean;
        class?: HTMLAttributes['class'];
    }>(),
    { allowDecimals: false, unit: undefined, invalid: false, class: undefined },
);

const model = defineModel<string>({ default: '' });

function onInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    let value = input.value.replace(/[^\d.,]/g, '');

    if (!props.allowDecimals) {
        value = value.replace(/[.,]/g, '');
    } else {
        const separator = value.search(/[.,]/);
        if (separator !== -1) {
            // One separator, at most 2 decimals.
            value = value.slice(0, separator + 1) + value.slice(separator + 1).replace(/[.,]/g, '').slice(0, 2);
        }
    }

    model.value = value;
    input.value = value;
}
</script>

<template>
    <div :class="cn('relative', props.class)">
        <input
            v-bind="$attrs"
            :value="model"
            type="text"
            :inputmode="allowDecimals ? 'decimal' : 'numeric'"
            autocomplete="off"
            :aria-invalid="invalid || undefined"
            :class="
                cn(
                    'flex h-11 w-full rounded-md border border-input bg-background py-2 pl-3 text-right text-base tabular-nums shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 sm:h-10 sm:text-sm',
                    unit ? 'pr-16' : 'pr-3',
                    invalid && 'border-destructive focus-visible:ring-destructive',
                )
            "
            @input="onInput"
        />
        <span v-if="unit" class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">{{ unit }}</span>
    </div>
</template>
