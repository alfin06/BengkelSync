<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import type { Vehicle, VehicleFormOptions } from '@/types/customers';

const props = defineProps<
    VehicleFormOptions & {
        /** Owner, when adding a vehicle */
        customerId?: number;
        /** Existing vehicle, when editing */
        vehicle?: Vehicle;
        cancelUrl: string;
    }
>();

const form = useForm({
    plate_number: props.vehicle?.plate_number ?? '',
    make: props.vehicle?.make ?? '',
    model: props.vehicle?.model ?? '',
    year: props.vehicle?.year ?? null,
    color: props.vehicle?.color ?? '',
    vin: props.vehicle?.vin ?? '',
    engine_number: props.vehicle?.engine_number ?? '',
    transmission: props.vehicle?.transmission ?? null,
    fuel_type: props.vehicle?.fuel_type ?? null,
    last_odometer: props.vehicle?.last_odometer ?? null,
    notes: props.vehicle?.notes ?? '',
    restore_deleted: false,
});

const modelSuggestions = computed(() => props.suggestions.models[form.make.trim()] ?? []);
const confirmRestore = ref(false);

function submit(restore = false): void {
    form.restore_deleted = restore;

    const options = {
        preserveScroll: true,
        // A deleted vehicle has this plate: ask before restoring it (decision 3).
        onError: (errors: Record<string, string>) => (confirmRestore.value = Boolean(errors.restorable)),
    };

    if (props.vehicle) {
        form.put(route('vehicles.update', props.vehicle.id), options);
    } else if (props.customerId !== undefined) {
        form.post(route('vehicles.store', props.customerId), options);
    }
}
</script>

<template>
    <form class="grid gap-5" novalidate @submit.prevent="submit()">
        <FormField id="plate_number" :label="$t('vehicles.fields.plate_number')" :hint="$t('vehicles.plate_hint')" :error="form.errors.plate_number" required>
            <template #default="{ invalid, describedBy }">
                <Input
                    id="plate_number"
                    v-model="form.plate_number"
                    autocomplete="off"
                    autocapitalize="characters"
                    placeholder="B 1234 XYZ"
                    autofocus
                    class="font-mono uppercase"
                    :invalid="invalid"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <div class="grid gap-5 sm:grid-cols-2">
            <FormField id="make" :label="$t('vehicles.fields.make')" :error="form.errors.make" required>
                <template #default="{ invalid, describedBy }">
                    <Input id="make" v-model="form.make" list="make-suggestions" autocomplete="off" placeholder="Toyota" :invalid="invalid" :aria-describedby="describedBy" />
                    <datalist id="make-suggestions">
                        <option v-for="make in suggestions.makes" :key="make" :value="make" />
                    </datalist>
                </template>
            </FormField>

            <FormField id="model" :label="$t('vehicles.fields.model')" :error="form.errors.model" required>
                <template #default="{ invalid, describedBy }">
                    <Input id="model" v-model="form.model" list="model-suggestions" autocomplete="off" placeholder="Avanza" :invalid="invalid" :aria-describedby="describedBy" />
                    <datalist id="model-suggestions">
                        <option v-for="model in modelSuggestions" :key="model" :value="model" />
                    </datalist>
                </template>
            </FormField>

            <FormField id="year" :label="$t('vehicles.fields.year')" :error="form.errors.year">
                <template #default="{ invalid, describedBy }">
                    <Input id="year" v-model.number="form.year" type="number" inputmode="numeric" min="1950" placeholder="2020" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="color" :label="$t('vehicles.fields.color')" :error="form.errors.color">
                <template #default="{ invalid, describedBy }">
                    <Input id="color" v-model="form.color" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="transmission" :label="$t('vehicles.fields.transmission')" :error="form.errors.transmission">
                <template #default="{ invalid, describedBy }">
                    <Select id="transmission" v-model="form.transmission" :options="transmissions" :placeholder="$t('common.choose')" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="fuel_type" :label="$t('vehicles.fields.fuel_type')" :error="form.errors.fuel_type">
                <template #default="{ invalid, describedBy }">
                    <Select id="fuel_type" v-model="form.fuel_type" :options="fuelTypes" :placeholder="$t('common.choose')" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="vin" :label="$t('vehicles.fields.vin')" :error="form.errors.vin">
                <template #default="{ invalid, describedBy }">
                    <Input id="vin" v-model="form.vin" autocomplete="off" autocapitalize="characters" class="font-mono uppercase" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="engine_number" :label="$t('vehicles.fields.engine_number')" :error="form.errors.engine_number">
                <template #default="{ invalid, describedBy }">
                    <Input id="engine_number" v-model="form.engine_number" autocomplete="off" autocapitalize="characters" class="font-mono uppercase" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="last_odometer" :label="$t('vehicles.fields.last_odometer')" :error="form.errors.last_odometer">
                <template #default="{ invalid, describedBy }">
                    <Input id="last_odometer" v-model.number="form.last_odometer" type="number" inputmode="numeric" min="0" placeholder="45000" :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>
        </div>

        <FormField id="notes" :label="$t('vehicles.fields.notes')" :error="form.errors.notes">
            <template #default="{ invalid, describedBy }">
                <Textarea id="notes" v-model="form.notes" rows="3" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
            <Link :href="cancelUrl" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
            <Button type="submit" :loading="form.processing && !confirmRestore">{{ $t('common.save') }}</Button>
        </div>

        <ConfirmDialog
            v-model:open="confirmRestore"
            :title="$t('vehicles.restore_title')"
            :description="$t('vehicles.restore_description', { plate: form.plate_number.toUpperCase() })"
            :confirm-label="$t('vehicles.restore_confirm')"
            :processing="form.processing"
            @confirm="submit(true)"
        />
    </form>
</template>
