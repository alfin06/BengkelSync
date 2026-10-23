<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';
import { Label } from '@/Components/ui/label';

/**
 * Label + control + hint + inline error, wired up for screen readers.
 * Pass the same `id` to the control inside the slot.
 */
const props = defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
    class?: HTMLAttributes['class'];
}>();
</script>

<template>
    <div :class="cn('grid gap-2', props.class)">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true">*</span>
        </Label>

        <slot
            :invalid="Boolean(error)"
            :described-by="[error ? `${id}-error` : null, hint ? `${id}-hint` : null].filter(Boolean).join(' ') || undefined"
        />

        <p v-if="hint && !error" :id="`${id}-hint`" class="text-sm text-muted-foreground">{{ hint }}</p>
        <p v-if="error" :id="`${id}-error`" class="text-sm font-medium text-destructive" role="alert">{{ error }}</p>
    </div>
</template>
