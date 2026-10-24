<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Lock, Pencil } from 'lucide-vue-next';
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import type { ExpenseCategoryOption } from '@/types/expenses';

/**
 * Add, rename, switch off or restrict expense categories (Owner and Finance by default).
 */
defineProps<{ categories: ExpenseCategoryOption[]; canRestrict: boolean }>();
const open = defineModel<boolean>('open', { default: false });

const editing = ref<ExpenseCategoryOption | null>(null);
const form = useForm({ name: '', is_restricted: false, is_active: true });

function edit(category: ExpenseCategoryOption | null): void {
    editing.value = category;
    form.defaults({ name: category?.name ?? '', is_restricted: category?.is_restricted ?? false, is_active: category?.is_active ?? true });
    form.reset();
    form.clearErrors();
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => edit(null) };

    if (editing.value) {
        form.put(route('expense-categories.update', editing.value.id), options);
    } else {
        form.post(route('expense-categories.store'), options);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('expenses.categories_title') }}</DialogTitle>
                <DialogDescription>{{ $t('expenses.categories_description') }}</DialogDescription>
            </DialogHeader>

            <ul class="max-h-64 divide-y overflow-y-auto rounded-md border">
                <li v-for="category in categories" :key="category.id" class="flex items-center gap-2 px-3 py-2 text-sm">
                    <span :class="['flex-1', !category.is_active && 'text-muted-foreground line-through']">{{ category.name }}</span>
                    <Badge v-if="category.is_restricted" tone="warning"><Lock class="size-3" aria-hidden="true" /> {{ $t('expenses.restricted') }}</Badge>
                    <Button type="button" variant="ghost" size="icon" :aria-label="$t('common.edit')" @click="edit(category)"><Pencil aria-hidden="true" /></Button>
                </li>
            </ul>

            <form class="grid gap-3 rounded-md border p-3" novalidate @submit.prevent="submit">
                <FormField id="category-name" :label="editing ? $t('expenses.category_name') : $t('expenses.add_category')" :error="form.errors.name" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="category-name" v-model="form.name" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>
                <Checkbox v-if="canRestrict" id="category-restricted" v-model="form.is_restricted" :label="$t('expenses.category_restricted')" />
                <Checkbox v-if="editing" id="category-active" v-model="form.is_active" :label="$t('expenses.category_active')" />
                <div class="flex justify-end gap-2">
                    <Button v-if="editing" type="button" variant="outline" @click="edit(null)">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
