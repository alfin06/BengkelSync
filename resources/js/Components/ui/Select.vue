<script setup lang="ts">
import { useMediaQuery } from '@vueuse/core';
import { Check, ChevronDown } from 'lucide-vue-next';
import {
    ComboboxAnchor,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxItemIndicator,
    ComboboxPortal,
    ComboboxRoot,
    ComboboxTrigger,
    ComboboxViewport,
} from 'reka-ui';
import { computed, ref, useAttrs, watch, type HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types/ui';

defineOptions({ inheritAttrs: false });

/**
 * A dropdown the user can type into to filter the options (helpful for long lists such
 * as suppliers or cash accounts). Attributes like id, aria-label, aria-describedby and
 * disabled go to the text field, so FormField labels and hints keep working.
 *
 * On touch screens a short list opens without the keyboard: typing only helps when
 * there are many options, and the keyboard would cover a short list.
 */
const props = defineProps<{
    options: SelectOption[];
    placeholder?: string;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

const emit = defineEmits<{ change: [value: string | number | null] }>();
const model = defineModel<string | number | null>();
const attrs = useAttrs();

const SHORT_LIST = 6;
const touch = useMediaQuery('(pointer: coarse)');
const typeable = computed(() => !touch.value || props.options.length > SHORT_LIST);
const disabled = computed(() => attrs.disabled === true || attrs.disabled === '' || attrs.disabled === 'disabled');

// The text in the field: the chosen option's label, or what the user types. Only typed
// text filters, so opening the list always shows every option first.
const text = ref('');
const typed = ref(false);
const open = ref(false);

const filtered = computed(() => {
    const term = typed.value ? text.value.trim().toLocaleLowerCase('id') : '';

    return term === '' ? props.options : props.options.filter((option) => option.label.toLocaleLowerCase('id').includes(term));
});

function labelOf(value: unknown): string {
    return props.options.find((option) => option.value === value)?.label ?? '';
}

// Labels can change after the first render (translations loading, options from the
// server): keep the field showing the current label while the list is closed.
watch(
    () => labelOf(model.value),
    (label) => {
        if (!open.value) {
            text.value = label;
        }
    },
);

function select(value: unknown): void {
    const next = value as string | number | null;

    if (next === model.value) {
        return;
    }

    model.value = next;
    emit('change', next);
}

function onOpen(isOpen: boolean): void {
    open.value = isOpen;
    typed.value = false;
}

function selectText(event: FocusEvent): void {
    // Typing then replaces the current label instead of adding to it.
    if (typeable.value) {
        (event.target as HTMLInputElement).select();
    }
}
</script>

<template>
    <ComboboxRoot
        :model-value="model"
        :open="open"
        :disabled="disabled"
        ignore-filter
        open-on-click
        :class="cn('relative', props.class)"
        @update:model-value="select"
        @update:open="onOpen"
    >
        <ComboboxAnchor class="relative">
            <ComboboxInput
                v-model="text"
                v-bind="attrs"
                :display-value="labelOf"
                :placeholder="placeholder"
                :readonly="!typeable"
                autocomplete="off"
                :aria-invalid="invalid || undefined"
                @input="typed = true"
                @focus="selectText"
                :class="
                    cn(
                        'h-11 w-full rounded-md border border-input bg-background py-2 pl-3 pr-10 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 sm:h-10 sm:text-sm',
                        !typeable && 'cursor-pointer caret-transparent',
                        invalid && 'border-destructive',
                    )
                "
            />
            <ComboboxTrigger class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted-foreground" tabindex="-1" :aria-label="$t('common.show_options')">
                <ChevronDown class="size-4" aria-hidden="true" />
            </ComboboxTrigger>
        </ComboboxAnchor>

        <ComboboxPortal>
            <ComboboxContent
                position="popper"
                :side-offset="4"
                class="z-50 max-h-[min(18rem,var(--reka-combobox-content-available-height))] w-[var(--reka-combobox-trigger-width)] min-w-40 overflow-hidden rounded-md border bg-popover text-popover-foreground shadow-md"
            >
                <ComboboxViewport class="max-h-[inherit] overflow-y-auto p-1">
                    <ComboboxEmpty class="px-3 py-2 text-sm text-muted-foreground">{{ $t('common.no_matches') }}</ComboboxEmpty>
                    <ComboboxItem
                        v-for="option in filtered"
                        :key="option.value"
                        :value="option.value"
                        :text-value="option.label"
                        class="relative flex min-h-10 cursor-pointer select-none items-center rounded-sm py-2 pl-3 pr-8 text-base outline-none data-[disabled]:pointer-events-none data-[highlighted]:bg-accent data-[highlighted]:text-accent-foreground data-[disabled]:opacity-50 sm:min-h-9 sm:text-sm"
                    >
                        {{ option.label }}
                        <ComboboxItemIndicator class="absolute right-2 flex items-center">
                            <Check class="size-4" aria-hidden="true" />
                        </ComboboxItemIndicator>
                    </ComboboxItem>
                </ComboboxViewport>
            </ComboboxContent>
        </ComboboxPortal>
    </ComboboxRoot>
</template>
