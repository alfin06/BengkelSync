<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import Textarea from '@/Components/ui/Textarea.vue';
import type { WorkOrder } from '@/types/workOrders';

/**
 * Complaint, diagnosis, odometer, estimate and notes.
 */
const props = defineProps<{ workOrder: WorkOrder }>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({ complaint: '', diagnosis: '', odometer_in: '', estimated_completion: '', notes: '' });

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults({
            complaint: props.workOrder.complaint,
            diagnosis: props.workOrder.diagnosis ?? '',
            odometer_in: props.workOrder.odometer_in === null ? '' : String(props.workOrder.odometer_in),
            estimated_completion: props.workOrder.estimated_completion ?? '',
            notes: props.workOrder.notes ?? '',
        });
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    form.transform((data) => ({ ...data, odometer_in: data.odometer_in === '' ? null : Number(data.odometer_in.replace(/\D/g, '')) }))
        .put(route('work-orders.update', props.workOrder.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('work_orders.edit_info') }}</DialogTitle>
                </DialogHeader>

                <FormField id="info-complaint" :label="$t('work_orders.fields.complaint')" :error="form.errors.complaint" required>
                    <template #default="{ invalid, describedBy }">
                        <Textarea id="info-complaint" v-model="form.complaint" rows="3" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="info-diagnosis" :label="$t('work_orders.fields.diagnosis')" :hint="$t('work_orders.diagnosis_hint')" :error="form.errors.diagnosis">
                    <template #default="{ invalid, describedBy }">
                        <Textarea id="info-diagnosis" v-model="form.diagnosis" rows="3" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid grid-cols-2 gap-3">
                    <FormField id="info-odometer" :label="$t('work_orders.fields.odometer_in')" :error="form.errors.odometer_in">
                        <template #default="{ invalid, describedBy }">
                            <Input id="info-odometer" v-model="form.odometer_in" inputmode="numeric" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="info-estimate" :label="$t('work_orders.fields.estimated_completion')" :error="form.errors.estimated_completion">
                        <template #default="{ invalid, describedBy }">
                            <Input id="info-estimate" v-model="form.estimated_completion" type="datetime-local" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <FormField id="info-notes" :label="$t('work_orders.fields.notes')" :error="form.errors.notes">
                    <template #default="{ invalid, describedBy }">
                        <Textarea id="info-notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
