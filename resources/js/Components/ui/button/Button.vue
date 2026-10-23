<script setup lang="ts">
import { Loader2 } from 'lucide-vue-next';
import { Primitive, type PrimitiveProps } from 'reka-ui';
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { buttonVariants, type ButtonVariants } from '.';

interface Props extends PrimitiveProps {
    variant?: ButtonVariants['variant'];
    size?: ButtonVariants['size'];
    /** Shows a spinner and disables the button (prevents double submit). */
    loading?: boolean;
    disabled?: boolean;
    class?: HTMLAttributes['class'];
}

const props = withDefaults(defineProps<Props>(), {
    as: 'button',
    variant: 'default',
    size: 'default',
    loading: false,
    disabled: false,
    class: undefined,
});
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :disabled="as === 'button' ? disabled || loading : undefined"
        :aria-busy="loading || undefined"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <Loader2 v-if="loading" class="animate-spin" aria-hidden="true" />
        <slot />
    </Primitive>
</template>
