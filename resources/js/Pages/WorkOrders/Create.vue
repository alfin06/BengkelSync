<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { Car, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CustomerPicker from '@/Components/Customers/CustomerPicker.vue';
import MechanicToggles from '@/Components/WorkOrders/MechanicToggles.vue';
import TaskListInput from '@/Components/WorkOrders/TaskListInput.vue';
import VehicleSearch from '@/Components/WorkOrders/VehicleSearch.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { MechanicOption, VehicleOption } from '@/types/workOrders';

const props = defineProps<{
    /** Chosen already when coming from a vehicle page */
    vehicle: VehicleOption | null;
    mechanics: MechanicOption[];
    suggestions: { makes: string[]; models: Record<string, string[]> };
    can: { createVehicle: boolean; createCustomer: boolean; assign: boolean };
}>();

const chosen = ref<VehicleOption | null>(props.vehicle);
const customerMode = ref<'existing' | 'new'>(props.can.createCustomer ? 'new' : 'existing');

const form = useForm({
    mode: (props.vehicle ? 'existing' : '') as 'existing' | 'new' | '',
    vehicle_id: props.vehicle?.id ?? (null as number | null),
    customer_id: null as number | null,
    customer: { name: '', phone: '' },
    vehicle: { plate_number: '', make: '', model: '', year: '', color: '' },
    complaint: '',
    odometer_in: '',
    estimated_completion: '',
    notes: '',
    tasks: [] as string[],
    mechanic_ids: [] as number[],
    draft: false,
});

const errors = computed(() => form.errors as Partial<Record<string, string>>);
const modelSuggestions = computed(() => props.suggestions.models[form.vehicle.make.trim()] ?? []);

function pick(vehicle: VehicleOption): void {
    chosen.value = vehicle;
    form.mode = 'existing';
    form.vehicle_id = vehicle.id;
}

function startNew(plate: string): void {
    chosen.value = null;
    form.mode = 'new';
    form.vehicle_id = null;
    form.vehicle.plate_number = plate.toUpperCase();
}

function reset(): void {
    chosen.value = null;
    form.mode = '';
    form.vehicle_id = null;
}

// Last reading of a known vehicle: a lower number is probably a typo.
const lastOdometer = computed(() => (form.mode === 'existing' ? (chosen.value?.last_odometer ?? null) : null));
const odometerLower = computed(() => lastOdometer.value !== null && form.odometer_in !== '' && Number(form.odometer_in) < lastOdometer.value);

function onOdometer(event: Event): void {
    const input = event.target as HTMLInputElement;
    form.odometer_in = input.value.replace(/\D/g, '').slice(0, 7);
    input.value = form.odometer_in;
}

function submit(): void {
    form.transform((data) => ({
        ...data,
        customer_id: data.mode === 'new' && customerMode.value === 'existing' ? data.customer_id : null,
        customer: data.mode === 'new' && customerMode.value === 'new' ? data.customer : undefined,
        vehicle: data.mode === 'new' ? data.vehicle : undefined,
        odometer_in: data.odometer_in === '' ? null : Number(data.odometer_in),
        mechanic_ids: props.can.assign ? data.mechanic_ids : [],
    }));
    form.post(route('work-orders.store'));
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('work_orders.create_title')" :description="$t('work_orders.create_description')" />

        <form class="grid max-w-3xl gap-6" novalidate @submit.prevent="submit">
            <Card>
                <CardHeader><CardTitle>{{ $t('work_orders.fields.vehicle') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="chosen" class="flex items-center gap-3 rounded-md border bg-muted/40 p-3">
                        <Car class="size-6 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <span class="min-w-0 flex-1">
                            <span class="block text-lg font-semibold">{{ chosen.plate_number }}</span>
                            <span class="block text-sm text-muted-foreground">{{ chosen.name }} · {{ chosen.customer_name }}</span>
                            <span v-if="chosen.open_work_order" class="block text-sm font-medium text-amber-700">{{ $t('work_orders.has_open', { number: chosen.open_work_order }) }}</span>
                        </span>
                        <Button type="button" variant="ghost" size="icon" :aria-label="$t('work_orders.change_vehicle')" @click="reset"><X aria-hidden="true" /></Button>
                    </div>

                    <template v-else-if="form.mode === 'new'">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-medium">{{ $t('work_orders.new_vehicle') }}</p>
                            <button type="button" class="text-sm font-medium text-primary underline-offset-4 hover:underline" @click="reset">{{ $t('work_orders.search_again') }}</button>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormField id="vehicle-plate" :label="$t('vehicles.fields.plate_number')" :error="errors['vehicle.plate_number']" required>
                                <template #default="{ invalid, describedBy }">
                                    <Input id="vehicle-plate" v-model="form.vehicle.plate_number" autocapitalize="characters" class="uppercase" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                            <FormField id="vehicle-make" :label="$t('vehicles.fields.make')" :error="errors['vehicle.make']" required>
                                <template #default="{ invalid, describedBy }">
                                    <Input id="vehicle-make" v-model="form.vehicle.make" list="make-suggestions" autocomplete="off" placeholder="Toyota" :invalid="invalid" :aria-describedby="describedBy" />
                                    <datalist id="make-suggestions"><option v-for="make in suggestions.makes" :key="make" :value="make" /></datalist>
                                </template>
                            </FormField>
                            <FormField id="vehicle-model" :label="$t('vehicles.fields.model')" :error="errors['vehicle.model']" required>
                                <template #default="{ invalid, describedBy }">
                                    <Input id="vehicle-model" v-model="form.vehicle.model" list="model-suggestions" autocomplete="off" placeholder="Avanza" :invalid="invalid" :aria-describedby="describedBy" />
                                    <datalist id="model-suggestions"><option v-for="model in modelSuggestions" :key="model" :value="model" /></datalist>
                                </template>
                            </FormField>
                            <div class="grid grid-cols-2 gap-4">
                                <FormField id="vehicle-year" :label="$t('vehicles.fields.year')" :error="errors['vehicle.year']">
                                    <template #default="{ invalid, describedBy }">
                                        <Input id="vehicle-year" v-model="form.vehicle.year" inputmode="numeric" placeholder="2020" :invalid="invalid" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                                <FormField id="vehicle-color" :label="$t('vehicles.fields.color')" :error="errors['vehicle.color']">
                                    <template #default="{ invalid, describedBy }">
                                        <Input id="vehicle-color" v-model="form.vehicle.color" :invalid="invalid" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                            </div>
                        </div>

                        <fieldset class="grid gap-3 rounded-md border p-3">
                            <legend class="px-1 text-sm font-medium">{{ $t('work_orders.fields.customer') }}</legend>
                            <div v-if="can.createCustomer" class="grid grid-cols-2 gap-2" role="radiogroup">
                                <button
                                    v-for="option in (['new', 'existing'] as const)"
                                    :key="option"
                                    type="button"
                                    role="radio"
                                    :aria-checked="customerMode === option"
                                    :class="cn('min-h-11 rounded-md border text-sm font-medium', customerMode === option ? 'border-primary bg-primary/10 text-primary' : 'hover:bg-muted')"
                                    @click="customerMode = option"
                                >
                                    {{ $t(`work_orders.customer_${option}`) }}
                                </button>
                            </div>

                            <div v-if="customerMode === 'new'" class="grid gap-4 sm:grid-cols-2">
                                <FormField id="customer-name" :label="$t('customers.fields.name')" :error="errors['customer.name']" required>
                                    <template #default="{ invalid, describedBy }">
                                        <Input id="customer-name" v-model="form.customer.name" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                                <FormField id="customer-phone" :label="$t('fields.phone')" :error="errors['customer.phone']">
                                    <template #default="{ invalid, describedBy }">
                                        <Input id="customer-phone" v-model="form.customer.phone" type="tel" inputmode="tel" placeholder="0812…" :invalid="invalid" :aria-describedby="describedBy" />
                                    </template>
                                </FormField>
                            </div>
                            <FormField v-else id="customer-search" :label="$t('work_orders.find_customer')" :error="errors.customer_id">
                                <CustomerPicker id="customer-search" v-model="form.customer_id" />
                            </FormField>
                        </fieldset>
                    </template>

                    <FormField v-else id="vehicle-search" :label="$t('work_orders.plate_label')" :error="errors.vehicle_id ?? errors.mode">
                        <template #default="{ invalid, describedBy }">
                            <VehicleSearch id="vehicle-search" :can-create="can.createVehicle" :invalid="invalid" :described-by="describedBy" @pick="pick" @create="startNew" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('work_orders.job') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-5">
                    <FormField id="complaint" :label="$t('work_orders.fields.complaint')" :error="form.errors.complaint" required>
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="complaint" v-model="form.complaint" rows="3" :placeholder="$t('work_orders.complaint_placeholder')" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField
                            id="odometer_in"
                            :label="$t('work_orders.fields.odometer_in')"
                            :hint="lastOdometer !== null ? $t('work_orders.last_odometer', { km: formatNumber(lastOdometer) }) : undefined"
                            :error="form.errors.odometer_in"
                        >
                            <template #default="{ invalid, describedBy }">
                                <div class="relative">
                                    <input
                                        id="odometer_in"
                                        :value="form.odometer_in"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        :aria-invalid="invalid || undefined"
                                        :aria-describedby="describedBy"
                                        class="flex h-11 w-full rounded-md border border-input bg-background py-2 pl-3 pr-12 text-right text-base tabular-nums shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-10 sm:text-sm"
                                        @input="onOdometer"
                                    />
                                    <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">km</span>
                                </div>
                                <p v-if="odometerLower" class="text-sm font-medium text-amber-700" role="status">{{ $t('work_orders.odometer_lower') }}</p>
                            </template>
                        </FormField>

                        <FormField id="estimated_completion" :label="$t('work_orders.fields.estimated_completion')" :error="form.errors.estimated_completion">
                            <template #default="{ invalid, describedBy }">
                                <Input id="estimated_completion" v-model="form.estimated_completion" type="datetime-local" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>

                    <FormField id="task-input" :label="$t('work_orders.fields.tasks')" :hint="$t('work_orders.tasks_hint')" :error="errors.tasks">
                        <TaskListInput id="task-input" v-model="form.tasks" />
                    </FormField>

                    <FormField v-if="can.assign" id="mechanics" :label="$t('work_orders.fields.mechanics')" :error="errors.mechanic_ids">
                        <MechanicToggles v-model="form.mechanic_ids" :mechanics="mechanics" :label="$t('work_orders.fields.mechanics')" />
                    </FormField>

                    <FormField id="notes" :label="$t('work_orders.fields.notes')" :error="form.errors.notes">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <Checkbox id="draft" v-model="form.draft" :label="$t('work_orders.save_as_draft')" />
                </CardContent>
            </Card>

            <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
                <Link :href="route('work-orders.index')" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
                <Button type="button" :loading="form.processing" :disabled="form.mode === ''" @click="submit">
                    {{ form.draft ? $t('work_orders.save_draft') : $t('work_orders.save_queue') }}
                </Button>
            </div>
        </form>
    </AppLayout>
</template>
