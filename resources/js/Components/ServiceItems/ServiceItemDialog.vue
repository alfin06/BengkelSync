<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import type { ServiceItem } from '@/types/catalog';

/**
 * Add or edit one catalog job. A bottom sheet on phones, so the list stays in view.
 */
const props = defineProps<{
    /** null = add a new job */
    item: ServiceItem | null;
    categories: string[];
    canDelete: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    code: '',
    name: '',
    category: '',
    default_price: null as number | null,
    estimated_minutes: null as number | null,
    is_active: true,
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    form.clearErrors();
    form.defaults({
        code: props.item?.code ?? '',
        name: props.item?.name ?? '',
        category: props.item?.category ?? '',
        default_price: props.item?.default_price ?? null,
        estimated_minutes: props.item?.estimated_minutes ?? null,
        is_active: props.item?.is_active ?? true,
    });
    form.reset();
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.item) {
        form.put(route('service-items.update', props.item.id), options);
    } else {
        form.post(route('service-items.store'), options);
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    if (!props.item) {
        return;
    }

    router.delete(route('service-items.destroy', props.item.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onSuccess: () => (open.value = false),
        onFinish: () => {
            deleting.value = false;
            confirmDelete.value = false;
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ item ? $t('service_items.edit_title') : $t('service_items.create_title') }}</DialogTitle>
                </DialogHeader>

                <FormField id="service-name" :label="$t('service_items.fields.name')" :error="form.errors.name" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="service-name" v-model="form.name" autocomplete="off" placeholder="Ganti oli mesin" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="service-price" :label="$t('service_items.fields.default_price')" :error="form.errors.default_price" required>
                    <template #default="{ invalid, describedBy }">
                        <MoneyInput id="service-price" v-model="form.default_price" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="service-category" :label="$t('service_items.fields.category')" :error="form.errors.category">
                        <template #default="{ invalid, describedBy }">
                            <Input
                                id="service-category"
                                v-model="form.category"
                                list="service-categories"
                                autocomplete="off"
                                placeholder="Perawatan"
                                :invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                            <datalist id="service-categories">
                                <option v-for="category in categories" :key="category" :value="category" />
                            </datalist>
                        </template>
                    </FormField>

                    <FormField id="service-code" :label="$t('service_items.fields.code')" :error="form.errors.code">
                        <template #default="{ invalid, describedBy }">
                            <Input
                                id="service-code"
                                v-model="form.code"
                                autocomplete="off"
                                autocapitalize="characters"
                                placeholder="GO-01"
                                class="font-mono uppercase"
                                :invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>

                    <FormField id="service-minutes" :label="$t('service_items.fields.estimated_minutes')" :error="form.errors.estimated_minutes">
                        <template #default="{ invalid, describedBy }">
                            <Input
                                id="service-minutes"
                                v-model.number="form.estimated_minutes"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                placeholder="30"
                                :invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>
                </div>

                <Checkbox v-if="item" id="service-active" v-model="form.is_active" :label="$t('service_items.fields.is_active')" />

                <DialogFooter class="sm:justify-between">
                    <Button v-if="item && canDelete" type="button" variant="ghost" class="text-destructive" @click="confirmDelete = true">
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
        v-if="item"
        v-model:open="confirmDelete"
        :title="$t('service_items.delete_confirm_title')"
        :description="$t('service_items.delete_confirm_description', { name: item.name })"
        :confirm-label="$t('common.delete')"
        destructive
        :processing="deleting"
        @confirm="destroy"
    />
</template>
