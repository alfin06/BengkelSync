<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, Copy, Lock, Plus } from 'lucide-vue-next';
import Badge from '@/Components/ui/Badge.vue';
import { buttonVariants } from '@/Components/ui/button';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { RoleSummary } from '@/types/users';

defineProps<{
    roles: RoleSummary[];
    can: { create: boolean };
}>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('roles_admin.title')" :description="$t('roles_admin.description')">
            <template #actions>
                <Link :href="route('settings.users.index')" :class="buttonVariants({ variant: 'outline' })"><ArrowLeft aria-hidden="true" /> {{ $t('users_admin.title') }}</Link>
                <Link v-if="can.create" :href="route('settings.roles.create')" :class="buttonVariants()"><Plus aria-hidden="true" /> {{ $t('roles_admin.add') }}</Link>
            </template>
        </PageHeader>

        <ul class="divide-y rounded-lg border bg-card">
            <li v-for="role in roles" :key="role.id" class="flex flex-wrap items-center gap-3 p-4 text-sm">
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <Link :href="route('settings.roles.edit', role.id)" class="font-medium text-primary hover:underline">{{ role.label }}</Link>
                        <Badge v-if="role.is_owner" tone="success"><Lock class="size-3" aria-hidden="true" /> {{ $t('roles_admin.all_access') }}</Badge>
                        <Badge v-else-if="!role.is_system" tone="info">{{ $t('roles_admin.custom') }}</Badge>
                    </span>
                    <span class="block text-muted-foreground">
                        {{ $tChoice('roles_admin.users_count', role.users_count, { count: String(role.users_count) }) }}
                        <template v-if="!role.is_owner"> · {{ $tChoice('roles_admin.permissions_count', role.permissions_count, { count: String(role.permissions_count) }) }}</template>
                    </span>
                </span>
                <Link v-if="can.create && !role.is_owner" :href="route('settings.roles.create', { copy: role.id })" :class="buttonVariants({ variant: 'ghost', size: 'sm' })">
                    <Copy aria-hidden="true" /> {{ $t('roles_admin.copy') }}
                </Link>
            </li>
        </ul>
    </AppLayout>
</template>
