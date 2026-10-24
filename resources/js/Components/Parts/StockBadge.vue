<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import type { Part } from '@/types/inventory';

/**
 * Stock in the active branch with its state: empty (red), low (amber) or fine.
 * Always shows the number and a word, never colour alone.
 */
defineProps<{ part: Part }>();
</script>

<template>
    <span class="inline-flex flex-wrap items-center gap-1.5">
        <span class="font-medium tabular-nums">{{ part.stock.qty_label }} {{ part.unit_label }}</span>
        <Badge v-if="part.stock.is_empty" tone="danger">{{ $t('parts.stock_state.empty') }}</Badge>
        <Badge v-else-if="part.stock.is_low" tone="warning">{{ $t('parts.stock_state.low') }}</Badge>
    </span>
</template>
