<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { cn } from '@/lib/utils';
import type { MechanicOption } from '@/types/workOrders';

/**
 * Pick the mechanics on a job with large toggle buttons (easier than a multi-select on
 * a phone).
 */
defineProps<{ mechanics: MechanicOption[]; label: string }>();
const selected = defineModel<number[]>({ required: true });

function toggle(id: number): void {
    selected.value = selected.value.includes(id) ? selected.value.filter((value) => value !== id) : [...selected.value, id];
}
</script>

<template>
    <div role="group" :aria-label="label" class="flex flex-wrap gap-2">
        <button
            v-for="mechanic in mechanics"
            :key="mechanic.id"
            type="button"
            :aria-pressed="selected.includes(mechanic.id)"
            :class="
                cn(
                    'inline-flex min-h-11 items-center gap-2 rounded-full border px-4 text-sm font-medium',
                    selected.includes(mechanic.id) ? 'border-primary bg-primary/10 text-primary' : 'bg-background hover:bg-muted',
                )
            "
            @click="toggle(mechanic.id)"
        >
            <Check v-if="selected.includes(mechanic.id)" class="size-4" aria-hidden="true" />
            {{ mechanic.name }}
        </button>
        <p v-if="mechanics.length === 0" class="text-sm text-muted-foreground">{{ $t('work_orders.no_mechanics') }}</p>
    </div>
</template>
