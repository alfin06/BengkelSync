<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import Select from '@/Components/ui/Select.vue';
import type { StaffUser } from '@/types/users';
import type { SelectOption } from '@/types/ui';

/**
 * Add a staff account (with a password the owner gives them) or edit one. Only roles and
 * branches the person editing holds themselves are offered; the server checks again.
 */
const props = defineProps<{
    user: StaffUser | null;
    roles: SelectOption[];
    branches: { id: number; name: string }[];
    canAllBranches: boolean;
}>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    email: '',
    phone: '',
    locale: 'id',
    password: '',
    role_id: null as number | null,
    all_branches: false,
    branch_ids: [] as number[],
    is_active: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.defaults({
        name: props.user?.name ?? '',
        email: props.user?.email ?? '',
        phone: props.user?.phone ?? '',
        locale: props.user?.locale ?? 'id',
        password: '',
        role_id: props.user?.role_id ?? (props.roles[0]?.value as number | undefined) ?? null,
        all_branches: props.user?.all_branches ?? false,
        // A new account in a one-branch shop gets that branch.
        branch_ids: props.user?.branch_ids ?? (props.branches.length === 1 && props.branches[0] ? [props.branches[0].id] : []),
        is_active: props.user?.is_active ?? true,
    });
    form.reset();
    form.clearErrors();
});

function toggleBranch(id: number, checked: boolean): void {
    form.branch_ids = checked ? [...form.branch_ids, id] : form.branch_ids.filter((value) => value !== id);
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.user) {
        form.transform(({ password: _password, ...data }) => data).put(route('settings.users.update', props.user.id), options);
    } else {
        form.transform(({ is_active: _active, ...data }) => data).post(route('settings.users.store'), options);
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    if (!props.user) {
        return;
    }

    router.delete(route('settings.users.destroy', props.user.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onFinish: () => (deleting.value = false),
        onSuccess: () => {
            confirmDelete.value = false;
            open.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ user ? $t('users_admin.edit_title', { name: user.name }) : $t('users_admin.add_title') }}</DialogTitle>
                    <DialogDescription v-if="!user">{{ $t('users_admin.add_description') }}</DialogDescription>
                </DialogHeader>

                <FormField id="user-name" :label="$t('users_admin.fields.name')" :error="form.errors.name" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="user-name" v-model="form.name" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="user-email" :label="$t('users_admin.fields.email')" :hint="$t('users_admin.email_hint')" :error="form.errors.email" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="user-email" v-model="form.email" type="email" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="user-phone" :label="$t('fields.phone')" :error="form.errors.phone">
                        <template #default="{ invalid, describedBy }">
                            <Input id="user-phone" v-model="form.phone" type="tel" inputmode="tel" placeholder="0812…" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <FormField v-if="!user" id="user-password" :label="$t('users_admin.fields.password')" :hint="$t('users_admin.password_hint')" :error="form.errors.password" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="user-password" v-model="form.password" type="text" autocomplete="new-password" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="user-role" :label="$t('users_admin.fields.role')" :error="form.errors.role_id" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="user-role" v-model="form.role_id" :options="roles" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="user-locale" :label="$t('common.language')" :error="form.errors.locale">
                        <template #default="{ invalid, describedBy }">
                            <Select id="user-locale" v-model="form.locale" :options="[{ value: 'id', label: 'Bahasa Indonesia' }, { value: 'en', label: 'English' }]" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <fieldset class="grid gap-2 rounded-md border p-3">
                    <legend class="px-1 text-sm font-medium">{{ $t('users_admin.fields.branches') }}</legend>
                    <Checkbox v-if="canAllBranches" id="user-all-branches" v-model="form.all_branches" :label="$t('users_admin.all_branches')" />
                    <template v-if="!form.all_branches">
                        <label v-for="branch in branches" :key="branch.id" class="inline-flex min-h-11 items-center gap-3 text-sm sm:min-h-0">
                            <input
                                type="checkbox"
                                class="size-5 rounded border-input text-primary"
                                :checked="form.branch_ids.includes(branch.id)"
                                @change="toggleBranch(branch.id, ($event.target as HTMLInputElement).checked)"
                            />
                            {{ branch.name }}
                        </label>
                    </template>
                    <p v-if="form.errors.branch_ids || form.errors.all_branches" class="text-sm font-medium text-destructive" role="alert">{{ form.errors.branch_ids ?? form.errors.all_branches }}</p>
                </fieldset>

                <template v-if="user">
                    <Checkbox id="user-active" v-model="form.is_active" :label="$t('users_admin.fields.is_active')" />
                    <p v-if="!form.is_active" class="-mt-2 text-sm text-muted-foreground">{{ $t('users_admin.inactive_hint') }}</p>
                </template>

                <DialogFooter class="sm:justify-between">
                    <Button v-if="user?.can.delete" type="button" variant="ghost" class="text-destructive" @click="confirmDelete = true">
                        <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                    </Button>
                    <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
                    </div>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <ConfirmDialog
        v-model:open="confirmDelete"
        :title="$t('users_admin.delete_title', { name: user?.name ?? '' })"
        :description="$t('users_admin.delete_description')"
        :confirm-label="$t('common.delete')"
        destructive
        :processing="deleting"
        @confirm="destroy"
    />
</template>
