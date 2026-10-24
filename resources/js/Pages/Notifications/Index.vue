<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { AlertTriangle, Bell, CheckCheck } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';
import type { AppNotification } from '@/types/inventory';

defineProps<{ items: Paginated<AppNotification> }>();

function open(notification: AppNotification): void {
    router.post(route('notifications.read', notification.id));
}

function readAll(): void {
    router.post(route('notifications.read-all'), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('notifications.title')">
            <template v-if="$page.props.notifications.unread > 0" #actions>
                <Button variant="outline" @click="readAll"><CheckCheck aria-hidden="true" /> {{ $t('notifications.read_all') }}</Button>
            </template>
        </PageHeader>

        <EmptyState v-if="items.data.length === 0" :icon="Bell" :title="$t('notifications.empty')" />

        <ul v-else class="divide-y rounded-lg border bg-card">
            <li v-for="notification in items.data" :key="notification.id">
                <button
                    type="button"
                    :class="cn('flex w-full items-start gap-3 p-4 text-left hover:bg-muted/50', !notification.read && 'bg-primary/5')"
                    @click="open(notification)"
                >
                    <AlertTriangle class="mt-0.5 size-5 shrink-0 text-amber-600" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span :class="cn('block', !notification.read && 'font-semibold')">{{ notification.title }}</span>
                        <span class="block text-sm text-muted-foreground">{{ notification.body }}</span>
                        <span class="block text-xs text-muted-foreground">{{ notification.created_at_label }}</span>
                    </span>
                    <span v-if="!notification.read" class="mt-2 size-2 shrink-0 rounded-full bg-primary" :aria-label="$t('notifications.unread')" />
                </button>
            </li>
        </ul>

        <div class="mt-4">
            <Pagination :meta="items" />
        </div>
    </AppLayout>
</template>
