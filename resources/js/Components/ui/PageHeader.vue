<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

/**
 * Page title (also the browser tab title), optional description and actions.
 * On phones the actions wrap below the title.
 */
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <Head :title="title" />
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="grid gap-1">
            <h1 class="text-2xl font-semibold tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap gap-2"><slot name="actions" /></div>
    </div>
</template>
