<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { formatNumber } from '@/lib/format';
import type { WorkOrder } from '@/types/workOrders';

/**
 * The job sheet ("SPK") for the mechanic's bay. No prices: the mechanic only needs to
 * know what to check, do and fit. Printed with the browser (A4 or A5).
 */
defineProps<{ workOrder: WorkOrder; branchName: string }>();

const page = usePage();

const print = (): void => window.print();

onMounted(print);
</script>

<template>
    <Head :title="workOrder.number" />

    <div class="min-h-screen bg-muted/40 print:bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-2 px-4 py-3 print:hidden">
            <Link :href="route('work-orders.show', workOrder.id)" :class="buttonVariants({ variant: 'ghost' })"><ArrowLeft aria-hidden="true" /> {{ $t('common.back') }}</Link>
            <Button @click="print"><Printer aria-hidden="true" /> {{ $t('work_orders.print') }}</Button>
        </div>

        <article class="mx-auto max-w-3xl bg-white p-8 text-sm text-black shadow print:max-w-none print:p-0 print:shadow-none">
            <header class="flex items-start justify-between gap-4 border-b-2 border-black pb-3">
                <div>
                    <p class="text-lg font-bold">{{ page.props.tenant?.name }}</p>
                    <p>{{ branchName }}</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-bold">{{ $t('work_orders.print_title') }}</p>
                    <p class="font-mono">{{ workOrder.number }}</p>
                    <p>{{ workOrder.created_at_label }}</p>
                </div>
            </header>

            <section class="grid grid-cols-2 gap-x-6 gap-y-1 border-b py-3">
                <p><span class="font-semibold">{{ $t('work_orders.fields.vehicle') }}:</span> {{ workOrder.vehicle.plate_number }}</p>
                <p><span class="font-semibold">{{ $t('work_orders.fields.customer') }}:</span> {{ workOrder.customer.name }}</p>
                <p>{{ workOrder.vehicle.name }}</p>
                <p>{{ workOrder.customer.phone }}</p>
                <p v-if="workOrder.odometer_in !== null"><span class="font-semibold">{{ $t('work_orders.fields.odometer_in') }}:</span> {{ formatNumber(workOrder.odometer_in) }} km</p>
                <p v-if="workOrder.estimated_completion_label"><span class="font-semibold">{{ $t('work_orders.fields.estimated_completion') }}:</span> {{ workOrder.estimated_completion_label }}</p>
            </section>

            <section class="border-b py-3">
                <h2 class="font-semibold">{{ $t('work_orders.fields.complaint') }}</h2>
                <p class="whitespace-pre-line">{{ workOrder.complaint }}</p>
                <template v-if="workOrder.diagnosis">
                    <h2 class="mt-2 font-semibold">{{ $t('work_orders.fields.diagnosis') }}</h2>
                    <p class="whitespace-pre-line">{{ workOrder.diagnosis }}</p>
                </template>
            </section>

            <section v-if="workOrder.tasks?.length" class="border-b py-3">
                <h2 class="mb-1 font-semibold">{{ $t('work_orders.fields.tasks') }}</h2>
                <ul class="grid gap-1">
                    <li v-for="task in workOrder.tasks" :key="task.id" class="flex items-center gap-2">
                        <span class="inline-block size-4 shrink-0 border border-black text-center leading-3">{{ task.is_done ? '✓' : '' }}</span>
                        {{ task.description }}
                    </li>
                </ul>
            </section>

            <section v-if="workOrder.services?.length" class="border-b py-3">
                <h2 class="mb-1 font-semibold">{{ $t('work_orders.services') }}</h2>
                <ul class="grid gap-1">
                    <li v-for="line in workOrder.services" :key="line.id" class="flex justify-between gap-4">
                        <span>{{ line.qty > 1 ? `${line.qty} × ` : '' }}{{ line.description }}</span>
                        <span v-if="line.mechanic_name">{{ line.mechanic_name }}</span>
                    </li>
                </ul>
            </section>

            <section v-if="workOrder.parts?.length" class="border-b py-3">
                <h2 class="mb-1 font-semibold">{{ $t('work_orders.parts') }}</h2>
                <ul class="grid gap-1">
                    <li v-for="line in workOrder.parts" :key="line.id" class="flex justify-between gap-4">
                        <span>{{ line.part_name }}</span>
                        <span class="tabular-nums">{{ line.qty_label }} {{ line.unit_label }}</span>
                    </li>
                </ul>
            </section>

            <section class="border-b py-3">
                <h2 class="font-semibold">{{ $t('work_orders.fields.mechanics') }}</h2>
                <p>{{ workOrder.mechanics?.map((mechanic) => mechanic.name).join(', ') || '—' }}</p>
            </section>

            <section class="py-3">
                <h2 class="font-semibold">{{ $t('work_orders.print_notes') }}</h2>
                <p v-if="workOrder.notes" class="whitespace-pre-line">{{ workOrder.notes }}</p>
                <div class="mt-2 h-20 border border-dashed border-gray-400" />
            </section>

            <footer class="mt-8 grid grid-cols-3 gap-6 text-center">
                <div v-for="role in ['print_sign_customer', 'print_sign_front_desk', 'print_sign_mechanic']" :key="role">
                    <div class="h-16 border-b border-black" />
                    <p class="mt-1">{{ $t(`work_orders.${role}`) }}</p>
                </div>
            </footer>
        </article>
    </div>
</template>

<style>
@media print {
    @page {
        margin: 12mm;
    }
}
</style>
