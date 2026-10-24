<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Bell, LogOut, Menu, UserRound, Wrench } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import BranchSwitcher from '@/Components/layout/BranchSwitcher.vue';
import FlashToasts from '@/Components/layout/FlashToasts.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { usePermissions } from '@/composables/usePermissions';
import { navigation, type NavItem } from '@/lib/navigation';
import { cn } from '@/lib/utils';

const page = usePage();
const { can } = usePermissions();
const moreOpen = ref(false);

const items = computed<NavItem[]>(() =>
    navigation.filter((item) => route().has(item.route) && (item.permission === null || can(item.permission))),
);
const primaryItems = computed(() => items.value.filter((item) => item.primary).slice(0, 4));
const secondaryItems = computed(() => items.value.filter((item) => !primaryItems.value.includes(item)));

const isActive = (item: NavItem): boolean => route().current(item.activePattern) ?? false;
const shopName = computed(() => page.props.tenant?.name ?? page.props.appName);
</script>

<template>
    <div class="min-h-dvh bg-muted/40">
        <FlashToasts />

        <a
            href="#main"
            class="sr-only z-50 rounded-md bg-background px-4 py-2 focus:not-sr-only focus:fixed focus:left-4 focus:top-4"
        >
            {{ $t('nav.skip_to_content') }}
        </a>

        <!-- Sidebar: tablet and desktop -->
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r bg-background md:flex">
            <div class="flex h-14 items-center gap-2 border-b px-4">
                <Wrench class="size-5 text-primary" aria-hidden="true" />
                <span class="truncate font-semibold">{{ shopName }}</span>
            </div>

            <nav class="flex-1 overflow-y-auto p-3" :aria-label="$t('nav.main')">
                <ul class="grid gap-1">
                    <li v-for="item in items" :key="item.route">
                        <Link
                            :href="route(item.route)"
                            :aria-current="isActive(item) ? 'page' : undefined"
                            :class="
                                cn(
                                    'flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium transition-colors hover:bg-accent',
                                    isActive(item) && 'bg-primary/10 text-primary',
                                )
                            "
                        >
                            <component :is="item.icon" class="size-4" aria-hidden="true" />
                            {{ $t(item.label) }}
                        </Link>
                    </li>
                </ul>
            </nav>

            <div class="grid gap-1 border-t p-3">
                <Link
                    :href="route('profile.edit')"
                    class="flex h-10 items-center gap-3 rounded-md px-3 text-sm hover:bg-accent"
                    :aria-current="route().current('profile.*') ? 'page' : undefined"
                >
                    <UserRound class="size-4" aria-hidden="true" />
                    <span class="truncate">{{ page.props.auth.user?.name }}</span>
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex h-10 items-center gap-3 rounded-md px-3 text-left text-sm text-muted-foreground hover:bg-accent hover:text-foreground"
                >
                    <LogOut class="size-4" aria-hidden="true" />
                    {{ $t('nav.logout') }}
                </Link>
            </div>
        </aside>

        <div class="md:pl-60">
            <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b bg-background/95 px-4 backdrop-blur sm:px-6">
                <span class="truncate font-semibold md:hidden">{{ shopName }}</span>
                <div class="ml-auto flex items-center gap-2">
                    <BranchSwitcher />
                    <Link
                        :href="route('notifications.index')"
                        class="relative inline-flex size-11 items-center justify-center rounded-md hover:bg-accent sm:size-10"
                        :aria-label="
                            page.props.notifications.unread > 0
                                ? $t('notifications.bell_unread', { count: String(page.props.notifications.unread) })
                                : $t('notifications.title')
                        "
                    >
                        <Bell class="size-5" aria-hidden="true" />
                        <span
                            v-if="page.props.notifications.unread > 0"
                            class="absolute right-1 top-1 min-w-5 rounded-full bg-destructive px-1 text-center text-xs font-semibold leading-5 text-destructive-foreground"
                            aria-hidden="true"
                        >
                            {{ page.props.notifications.unread > 9 ? '9+' : page.props.notifications.unread }}
                        </span>
                    </Link>
                </div>
            </header>

            <main id="main" class="mx-auto w-full max-w-6xl px-4 pb-28 pt-6 sm:px-6 md:pb-10">
                <slot />
            </main>
        </div>

        <!-- Bottom bar: phones -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t bg-background pb-[env(safe-area-inset-bottom)] md:hidden"
            :aria-label="$t('nav.main')"
        >
            <ul class="grid" :style="{ gridTemplateColumns: `repeat(${primaryItems.length + 1}, minmax(0, 1fr))` }">
                <li v-for="item in primaryItems" :key="item.route">
                    <Link
                        :href="route(item.route)"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        :class="
                            cn(
                                'flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium text-muted-foreground',
                                isActive(item) && 'text-primary',
                            )
                        "
                    >
                        <component :is="item.icon" class="size-5" aria-hidden="true" />
                        {{ $t(item.label) }}
                    </Link>
                </li>
                <li>
                    <button
                        type="button"
                        class="flex h-16 w-full flex-col items-center justify-center gap-1 text-xs font-medium text-muted-foreground"
                        @click="moreOpen = true"
                    >
                        <Menu class="size-5" aria-hidden="true" />
                        {{ $t('nav.more') }}
                    </button>
                </li>
            </ul>
        </nav>

        <Dialog v-model:open="moreOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('nav.more') }}</DialogTitle>
                    <DialogDescription>{{ page.props.auth.user?.email }}</DialogDescription>
                </DialogHeader>
                <ul class="grid gap-1">
                    <li v-for="item in secondaryItems" :key="item.route">
                        <Link :href="route(item.route)" class="flex h-12 items-center gap-3 rounded-md px-3 hover:bg-accent" @click="moreOpen = false">
                            <component :is="item.icon" class="size-5" aria-hidden="true" />
                            {{ $t(item.label) }}
                        </Link>
                    </li>
                    <li>
                        <Link :href="route('profile.edit')" class="flex h-12 items-center gap-3 rounded-md px-3 hover:bg-accent" @click="moreOpen = false">
                            <UserRound class="size-5" aria-hidden="true" />
                            {{ $t('nav.profile') }}
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex h-12 w-full items-center gap-3 rounded-md px-3 text-left text-destructive hover:bg-accent"
                        >
                            <LogOut class="size-5" aria-hidden="true" />
                            {{ $t('nav.logout') }}
                        </Link>
                    </li>
                </ul>
            </DialogContent>
        </Dialog>
    </div>
</template>
