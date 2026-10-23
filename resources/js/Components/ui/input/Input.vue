<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const props = defineProps<{
    invalid?: boolean;
    class?: HTMLAttributes['class'];
}>();

const model = defineModel<string | number | null>();
</script>

<template>
    <!-- text-base on phones avoids the automatic zoom on focus in mobile Safari -->
    <input
        v-model="model"
        v-bind="$attrs"
        :aria-invalid="props.invalid || undefined"
        :class="
            cn(
                'flex h-11 w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-sm transition-colors placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 sm:h-10 sm:text-sm',
                props.invalid && 'border-destructive focus-visible:ring-destructive',
                props.class,
            )
        "
    />
</template>
