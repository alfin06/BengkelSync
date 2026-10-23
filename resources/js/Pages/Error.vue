<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { buttonVariants } from '@/Components/ui/button';

const props = defineProps<{ status: number }>();

const key = computed(() => ([403, 404, 429, 503].includes(props.status) ? String(props.status) : '500'));
const title = computed(() => trans(`errors.${key.value}.title`));
</script>

<template>
    <Head :title="title" />

    <main class="flex min-h-dvh flex-col items-center justify-center gap-4 bg-muted/40 px-4 text-center">
        <p class="text-5xl font-bold text-primary">{{ status }}</p>
        <h1 class="text-2xl font-semibold">{{ title }}</h1>
        <p class="max-w-md text-muted-foreground">{{ $t(`errors.${key}.description`) }}</p>
        <Link :href="route('dashboard')" :class="buttonVariants()">{{ $t('errors.back_home') }}</Link>
    </main>
</template>
