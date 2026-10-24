<script setup lang="ts">
import { computed } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import type { Purchase } from '@/types/purchases';

const props = defineProps<{ purchase: Pick<Purchase, 'status' | 'status_label' | 'is_overdue'> }>();

const tone = computed(() => {
    if (props.purchase.is_overdue) {
        return 'danger';
    }

    return ({ paid: 'success', partial: 'warning', unpaid: 'info', void: 'neutral' } as const)[props.purchase.status];
});
</script>

<template>
    <Badge :tone="tone">{{ purchase.is_overdue ? $t('purchases.overdue') : purchase.status_label }}</Badge>
</template>
