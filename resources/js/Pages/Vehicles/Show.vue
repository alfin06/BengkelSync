<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, ClipboardPlus, Pencil, Trash2, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CustomerPicker from '@/Components/Customers/CustomerPicker.vue';
import WorkOrderStatusBadge from '@/Components/WorkOrders/WorkOrderStatusBadge.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate, formatNumber } from '@/lib/format';
import type { Vehicle, VehicleOwnership } from '@/types/customers';
import type { ServiceHistoryRow } from '@/types/workOrders';

const props = defineProps<{
    vehicle: Vehicle;
    ownerships: VehicleOwnership[];
    /** Work orders of this vehicle in all branches, newest first */
    history: ServiceHistoryRow[];
    can: { update: boolean; delete: boolean; changeOwner: boolean; checkIn: boolean };
}>();

const details = computed(() =>
    [
        { key: 'year', value: props.vehicle.year },
        { key: 'color', value: props.vehicle.color },
        { key: 'transmission', value: props.vehicle.transmission_label },
        { key: 'fuel_type', value: props.vehicle.fuel_type_label },
        { key: 'last_odometer', value: props.vehicle.last_odometer === null ? null : `${formatNumber(props.vehicle.last_odometer)} km` },
        { key: 'vin', value: props.vehicle.vin },
        { key: 'engine_number', value: props.vehicle.engine_number },
    ].filter((detail) => detail.value !== null && detail.value !== ''),
);

const ownerOpen = ref(false);
const ownerForm = useForm<{ customer_id: number | null; reason: string }>({ customer_id: null, reason: '' });

function changeOwner(): void {
    ownerForm.put(route('vehicles.owner.update', props.vehicle.id), {
        preserveScroll: true,
        onSuccess: () => {
            ownerOpen.value = false;
            ownerForm.reset();
        },
    });
}

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    router.delete(route('vehicles.destroy', props.vehicle.id), {
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
        <PageHeader :title="vehicle.plate_number" :description="vehicle.name">
            <template v-if="can.checkIn || can.update || can.changeOwner || can.delete" #actions>
                <Link v-if="can.checkIn" :href="route('work-orders.create', { vehicle: vehicle.id })" :class="buttonVariants()">
                    <ClipboardPlus aria-hidden="true" /> {{ $t('work_orders.add') }}
                </Link>
                <Link v-if="can.update" :href="route('vehicles.edit', vehicle.id)" :class="buttonVariants({ variant: 'outline' })">
                    <Pencil aria-hidden="true" /> {{ $t('common.edit') }}
                </Link>
                <Button v-if="can.changeOwner" variant="outline" @click="ownerOpen = true">
                    <ArrowLeftRight aria-hidden="true" /> {{ $t('vehicles.change_owner') }}
                </Button>
                <Button v-if="can.delete" variant="outline" class="text-destructive" @click="confirmDelete = true">
                    <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>{{ $t('vehicles.details') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <Link
                        v-if="vehicle.customer"
                        :href="route('customers.show', vehicle.customer.id)"
                        class="flex min-h-12 items-center gap-3 rounded-md border px-3 py-2 hover:bg-muted/50"
                    >
                        <UserRound class="size-5 text-muted-foreground" aria-hidden="true" />
                        <span>
                            <span class="block text-xs text-muted-foreground">{{ $t('vehicles.current_owner') }}</span>
                            <span class="font-medium">{{ vehicle.customer.name }}</span>
                        </span>
                    </Link>

                    <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                        <div v-for="detail in details" :key="detail.key">
                            <dt class="text-muted-foreground">{{ $t(`vehicles.fields.${detail.key}`) }}</dt>
                            <dd class="break-words font-medium">{{ detail.value }}</dd>
                        </div>
                    </dl>

                    <div v-if="vehicle.notes" class="text-sm">
                        <p class="text-muted-foreground">{{ $t('vehicles.fields.notes') }}</p>
                        <p class="whitespace-pre-line">{{ vehicle.notes }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('vehicles.owner_history') }}</CardTitle></CardHeader>
                <CardContent>
                    <ol class="grid gap-3">
                        <li v-for="ownership in ownerships" :key="ownership.id" class="rounded-md border p-3 text-sm">
                            <div class="flex items-center justify-between gap-2">
                                <Link :href="route('customers.show', ownership.customer.id)" class="font-medium text-primary hover:underline">
                                    {{ ownership.customer.name }}
                                </Link>
                                <Badge v-if="!ownership.ended_on" tone="success">{{ $t('vehicles.current_owner') }}</Badge>
                            </div>
                            <p class="text-muted-foreground">
                                {{ formatDate(ownership.started_on) }} – {{ ownership.ended_on ? formatDate(ownership.ended_on) : $t('vehicles.now') }}
                            </p>
                            <p v-if="ownership.reason" class="mt-1">{{ ownership.reason }}</p>
                        </li>
                    </ol>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader><CardTitle>{{ $t('work_orders.history') }}</CardTitle></CardHeader>
                <CardContent class="p-0 sm:p-0">
                    <p v-if="history.length === 0" class="px-4 pb-4 text-sm text-muted-foreground sm:px-6">{{ $t('work_orders.history_empty') }}</p>
                    <ul v-else class="divide-y border-t">
                        <li v-for="row in history" :key="row.id">
                            <component
                                :is="row.in_branch ? Link : 'div'"
                                :href="row.in_branch ? route('work-orders.show', row.id) : undefined"
                                class="flex items-start gap-3 px-4 py-3 text-sm sm:px-6"
                                :class="row.in_branch && 'hover:bg-muted/50'"
                            >
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium">{{ row.date_label }} · {{ row.number }}</span>
                                    <span class="line-clamp-2 block">{{ row.complaint }}</span>
                                    <span class="block text-xs text-muted-foreground">
                                        <template v-if="row.odometer_in !== null">{{ formatNumber(row.odometer_in) }} km · </template>{{ row.branch_name }}
                                    </span>
                                </span>
                                <WorkOrderStatusBadge :status="row.status" :label="row.status_label" />
                            </component>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="ownerOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="changeOwner">
                    <DialogHeader>
                        <DialogTitle>{{ $t('vehicles.change_owner') }}</DialogTitle>
                        <DialogDescription>{{ $t('vehicles.change_owner_description', { plate: vehicle.plate_number }) }}</DialogDescription>
                    </DialogHeader>

                    <FormField id="new-owner" :label="$t('vehicles.new_owner')" :error="ownerForm.errors.customer_id" required>
                        <CustomerPicker id="new-owner" v-model="ownerForm.customer_id" :exclude-id="vehicle.customer_id" />
                    </FormField>

                    <FormField id="owner-reason" :label="$t('vehicles.owner_reason')" :hint="$t('common.optional')" :error="ownerForm.errors.reason">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="owner-reason" v-model="ownerForm.reason" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="ownerOpen = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="ownerForm.customer_id === null" :loading="ownerForm.processing">
                            {{ $t('vehicles.change_owner') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="confirmDelete"
            :title="$t('vehicles.delete_confirm_title')"
            :description="$t('vehicles.delete_confirm_description', { plate: vehicle.plate_number })"
            :confirm-label="$t('common.delete')"
            destructive
            :processing="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
