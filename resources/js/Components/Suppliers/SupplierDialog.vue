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
import Textarea from '@/Components/ui/Textarea.vue';
import type { Supplier } from '@/types/inventory';

const props = defineProps<{
    /** null = add a new supplier */
    supplier: Supplier | null;
    canDelete: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    name: '',
    phone: '',
    whatsapp_same_as_phone: true,
    whatsapp: '',
    sales_name: '',
    email: '',
    address: '',
    npwp: '',
    notes: '',
});

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    const same = !props.supplier || !props.supplier.whatsapp || props.supplier.whatsapp === props.supplier.phone;
    form.clearErrors();
    form.defaults({
        name: props.supplier?.name ?? '',
        phone: props.supplier?.phone ?? '',
        whatsapp_same_as_phone: same,
        whatsapp: same ? '' : (props.supplier?.whatsapp ?? ''),
        sales_name: props.supplier?.sales_name ?? '',
        email: props.supplier?.email ?? '',
        address: props.supplier?.address ?? '',
        npwp: props.supplier?.npwp ?? '',
        notes: props.supplier?.notes ?? '',
    });
    form.reset();
});

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.supplier) {
        form.put(route('suppliers.update', props.supplier.id), options);
    } else {
        form.post(route('suppliers.store'), options);
    }
}

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    if (!props.supplier) {
        return;
    }

    router.delete(route('suppliers.destroy', props.supplier.id), {
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
                    <DialogTitle>{{ supplier ? $t('suppliers.edit_title') : $t('suppliers.create_title') }}</DialogTitle>
                </DialogHeader>

                <FormField id="supplier-name" :label="$t('suppliers.fields.name')" :error="form.errors.name" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="supplier-name" v-model="form.name" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="supplier-phone" :label="$t('suppliers.fields.phone')" :error="form.errors.phone">
                        <template #default="{ invalid, describedBy }">
                            <Input id="supplier-phone" v-model="form.phone" type="tel" inputmode="tel" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="supplier-sales" :label="$t('suppliers.fields.sales_name')" :error="form.errors.sales_name">
                        <template #default="{ invalid, describedBy }">
                            <Input id="supplier-sales" v-model="form.sales_name" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <Checkbox id="supplier-wa-same" v-model="form.whatsapp_same_as_phone" :label="$t('customers.fields.whatsapp_same')" />

                <FormField v-if="!form.whatsapp_same_as_phone" id="supplier-whatsapp" :label="$t('customers.fields.whatsapp')" :error="form.errors.whatsapp">
                    <template #default="{ invalid, describedBy }">
                        <Input id="supplier-whatsapp" v-model="form.whatsapp" type="tel" inputmode="tel" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="supplier-address" :label="$t('customers.fields.address')" :error="form.errors.address">
                    <template #default="{ invalid, describedBy }">
                        <Textarea id="supplier-address" v-model="form.address" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="supplier-email" :label="$t('fields.email')" :error="form.errors.email">
                        <template #default="{ invalid, describedBy }">
                            <Input id="supplier-email" v-model="form.email" type="email" inputmode="email" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="supplier-npwp" :label="$t('customers.fields.npwp')" :error="form.errors.npwp">
                        <template #default="{ invalid, describedBy }">
                            <Input id="supplier-npwp" v-model="form.npwp" inputmode="numeric" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <FormField id="supplier-notes" :label="$t('customers.fields.notes')" :error="form.errors.notes">
                    <template #default="{ invalid, describedBy }">
                        <Textarea id="supplier-notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <DialogFooter class="sm:justify-between">
                    <Button v-if="supplier && canDelete" type="button" variant="ghost" class="text-destructive" @click="confirmDelete = true">
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
        v-if="supplier"
        v-model:open="confirmDelete"
        :title="$t('suppliers.delete_confirm_title')"
        :description="$t('suppliers.delete_confirm_description', { name: supplier.name })"
        :confirm-label="$t('common.delete')"
        destructive
        :processing="deleting"
        @confirm="destroy"
    />
</template>
