<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Ban, Printer } from 'lucide-vue-next';
import { ref } from 'vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Invoice } from '@/types/invoices';

const props = defineProps<{
    invoice: Invoice;
    can: { void: boolean };
}>();

const voidOpen = ref(false);
const voiding = ref(false);

function voidInvoice(reason: string | null): void {
    router.post(route('invoices.void', props.invoice.id), { reason }, {
        preserveScroll: true,
        onStart: () => (voiding.value = true),
        onFinish: () => (voiding.value = false),
        onSuccess: () => (voidOpen.value = false),
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="invoice.number" :description="invoice.customer?.name ?? $t('invoices.walk_in_customer')">
            <template #actions>
                <a :href="route('invoices.print', invoice.id)" target="_blank" rel="noopener" :class="buttonVariants({ variant: 'outline' })">
                    <Printer aria-hidden="true" /> {{ $t('invoices.print') }}
                </a>
                <Button v-if="can.void" variant="outline" class="text-destructive" @click="voidOpen = true">
                    <Ban aria-hidden="true" /> {{ $t('invoices.void') }}
                </Button>
            </template>
        </PageHeader>

        <p v-if="invoice.status === 'void'" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {{ $t('invoices.void_notice', { reason: invoice.void_reason ?? '' }) }}
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="lg:col-span-2">
                <CardHeader><CardTitle>{{ $t('invoices.lines') }}</CardTitle></CardHeader>
                <CardContent class="p-0 sm:p-0">
                    <ul class="divide-y border-t">
                        <li v-for="line in invoice.lines" :key="line.id" class="flex items-start gap-3 px-4 py-3 text-sm sm:px-6">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">{{ line.description }}</span>
                                <span class="block text-muted-foreground tabular-nums">
                                    {{ line.qty_label }} {{ line.unit_label ?? '' }} × {{ line.unit_price_label }}
                                    <template v-if="line.discount_amount > 0"> · {{ $t('invoices.fields.discount') }} {{ line.discount_label }}</template>
                                </span>
                            </span>
                            <span class="font-medium tabular-nums">{{ line.line_total_label }}</span>
                        </li>
                    </ul>
                    <div class="grid gap-2 border-t px-4 py-3 text-sm sm:px-6">
                        <div class="flex justify-between"><span>{{ $t('invoices.subtotal') }}</span><span class="tabular-nums">{{ invoice.subtotal_label }}</span></div>
                        <div v-if="invoice.discount_amount > 0" class="flex justify-between">
                            <span>{{ $t('invoices.fields.invoice_discount') }}</span><span class="tabular-nums">−{{ invoice.discount_label }}</span>
                        </div>
                        <div v-if="invoice.tax_enabled" class="flex justify-between text-muted-foreground">
                            <span>{{ invoice.tax_inclusive ? $t('invoices.tax_included', { name: invoice.tax_name, rate: invoice.tax_rate_label }) : `${invoice.tax_name} ${invoice.tax_rate_label}` }}</span>
                            <span class="tabular-nums">{{ invoice.tax_label }}</span>
                        </div>
                        <div class="flex justify-between border-t pt-2 text-base font-semibold"><span>{{ $t('invoices.total') }}</span><span class="tabular-nums">{{ invoice.total_label }}</span></div>
                    </div>
                </CardContent>
            </Card>

            <Card class="h-fit">
                <CardContent class="grid gap-3 pt-4 text-sm sm:pt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">{{ $t('invoices.fields.status') }}</span>
                        <InvoiceStatusBadge :status="invoice.status" :label="invoice.status_label" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ $t('invoices.fields.issue_date') }}</span><span>{{ formatDate(invoice.issue_date) }}</span>
                    </div>
                    <div v-if="invoice.due_date" class="flex justify-between">
                        <span class="text-muted-foreground">{{ $t('invoices.fields.due_date') }}</span><span>{{ formatDate(invoice.due_date) }}</span>
                    </div>
                    <div v-if="invoice.customer">
                        <p class="text-muted-foreground">{{ $t('invoices.fields.customer') }}</p>
                        <Link :href="route('customers.show', invoice.customer.id)" class="font-medium text-primary hover:underline">{{ invoice.customer.name }}</Link>
                        <p v-if="invoice.customer.phone">{{ invoice.customer.phone }}</p>
                    </div>
                    <div v-if="invoice.work_order">
                        <p class="text-muted-foreground">{{ $t('invoices.fields.work_order') }}</p>
                        <Link :href="route('work-orders.show', invoice.work_order.id)" class="font-medium text-primary hover:underline">{{ invoice.work_order.number }}</Link>
                        <p v-if="invoice.vehicle">{{ invoice.vehicle.plate_number }} · {{ invoice.vehicle.name }}</p>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <span class="text-muted-foreground">{{ $t('invoices.paid') }}</span><span class="tabular-nums">{{ invoice.amount_paid_label }}</span>
                    </div>
                    <div class="flex justify-between text-base font-semibold">
                        <span>{{ $t('invoices.outstanding') }}</span>
                        <span :class="cn('tabular-nums', invoice.outstanding > 0 && 'text-destructive')">{{ invoice.outstanding_label }}</span>
                    </div>
                    <p v-if="invoice.notes" class="whitespace-pre-line border-t pt-3">{{ invoice.notes }}</p>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="voidOpen"
            :title="$t('invoices.void_title', { number: invoice.number })"
            :description="invoice.work_order ? $t('invoices.void_description_work_order') : $t('invoices.void_description_walk_in')"
            :confirm-label="$t('invoices.void')"
            destructive
            require-reason
            :processing="voiding"
            @confirm="voidInvoice"
        />
    </AppLayout>
</template>
