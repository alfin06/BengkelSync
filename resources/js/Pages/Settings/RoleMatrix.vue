<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Check, Lock, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { MatrixRow } from '@/types/users';

/**
 * The permission matrix of one role (SPEC section 2). On phones a grid of checkboxes is
 * too wide, so every area is a card with toggle buttons for its actions.
 */
const props = defineProps<{
    role: { id: number | null; label: string; name: string; is_system: boolean; is_owner: boolean; permissions: string[] };
    matrix: MatrixRow[];
    can: { update: boolean };
}>();

const editable = computed(() => props.can.update && !props.role.is_owner);
const form = useForm({ name: props.role.is_system ? '' : props.role.name, permissions: [...props.role.permissions] });

const has = (permission: string): boolean => props.role.is_owner || form.permissions.includes(permission);

function toggle(permission: string): void {
    if (!editable.value) {
        return;
    }
    form.permissions = has(permission) ? form.permissions.filter((value) => value !== permission) : [...form.permissions, permission];
}

const rowPermissions = (row: MatrixRow): string[] => [...Object.values(row.standard).filter((value): value is string => value !== null), ...row.extra];

function toggleRow(row: MatrixRow): void {
    const all = rowPermissions(row);
    const allOn = all.every(has);
    form.permissions = allOn ? form.permissions.filter((value) => !all.includes(value)) : [...new Set([...form.permissions, ...all])];
}

function submit(): void {
    const data = { preserveScroll: true };

    if (props.role.id === null) {
        form.post(route('settings.roles.store'), data);
    } else {
        form.transform((values) => ({ ...values, name: props.role.is_system ? null : values.name })).put(route('settings.roles.update', props.role.id), data);
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    if (props.role.id === null) {
        return;
    }

    router.delete(route('settings.roles.destroy', props.role.id), {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmDelete.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="role.id === null ? $t('roles_admin.add') : role.label"
            :description="role.is_owner ? $t('roles_admin.owner_description') : $t('roles_admin.matrix_description')"
        >
            <template #actions>
                <Link :href="route('settings.roles.index')" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.back') }}</Link>
            </template>
        </PageHeader>

        <form class="grid max-w-4xl gap-4" novalidate @submit.prevent="submit">
            <p v-if="role.is_owner" class="flex items-center gap-2 rounded-md border bg-muted/50 p-3 text-sm"><Lock class="size-4" aria-hidden="true" /> {{ $t('roles_admin.owner_locked') }}</p>
            <p v-if="(form.errors as Record<string, string>).business" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800" role="alert">
                {{ (form.errors as Record<string, string>).business }}
            </p>

            <Card v-if="!role.is_system">
                <CardContent class="pt-4 sm:pt-6">
                    <FormField id="role-name" :label="$t('roles_admin.name')" :hint="$t('roles_admin.name_hint')" :error="form.errors.name" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="role-name" v-model="form.name" :disabled="!editable" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <div class="grid gap-4 md:grid-cols-2">
                <Card v-for="row in matrix" :key="row.resource">
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0 pb-3">
                        <CardTitle class="text-base">{{ $t(`permissions.resource.${row.resource}`) }}</CardTitle>
                        <button v-if="editable" type="button" class="text-sm font-medium text-primary underline-offset-4 hover:underline" @click="toggleRow(row)">
                            {{ rowPermissions(row).every(has) ? $t('roles_admin.none') : $t('roles_admin.all') }}
                        </button>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <template v-for="permission in rowPermissions(row)" :key="permission">
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="has(permission)"
                                :disabled="!editable"
                                :class="
                                    cn(
                                        'inline-flex min-h-10 items-center gap-1.5 rounded-full border px-3 text-sm',
                                        has(permission) ? 'border-primary bg-primary/10 font-medium text-primary' : 'text-muted-foreground hover:bg-muted',
                                        !editable && 'cursor-default',
                                    )
                                "
                                @click="toggle(permission)"
                            >
                                <Check v-if="has(permission)" class="size-4" aria-hidden="true" />
                                {{ Object.values(row.standard).includes(permission) ? $t(`permissions.action.${permission.split('.')[1]}`) : $t(`permissions.extra.${permission}`) }}
                            </button>
                        </template>
                    </CardContent>
                </Card>
            </div>

            <div v-if="editable" class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:mx-0 sm:flex-row sm:justify-between sm:rounded-md sm:border md:bottom-0">
                <Button v-if="role.id !== null && !role.is_system" type="button" variant="ghost" class="text-destructive" @click="confirmDelete = true">
                    <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                </Button>
                <span v-else />
                <Button type="submit" :loading="form.processing" :disabled="role.id !== null && !form.isDirty">
                    {{ $t('roles_admin.save', { count: String(form.permissions.length) }) }}
                </Button>
            </div>
        </form>

        <ConfirmDialog
            v-model:open="confirmDelete"
            :title="$t('roles_admin.delete_title', { name: role.label })"
            :description="$t('roles_admin.delete_description')"
            :confirm-label="$t('common.delete')"
            destructive
            :processing="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
