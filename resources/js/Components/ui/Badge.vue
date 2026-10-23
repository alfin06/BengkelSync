<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

/**
 * Status colours are the same everywhere (SPEC 8.1). Always pair the colour with text;
 * never rely on colour alone.
 */
const badgeVariants = cva('inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium', {
    variants: {
        tone: {
            neutral: 'border-border bg-muted text-foreground',
            info: 'border-blue-200 bg-blue-50 text-blue-800',
            success: 'border-green-200 bg-green-50 text-green-800',
            warning: 'border-amber-200 bg-amber-50 text-amber-900',
            danger: 'border-red-200 bg-red-50 text-red-800',
        },
    },
    defaultVariants: { tone: 'neutral' },
});

const props = defineProps<{
    tone?: VariantProps<typeof badgeVariants>['tone'];
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <span :class="cn(badgeVariants({ tone: props.tone }), props.class)"><slot /></span>
</template>
