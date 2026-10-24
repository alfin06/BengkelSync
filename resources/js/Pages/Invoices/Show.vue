<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Ban, Banknote, History, Pencil, Printer } from 'lucide-vue-next';
import { ref } from 'vue';
import InvoiceStatusBadge from '@/Components/Invoices/InvoiceStatusBadge.vue';
import CheckoutDialog from '@/Components/Payments/CheckoutDialog.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Invoice, PaymentMethodOption, Receipt, ReceiptLine } from '@/types/invoices';

const props = defineProps<{
    invoice: Invoice;
    receipts: Receipt[];
    methods: PaymentMethodOption[];
    today: string;
    suggestedDueDate: string;
    can: { void: boolean; update: boolean; pay: boolean; voidPayment: boolean };
}>();

const payOpen = ref(false);

// Cancelling one line or a whole kwitansi (Finance, with a reason). Nothing is deleted.
const lineToVoid = ref<ReceiptLine | null>(null);
const receiptToVoid = ref<Receipt | null>(null);
const paymentVoidOpen = ref(false);
const paymentVoiding = ref(false);

function askVoidLine(line: ReceiptLine): void {
    lineToVoid.value = line;
    receiptToVoid.value = null;
    paymentVoidOpen.value = true;
}

function askVoidReceipt(receipt: Receipt): void {
    receiptToVoid.value = receipt;
    lineToVoid.value = null;
    paymentVoidOpen.value = true;
}

function voidPayment(reason: string | null): void {
    const url = lineToVoid.value ? route('payments.void', lineToVoid.value.id) : route('receipts.void', receiptToVoid.value?.id ?? 0);

    router.post(url, { reason }, {
        preserveScroll: true,
        onStart: () => (paymentVoiding.value = true),
        onFinish: () => (paymentVoiding.value = false),
        onSuccess: () => (paymentVoidOpen.value = false),
    });
}

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
                <Button v-if="can.pay" @click="payOpen = true"><Banknote aria-hidden="true" /> {{ $t('payments.pay') }}</Button>
                <Link v-if="can.update" :href="route('invoices.edit', invoice.id)" :class="buttonVariants({ variant: 'outline' })">
                    <Pencil aria-hidden="true" /> {{ $t('common.edit') }}
                </Link>
                <Link v-if="invoice.revision_no > 0" :href="route('invoices.revisions', invoice.id)" :class="buttonVariants({ variant: 'ghost' })">
                    <History aria-hidden="true" /> {{ $t('invoices.revision', { no: String(invoice.revision_no) }) }}
                </Link>
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

            <Card class="lg:col-span-2">
                <CardHeader><CardTitle>{{ $t('payments.history') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-3">
                    <p v-if="receipts.length === 0" class="text-sm text-muted-foreground">{{ $t('payments.none') }}</p>
                    <div v-for="receipt in receipts" :key="receipt.id" class="rounded-md border text-sm">
                        <div class="flex flex-wrap items-center gap-2 border-b px-3 py-2">
                            <span class="flex-1">
                                <span class="font-medium">{{ formatDate(receipt.paid_on) }} · {{ receipt.number }}</span>
                                <span v-if="receipt.received_by" class="block text-xs text-muted-foreground">{{ receipt.received_by }}</span>
                            </span>
                            <span :class="cn('font-semibold tabular-nums', receipt.fully_voided && 'text-muted-foreground line-through')">{{ receipt.amount_label }}</span>
                            <a :href="route('receipts.print', receipt.id)" target="_blank" rel="noopener" :class="buttonVariants({ variant: 'ghost', size: 'sm' })">
                                <Printer aria-hidden="true" /> {{ $t('payments.print') }}
                            </a>
                            <Button v-if="can.voidPayment && !receipt.fully_voided" variant="ghost" size="sm" class="text-destructive" @click="askVoidReceipt(receipt)">
                                {{ $t('payments.void_receipt') }}
                            </Button>
                        </div>
                        <ul class="divide-y">
                            <li v-for="line in receipt.lines" :key="line.id" class="flex items-start gap-3 px-3 py-2">
                                <span :class="cn('min-w-0 flex-1', line.voided && 'text-muted-foreground')">
                                    <span class="block">{{ line.method }}<template v-if="line.reference"> · {{ line.reference }}</template></span>
                                    <span v-if="line.surcharge_label" class="block text-xs text-muted-foreground">{{ $t('payments.surcharge') }} {{ line.surcharge_label }}</span>
                                    <span v-if="line.voided" class="block text-xs text-destructive">
                                        {{ $t('payments.voided_notice', { reason: line.void_reason ?? '', by: line.voided_by ?? '', at: line.voided_at_label ?? '' }) }}
                                    </span>
                                </span>
                                <span class="text-right">
                                    <span :class="cn('block tabular-nums', line.voided && 'text-muted-foreground line-through')">{{ line.amount_label }}</span>
                                    <button
                                        v-if="can.voidPayment && !line.voided"
                                        type="button"
                                        class="mt-1 text-xs font-medium text-destructive underline-offset-4 hover:underline"
                                        @click="askVoidLine(line)"
                                    >
                                        {{ $t('payments.void_line') }}
                                    </button>
                                </span>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>

        <CheckoutDialog v-if="can.pay" v-model:open="payOpen" :invoice="invoice" :methods="methods" :today="today" :suggested-due-date="suggestedDueDate" />

        <ConfirmDialog
            v-model:open="paymentVoidOpen"
            :title="lineToVoid ? $t('payments.void_line_title', { method: lineToVoid.method, amount: lineToVoid.amount_label }) : $t('payments.void_receipt_title', { number: receiptToVoid?.number ?? '' })"
            :description="$t('payments.void_description')"
            :confirm-label="lineToVoid ? $t('payments.void_line') : $t('payments.void_receipt')"
            destructive
            require-reason
            :processing="paymentVoiding"
            @confirm="voidPayment"
        />

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
