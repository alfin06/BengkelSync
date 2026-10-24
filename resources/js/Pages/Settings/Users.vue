<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { KeyRound, Pencil, ShieldCheck, UserPlus, Users as UsersIcon } from 'lucide-vue-next';
import { ref } from 'vue';
import StaffUserDialog from '@/Components/Users/StaffUserDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import type { SelectOption } from '@/types/ui';
import type { StaffUser } from '@/types/users';

defineProps<{
    users: Paginated<StaffUser>;
    filters: { search: string };
    roles: SelectOption[];
    branches: { id: number; name: string }[];
    can: { create: boolean; allBranches: boolean; roles: boolean };
}>();

const dialogOpen = ref(false);
const editing = ref<StaffUser | null>(null);

function openDialog(user: StaffUser | null): void {
    editing.value = user;
    dialogOpen.value = true;
}

// A new password for someone who forgot theirs.
const passwordOpen = ref(false);
const passwordUser = ref<StaffUser | null>(null);
const passwordForm = useForm({ password: '' });

function openPassword(user: StaffUser): void {
    passwordUser.value = user;
    passwordForm.reset();
    passwordForm.clearErrors();
    passwordOpen.value = true;
}

function savePassword(): void {
    if (!passwordUser.value) {
        return;
    }

    passwordForm.put(route('settings.users.password', passwordUser.value.id), { preserveScroll: true, onSuccess: () => (passwordOpen.value = false) });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('users_admin.title')" :description="$t('users_admin.description')">
            <template #actions>
                <Link v-if="can.roles" :href="route('settings.roles.index')" :class="buttonVariants({ variant: 'outline' })"><ShieldCheck aria-hidden="true" /> {{ $t('roles_admin.title') }}</Link>
                <Button v-if="can.create" @click="openDialog(null)"><UserPlus aria-hidden="true" /> {{ $t('users_admin.add') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4">
            <SearchInput :initial="filters.search" :placeholder="$t('users_admin.search_placeholder')" :only="['users', 'filters']" />
        </div>

        <EmptyState v-if="users.data.length === 0" :icon="UsersIcon" :title="$t('users_admin.empty_title')" />

        <ul v-else class="divide-y rounded-lg border bg-card">
            <li v-for="user in users.data" :key="user.id" class="flex flex-wrap items-start gap-3 p-4 text-sm">
                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ user.name }}</span>
                        <Badge v-if="user.role_label" tone="info">{{ user.role_label }}</Badge>
                        <Badge v-if="!user.is_active">{{ $t('users_admin.inactive') }}</Badge>
                        <Badge v-if="user.is_self" tone="success">{{ $t('users_admin.you') }}</Badge>
                    </span>
                    <span class="block text-muted-foreground">{{ user.email }}<template v-if="user.phone"> · {{ user.phone }}</template></span>
                    <span class="block text-xs text-muted-foreground">
                        {{ user.all_branches ? $t('users_admin.all_branches') : user.branch_names.join(', ') || $t('users_admin.no_branch') }}
                        · {{ user.last_login_label ? $t('users_admin.last_login', { date: user.last_login_label }) : $t('users_admin.never_logged_in') }}
                    </span>
                </span>
                <span v-if="user.can.update" class="flex gap-1">
                    <Button variant="ghost" size="sm" @click="openPassword(user)"><KeyRound aria-hidden="true" /> {{ $t('users_admin.reset_password') }}</Button>
                    <Button variant="outline" size="sm" @click="openDialog(user)"><Pencil aria-hidden="true" /> {{ $t('common.edit') }}</Button>
                </span>
                <Link v-else-if="user.is_self" :href="route('profile.edit')" :class="buttonVariants({ variant: 'ghost', size: 'sm' })">{{ $t('users_admin.my_profile') }}</Link>
            </li>
        </ul>

        <div class="mt-4">
            <Pagination :meta="users" />
        </div>

        <StaffUserDialog v-model:open="dialogOpen" :user="editing" :roles="roles" :branches="branches" :can-all-branches="can.allBranches" />

        <Dialog v-model:open="passwordOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="savePassword">
                    <DialogHeader>
                        <DialogTitle>{{ $t('users_admin.reset_password_title', { name: passwordUser?.name ?? '' }) }}</DialogTitle>
                        <DialogDescription>{{ $t('users_admin.reset_password_description') }}</DialogDescription>
                    </DialogHeader>
                    <FormField id="new-password" :label="$t('users_admin.fields.password')" :hint="$t('users_admin.password_hint')" :error="passwordForm.errors.password" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="new-password" v-model="passwordForm.password" type="text" autocomplete="new-password" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="passwordOpen = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="passwordForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
