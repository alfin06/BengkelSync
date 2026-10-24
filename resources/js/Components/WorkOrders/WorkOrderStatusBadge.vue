<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import type { WorkOrderStatus } from '@/types/workOrders';

defineProps<{ status: WorkOrderStatus; label: string }>();

// Same colours everywhere (SPEC 8.1); the label always says the status in words.
const tones = {
    draft: 'neutral',
    queued: 'info',
    in_progress: 'warning',
    waiting_parts: 'danger',
    completed: 'success',
    invoiced: 'success',
    closed: 'neutral',
    cancelled: 'neutral',
} as const;
</script>

<template>
    <Badge :tone="tones[status]">{{ label }}</Badge>
</template>
