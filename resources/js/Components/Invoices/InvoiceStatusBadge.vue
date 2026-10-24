<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import type { InvoiceStatus } from '@/types/invoices';

defineProps<{ status: InvoiceStatus; label: string }>();

const tones = { unpaid: 'warning', partial: 'info', paid: 'success', void: 'neutral' } as const;
</script>

<template>
    <Badge :tone="tones[status]">{{ label }}</Badge>
</template>
