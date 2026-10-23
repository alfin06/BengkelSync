<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next';
import { buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

/**
 * Previous / next with "page x of y". Big targets, works the same on phone and desktop,
 * and keeps filters because Laravel's page URLs already include the query string.
 */
defineProps<{ meta: Paginated<unknown> }>();

const linkClass = cn(buttonVariants({ variant: 'outline' }), 'gap-1');
const disabledClass = cn(linkClass, 'pointer-events-none opacity-50');
</script>

<template>
    <nav v-if="meta.last_page > 1" class="flex items-center justify-between gap-3" :aria-label="$t('pagination.label')">
        <Link v-if="meta.prev_page_url" :href="meta.prev_page_url" preserve-scroll :class="linkClass" rel="prev">
            <ChevronLeft aria-hidden="true" /> {{ $t('pagination.previous') }}
        </Link>
        <span v-else :class="disabledClass" aria-disabled="true"><ChevronLeft aria-hidden="true" /> {{ $t('pagination.previous') }}</span>

        <p class="text-sm text-muted-foreground">
            {{ $t('pagination.page_of', { page: String(meta.current_page), last: String(meta.last_page) }) }}
        </p>

        <Link v-if="meta.next_page_url" :href="meta.next_page_url" preserve-scroll :class="linkClass" rel="next">
            {{ $t('pagination.next') }} <ChevronRight aria-hidden="true" />
        </Link>
        <span v-else :class="disabledClass" aria-disabled="true">{{ $t('pagination.next') }} <ChevronRight aria-hidden="true" /></span>
    </nav>
</template>
