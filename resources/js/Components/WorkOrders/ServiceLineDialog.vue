<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import { formatRupiah } from '@/lib/money';
import type { CatalogOption, MechanicOption, WorkOrderServiceLine } from '@/types/workOrders';

/**
 * Add or change a job. Picking a catalog job fills in its name and usual price; both can
 * be changed, the price is what staff agree with the customer.
 */
const props = defineProps<{
    workOrderId: number;
    /** Existing line when editing */
    line: WorkOrderServiceLine | null;
    catalog: CatalogOption[];
    mechanics: MechanicOption[];
}>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({
    service_item_id: null as number | null,
    description: '',
    qty: 1,
    unit_price: null as number | null,
    mechanic_id: null as number | null,
});

const catalogOptions = computed(() => props.catalog.map((item) => ({ value: item.id, label: `${item.name} · ${formatRupiah(item.default_price)}` })));
const mechanicOptions = computed(() => props.mechanics.map((mechanic) => ({ value: mechanic.id, label: mechanic.name })));

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults({
            service_item_id: props.line?.service_item_id ?? null,
            description: props.line?.description ?? '',
            qty: props.line?.qty ?? 1,
            unit_price: props.line?.unit_price ?? null,
            mechanic_id: props.line?.mechanic_id ?? null,
        });
        form.reset();
        form.clearErrors();
    }
});

function pickCatalog(value: string | number | null | undefined): void {
    const item = props.catalog.find((option) => option.id === Number(value));
    form.service_item_id = item?.id ?? null;
    if (item) {
        form.description = item.name;
        form.unit_price = item.default_price;
    }
}

function submit(): void {
    const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

    if (props.line) {
        form.put(route('work-orders.services.update', [props.workOrderId, props.line.id]), options);
    } else {
        form.post(route('work-orders.services.store', props.workOrderId), options);
    }
}

const removing = ref(false);

function remove(): void {
    if (!props.line) {
        return;
    }

    router.delete(route('work-orders.services.destroy', [props.workOrderId, props.line.id]), {
        preserveScroll: true,
        onStart: () => (removing.value = true),
        onFinish: () => (removing.value = false),
        onSuccess: () => (open.value = false),
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ line ? $t('work_orders.edit_service') : $t('work_orders.add_service') }}</DialogTitle>
                </DialogHeader>

                <FormField v-if="!line && catalog.length" id="service-catalog" :label="$t('work_orders.from_catalog')" :hint="$t('work_orders.from_catalog_hint')">
                    <template #default="{ describedBy }">
                        <Select id="service-catalog" :model-value="form.service_item_id" :options="catalogOptions" :placeholder="$t('common.choose')" :aria-describedby="describedBy" @update:model-value="pickCatalog" />
                    </template>
                </FormField>

                <FormField id="service-description" :label="$t('work_orders.fields.service')" :error="form.errors.description" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="service-description" v-model="form.description" autocomplete="off" :placeholder="$t('work_orders.service_placeholder')" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid grid-cols-[5rem_1fr] gap-3">
                    <FormField id="service-qty" :label="$t('work_orders.fields.qty')" :error="form.errors.qty" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="service-qty" v-model.number="form.qty" type="number" inputmode="numeric" min="1" max="999" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="service-price" :label="$t('work_orders.fields.price')" :error="form.errors.unit_price" required>
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput id="service-price" v-model="form.unit_price" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <FormField v-if="mechanics.length" id="service-mechanic" :label="$t('work_orders.fields.mechanic')" :error="form.errors.mechanic_id">
                    <template #default="{ invalid, describedBy }">
                        <Select id="service-mechanic" v-model="form.mechanic_id" :options="[{ value: '', label: $t('work_orders.no_mechanic') }, ...mechanicOptions]" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <DialogFooter class="sm:justify-between">
                    <Button v-if="line" type="button" variant="ghost" class="text-destructive" :loading="removing" @click="remove">
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
</template>
