<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Ban, FileText, Pencil, Plus, Printer, Users, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MechanicToggles from '@/Components/WorkOrders/MechanicToggles.vue';
import PartLineDialog from '@/Components/WorkOrders/PartLineDialog.vue';
import ServiceLineDialog from '@/Components/WorkOrders/ServiceLineDialog.vue';
import WorkOrderInfoDialog from '@/Components/WorkOrders/WorkOrderInfoDialog.vue';
import WorkOrderStatusBadge from '@/Components/WorkOrders/WorkOrderStatusBadge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { CatalogOption, MechanicOption, WorkOrder, WorkOrderPartLine, WorkOrderServiceLine, WorkOrderTask } from '@/types/workOrders';

const props = defineProps<{
    workOrder: WorkOrder;
    /** Statuses this user may move to, with their button text */
    nextStatuses: { value: string; label: string }[];
    catalog: CatalogOption[];
    mechanics: MechanicOption[];
    /** The invoice of this work order, once invoiced */
    invoice: { id: number; number: string } | null;
    can: { invoice: boolean; update: boolean; editLines: boolean; assign: boolean; cancel: boolean };
}>();

// Status buttons: the first next step is the main action ("Mulai kerjakan", "Selesai").
const statusForm = useForm({ status: '' });

function moveTo(status: string): void {
    statusForm.status = status;
    statusForm.put(route('work-orders.status', props.workOrder.id), { preserveScroll: true });
}

const infoOpen = ref(false);

// Checklist: ticking saves right away.
const taskForm = useForm({ description: '' });

function addTask(): void {
    if (taskForm.description.trim() === '') {
        return;
    }

    taskForm.post(route('work-orders.tasks.store', props.workOrder.id), { preserveScroll: true, onSuccess: () => taskForm.reset() });
}

function toggleTask(task: WorkOrderTask): void {
    router.put(route('work-orders.tasks.update', [props.workOrder.id, task.id]), { is_done: !task.is_done }, { preserveScroll: true });
}

function removeTask(task: WorkOrderTask): void {
    router.delete(route('work-orders.tasks.destroy', [props.workOrder.id, task.id]), { preserveScroll: true });
}

const tasksDone = computed(() => (props.workOrder.tasks ?? []).filter((task) => task.is_done).length);

// Mechanics on the job.
const mechanicsOpen = ref(false);
const mechanicsForm = useForm({ mechanic_ids: [] as number[] });

function openMechanics(): void {
    mechanicsForm.mechanic_ids = (props.workOrder.mechanics ?? []).map((mechanic) => mechanic.id);
    mechanicsForm.clearErrors();
    mechanicsOpen.value = true;
}

function saveMechanics(): void {
    mechanicsForm.put(route('work-orders.mechanics', props.workOrder.id), { preserveScroll: true, onSuccess: () => (mechanicsOpen.value = false) });
}

// Assigned mechanics who were deactivated since still show in the picker.
const mechanicChoices = computed(() => {
    const assigned = props.workOrder.mechanics ?? [];

    return [...props.mechanics, ...assigned.filter((mechanic) => !props.mechanics.some((option) => option.id === mechanic.id))];
});

// Services and parts.
const serviceOpen = ref(false);
const editingService = ref<WorkOrderServiceLine | null>(null);

function openService(line: WorkOrderServiceLine | null): void {
    editingService.value = line;
    serviceOpen.value = true;
}

const partOpen = ref(false);
const editingPart = ref<WorkOrderPartLine | null>(null);

function openPart(line: WorkOrderPartLine | null): void {
    editingPart.value = line;
    partOpen.value = true;
}

// Cancel (with a reason): parts go back on the shelf.
const cancelOpen = ref(false);
const cancelling = ref(false);

function cancel(reason: string | null): void {
    router.post(route('work-orders.cancel', props.workOrder.id), { reason }, {
        preserveScroll: true,
        onStart: () => (cancelling.value = true),
        onFinish: () => (cancelling.value = false),
        onSuccess: () => (cancelOpen.value = false),
    });
}

const hasParts = computed(() => (props.workOrder.parts ?? []).length > 0);
</script>

<template>
    <AppLayout>
        <PageHeader :title="workOrder.vehicle.plate_number" :description="`${workOrder.number} · ${workOrder.vehicle.name}`">
            <template #actions>
                <Link v-if="can.invoice" :href="route('invoices.create', { work_order: workOrder.id })" :class="buttonVariants()">
                    <FileText aria-hidden="true" /> {{ $t('invoices.create_button') }}
                </Link>
                <Link v-if="invoice" :href="route('invoices.show', invoice.id)" :class="buttonVariants({ variant: 'outline' })">
                    <FileText aria-hidden="true" /> {{ invoice.number }}
                </Link>
                <a :href="route('work-orders.print', workOrder.id)" target="_blank" rel="noopener" :class="buttonVariants({ variant: 'outline' })">
                    <Printer aria-hidden="true" /> {{ $t('work_orders.print') }}
                </a>
                <Button v-if="can.cancel" variant="outline" class="text-destructive" @click="cancelOpen = true">
                    <Ban aria-hidden="true" /> {{ $t('work_orders.cancel') }}
                </Button>
            </template>
        </PageHeader>

        <p v-if="workOrder.status === 'cancelled'" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {{ $t('work_orders.cancelled_notice', { reason: workOrder.cancel_reason ?? '' }) }}
        </p>

        <!-- Status and the next step, big enough to tap with greasy fingers. -->
        <Card class="mb-6">
            <CardContent class="grid gap-4 pt-4 sm:flex sm:items-center sm:pt-6">
                <div class="flex-1">
                    <WorkOrderStatusBadge :status="workOrder.status" :label="workOrder.status_label" />
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ $t('work_orders.checked_in', { date: workOrder.created_at_label }) }}
                        <template v-if="workOrder.started_at_label"> · {{ $t('work_orders.started', { date: workOrder.started_at_label }) }}</template>
                        <template v-if="workOrder.completed_at_label"> · {{ $t('work_orders.finished', { date: workOrder.completed_at_label }) }}</template>
                    </p>
                </div>
                <div v-if="nextStatuses.length" class="grid gap-2 sm:flex">
                    <Button
                        v-for="(next, index) in nextStatuses"
                        :key="next.value"
                        size="lg"
                        :variant="index === 0 ? 'default' : 'outline'"
                        :loading="statusForm.processing && statusForm.status === next.value"
                        :disabled="statusForm.processing"
                        @click="moveTo(next.value)"
                    >
                        {{ next.label }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="grid gap-6 lg:col-span-2">
                <!-- Services -->
                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('work_orders.services') }}</CardTitle>
                        <Button v-if="can.editLines" size="sm" @click="openService(null)"><Plus aria-hidden="true" /> {{ $t('work_orders.add_service') }}</Button>
                    </CardHeader>
                    <CardContent class="p-0 sm:p-0">
                        <p v-if="!workOrder.services?.length" class="px-4 pb-4 text-sm text-muted-foreground sm:px-6">{{ $t('work_orders.no_services') }}</p>
                        <ul v-else class="divide-y border-t">
                            <li v-for="line in workOrder.services" :key="line.id">
                                <component
                                    :is="can.editLines ? 'button' : 'div'"
                                    :type="can.editLines ? 'button' : undefined"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left text-sm sm:px-6"
                                    :class="can.editLines && 'hover:bg-muted/50'"
                                    @click="can.editLines && openService(line)"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium">{{ line.description }}</span>
                                        <span class="block text-muted-foreground">
                                            <template v-if="line.qty > 1">{{ line.qty }} × {{ line.unit_price_label }}</template>
                                            <template v-if="line.qty > 1 && line.mechanic_name"> · </template>
                                            <template v-if="line.mechanic_name">{{ line.mechanic_name }}</template>
                                        </span>
                                    </span>
                                    <span class="font-medium tabular-nums">{{ line.line_total_label }}</span>
                                </component>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <!-- Parts -->
                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('work_orders.parts') }}</CardTitle>
                        <Button v-if="can.editLines" size="sm" @click="openPart(null)"><Plus aria-hidden="true" /> {{ $t('work_orders.add_part') }}</Button>
                    </CardHeader>
                    <CardContent class="p-0 sm:p-0">
                        <p v-if="!hasParts" class="px-4 pb-4 text-sm text-muted-foreground sm:px-6">{{ $t('work_orders.no_parts') }}</p>
                        <ul v-else class="divide-y border-t">
                            <li v-for="line in workOrder.parts" :key="line.id">
                                <component
                                    :is="can.editLines ? 'button' : 'div'"
                                    :type="can.editLines ? 'button' : undefined"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left text-sm sm:px-6"
                                    :class="can.editLines && 'hover:bg-muted/50'"
                                    @click="can.editLines && openPart(line)"
                                >
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-medium">{{ line.part_name }}</span>
                                        <span class="block text-muted-foreground tabular-nums">{{ line.qty_label }} {{ line.unit_label }} × {{ line.unit_price_label }}</span>
                                    </span>
                                    <span class="font-medium tabular-nums">{{ line.line_total_label }}</span>
                                </component>
                            </li>
                        </ul>
                        <div class="flex justify-between border-t px-4 py-3 font-semibold sm:px-6">
                            <span>{{ $t('work_orders.estimated_total') }}</span>
                            <span class="tabular-nums">{{ workOrder.estimated_total_label }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Checklist -->
                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('work_orders.fields.tasks') }}</CardTitle>
                        <span v-if="workOrder.tasks?.length" class="text-sm text-muted-foreground tabular-nums">{{ tasksDone }}/{{ workOrder.tasks.length }}</span>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <p v-if="!workOrder.tasks?.length" class="text-sm text-muted-foreground">{{ $t('work_orders.no_tasks') }}</p>
                        <ul v-else class="grid gap-1">
                            <li v-for="task in workOrder.tasks" :key="task.id" class="flex items-center gap-2">
                                <label class="flex min-h-11 flex-1 cursor-pointer items-center gap-3 rounded-md px-2 hover:bg-muted/50">
                                    <input type="checkbox" class="size-5 rounded border-input text-primary" :checked="task.is_done" :disabled="!can.editLines" @change="toggleTask(task)" />
                                    <span :class="cn('text-sm', task.is_done && 'text-muted-foreground line-through')">{{ task.description }}</span>
                                </label>
                                <button
                                    v-if="can.editLines"
                                    type="button"
                                    class="inline-flex size-9 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                                    :aria-label="$t('work_orders.remove_task', { task: task.description })"
                                    @click="removeTask(task)"
                                >
                                    <X class="size-4" aria-hidden="true" />
                                </button>
                            </li>
                        </ul>
                        <form v-if="can.editLines" class="flex gap-2" @submit.prevent="addTask">
                            <Input v-model="taskForm.description" autocomplete="off" :placeholder="$t('work_orders.task_placeholder')" :aria-label="$t('work_orders.add_task')" class="flex-1" />
                            <Button type="submit" variant="outline" :loading="taskForm.processing" :aria-label="$t('work_orders.add_task')"><Plus aria-hidden="true" /></Button>
                        </form>
                    </CardContent>
                </Card>
            </div>

            <div class="grid h-fit gap-6">
                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('work_orders.info') }}</CardTitle>
                        <Button v-if="can.update" size="sm" variant="ghost" @click="infoOpen = true"><Pencil aria-hidden="true" /> {{ $t('common.edit') }}</Button>
                    </CardHeader>
                    <CardContent class="grid gap-3 text-sm">
                        <div>
                            <p class="text-muted-foreground">{{ $t('work_orders.fields.customer') }}</p>
                            <Link :href="route('customers.show', workOrder.customer.id)" class="font-medium text-primary hover:underline">{{ workOrder.customer.name }}</Link>
                            <p v-if="workOrder.customer.phone">{{ workOrder.customer.phone }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ $t('work_orders.fields.vehicle') }}</p>
                            <Link :href="route('vehicles.show', workOrder.vehicle.id)" class="font-medium text-primary hover:underline">{{ workOrder.vehicle.plate_number }}</Link>
                            <p>{{ workOrder.vehicle.name }}</p>
                        </div>
                        <div>
                            <p class="text-muted-foreground">{{ $t('work_orders.fields.complaint') }}</p>
                            <p class="whitespace-pre-line">{{ workOrder.complaint }}</p>
                        </div>
                        <div v-if="workOrder.diagnosis">
                            <p class="text-muted-foreground">{{ $t('work_orders.fields.diagnosis') }}</p>
                            <p class="whitespace-pre-line">{{ workOrder.diagnosis }}</p>
                        </div>
                        <div v-if="workOrder.odometer_in !== null" class="flex justify-between">
                            <span class="text-muted-foreground">{{ $t('work_orders.fields.odometer_in') }}</span>
                            <span class="tabular-nums">{{ formatNumber(workOrder.odometer_in) }} km</span>
                        </div>
                        <div v-if="workOrder.estimated_completion_label" class="flex justify-between gap-2">
                            <span class="text-muted-foreground">{{ $t('work_orders.fields.estimated_completion') }}</span>
                            <span class="text-right">{{ workOrder.estimated_completion_label }}</span>
                        </div>
                        <div v-if="workOrder.notes">
                            <p class="text-muted-foreground">{{ $t('work_orders.fields.notes') }}</p>
                            <p class="whitespace-pre-line">{{ workOrder.notes }}</p>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('work_orders.fields.mechanics') }}</CardTitle>
                        <Button v-if="can.assign" size="sm" variant="ghost" @click="openMechanics"><Users aria-hidden="true" /> {{ $t('work_orders.assign') }}</Button>
                    </CardHeader>
                    <CardContent class="text-sm">
                        <p v-if="!workOrder.mechanics?.length" class="text-muted-foreground">{{ $t('work_orders.no_mechanic_assigned') }}</p>
                        <ul v-else class="grid gap-1">
                            <li v-for="mechanic in workOrder.mechanics" :key="mechanic.id" class="font-medium">{{ mechanic.name }}</li>
                        </ul>
                    </CardContent>
                </Card>
            </div>
        </div>

        <WorkOrderInfoDialog v-if="can.update" v-model:open="infoOpen" :work-order="workOrder" />
        <ServiceLineDialog v-if="can.editLines" v-model:open="serviceOpen" :work-order-id="workOrder.id" :line="editingService" :catalog="catalog" :mechanics="mechanics" />
        <PartLineDialog v-if="can.editLines" v-model:open="partOpen" :work-order-id="workOrder.id" :line="editingPart" />

        <Dialog v-if="can.assign" v-model:open="mechanicsOpen">
            <DialogContent>
                <form class="grid gap-4" @submit.prevent="saveMechanics">
                    <DialogHeader>
                        <DialogTitle>{{ $t('work_orders.fields.mechanics') }}</DialogTitle>
                    </DialogHeader>
                    <MechanicToggles v-model="mechanicsForm.mechanic_ids" :mechanics="mechanicChoices" :label="$t('work_orders.fields.mechanics')" />
                    <p v-if="mechanicsForm.errors.mechanic_ids" class="text-sm font-medium text-destructive" role="alert">{{ mechanicsForm.errors.mechanic_ids }}</p>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="mechanicsOpen = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="mechanicsForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="cancelOpen"
            :title="$t('work_orders.cancel_title', { number: workOrder.number })"
            :description="hasParts ? $t('work_orders.cancel_description_parts') : $t('work_orders.cancel_description')"
            :confirm-label="$t('work_orders.cancel')"
            destructive
            require-reason
            :processing="cancelling"
            @confirm="cancel"
        />
    </AppLayout>
</template>
