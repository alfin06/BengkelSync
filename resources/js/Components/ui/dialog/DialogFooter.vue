<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <!-- Primary action first on phones (closest to the thumb), on the right from `sm` up. -->
    <div :class="cn('flex flex-col-reverse gap-2 sm:flex-row sm:justify-end', props.class)">
        <slot />
    </div>
</template>
