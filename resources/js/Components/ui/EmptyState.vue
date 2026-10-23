<script setup lang="ts">
import { Inbox } from 'lucide-vue-next';
import type { Component } from 'vue';

withDefaults(defineProps<{ title: string; description?: string; icon?: Component }>(), {
    description: undefined,
    icon: () => Inbox,
});
</script>

<template>
    <div class="flex flex-col items-center gap-3 rounded-lg border border-dashed px-6 py-10 text-center">
        <component :is="icon" class="size-10 text-muted-foreground" aria-hidden="true" />
        <div class="grid gap-1">
            <p class="font-medium">{{ title }}</p>
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.default" class="mt-2"><slot /></div>
    </div>
</template>
