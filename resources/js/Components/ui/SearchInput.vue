<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { Search } from 'lucide-vue-next';
import { ref } from 'vue';

/**
 * Debounced server-side search: updates `?{param}=` in the URL and reloads only the
 * listed props, keeping scroll position and other filters.
 */
const props = withDefaults(
    defineProps<{
        initial?: string | null;
        placeholder: string;
        param?: string;
        only?: string[];
    }>(),
    { initial: '', param: 'search', only: undefined },
);

const term = ref(props.initial ?? '');

const search = useDebounceFn(() => {
    const url = new URL(window.location.href);
    const value = term.value.trim();

    if (value === '') {
        url.searchParams.delete(props.param);
    } else {
        url.searchParams.set(props.param, value);
    }
    url.searchParams.delete('page');

    router.get(url.pathname + url.search, {}, { preserveState: true, preserveScroll: true, replace: true, only: props.only });
}, 300);
</script>

<template>
    <div class="relative">
        <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
        <input
            v-model="term"
            type="search"
            :placeholder="placeholder"
            :aria-label="placeholder"
            class="h-11 w-full rounded-md border border-input bg-background pl-9 pr-3 text-base shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-10 sm:text-sm"
            @input="search"
        />
    </div>
</template>
