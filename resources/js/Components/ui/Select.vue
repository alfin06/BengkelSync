<script setup lang="ts">
import { ChevronDown } from 'lucide-vue-next';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types/ui';

defineOptions({ inheritAttrs: false });

/**
 * A native select: on phones it opens the system picker, which is the easiest to use.
 */
const props = defineProps<{
    options: SelectOption[];
    placeholder?: string;
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<string | number | null>();
</script>

<template>
    <div :class="cn('relative', props.class)">
        <select
            v-model="model"
            v-bind="$attrs"
            :aria-invalid="props.invalid || undefined"
            :class="
                cn(
                    'h-11 w-full appearance-none rounded-md border border-input bg-background py-2 pl-3 pr-10 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 sm:h-10 sm:text-sm',
                    props.invalid && 'border-destructive',
                )
            "
        >
            <option v-if="placeholder" :value="null" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <ChevronDown class="pointer-events-none absolute right-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
    </div>
</template>
