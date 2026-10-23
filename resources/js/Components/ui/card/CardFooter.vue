<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <div :class="cn('flex flex-col-reverse gap-2 p-4 pt-0 sm:flex-row sm:items-center sm:justify-end sm:p-6 sm:pt-0', props.class)">
        <slot />
    </div>
</template>
