<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/layout/AppLogo.vue';
import FlashToasts from '@/Components/layout/FlashToasts.vue';
import { Card, CardContent, CardDescription, CardHeader } from '@/Components/ui/card';
import { cn } from '@/lib/utils';

defineProps<{ title: string; description?: string }>();

const page = usePage();
const locales = [
    { code: 'id', label: 'Bahasa Indonesia' },
    { code: 'en', label: 'English' },
];

function setLocale(code: string): void {
    // Reload the full page so both PHP and Vue switch language.
    router.post(route('locale.update'), { locale: code }, { onSuccess: () => window.location.reload() });
}
</script>

<template>
    <div class="flex min-h-dvh flex-col items-center justify-center bg-muted/40 px-4 py-10">
        <FlashToasts />

        <div class="mb-6 flex items-center gap-2 text-lg font-semibold">
            <AppLogo class="size-8" />
            {{ page.props.appName }}
        </div>

        <Card class="w-full max-w-md">
            <CardHeader>
                <h1 class="text-lg font-semibold leading-tight">{{ title }}</h1>
                <CardDescription v-if="description">{{ description }}</CardDescription>
            </CardHeader>
            <CardContent>
                <slot />
            </CardContent>
        </Card>

        <nav class="mt-6 flex gap-1 text-sm" :aria-label="$t('common.language')">
            <button
                v-for="locale in locales"
                :key="locale.code"
                type="button"
                :aria-pressed="page.props.locale === locale.code"
                :class="
                    cn(
                        'min-h-11 rounded-md px-3 text-muted-foreground hover:text-foreground',
                        page.props.locale === locale.code && 'font-medium text-foreground underline underline-offset-4',
                    )
                "
                @click="setLocale(locale.code)"
            >
                {{ locale.label }}
            </button>
        </nav>
    </div>
</template>
