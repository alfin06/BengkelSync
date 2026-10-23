<script setup lang="ts">
import { ref, watch, type HTMLAttributes } from 'vue';
import { formatThousands, parseRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

/**
 * Rupiah input that formats as you type ("1.250.000") and emits an integer.
 * Opens the numeric keypad on phones.
 */
const props = defineProps<{
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<number | null>({ default: null });
const display = ref(formatThousands(model.value));

watch(model, (value) => {
    if (parseRupiah(display.value) !== value) {
        display.value = formatThousands(value);
    }
});

function onInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    const value = parseRupiah(input.value);

    model.value = value;
    display.value = formatThousands(value);
    // Write back directly: when a letter is typed the formatted text does not change,
    // so Vue would not re-render and the letter would stay visible.
    input.value = display.value;
}
</script>

<template>
    <div :class="cn('relative', props.class)">
        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-base text-muted-foreground sm:text-sm">Rp</span>
        <input
            v-bind="$attrs"
            :value="display"
            type="text"
            inputmode="numeric"
            autocomplete="off"
            :aria-invalid="props.invalid || undefined"
            :class="
                cn(
                    'flex h-11 w-full rounded-md border border-input bg-background py-2 pl-10 pr-3 text-right text-base tabular-nums shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 sm:h-10 sm:text-sm',
                    props.invalid && 'border-destructive focus-visible:ring-destructive',
                )
            "
            @input="onInput"
        />
    </div>
</template>
