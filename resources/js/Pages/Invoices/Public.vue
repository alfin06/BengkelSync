<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';
import InvoiceDocument, { type InvoiceBranch } from '@/Components/Invoices/InvoiceDocument.vue';
import { Button } from '@/Components/ui/button';
import { formatDate } from '@/lib/format';
import type { Invoice } from '@/types/invoices';

/**
 * The invoice a customer opens from the WhatsApp link (no login). Same layout as the
 * printed invoice, plus what is paid and what is still owed. "Simpan / cetak" opens the
 * browser's print dialog, where "Save as PDF" keeps a copy.
 */
defineProps<{
    invoice: Invoice;
    branch: InvoiceBranch;
    payments: { id: number; paid_on: string; method: string; amount_label: string }[];
}>();

const print = (): void => window.print();
</script>

<template>
    <Head :title="`${invoice.number} · ${branch.shop_name}`">
        <meta name="robots" content="noindex, nofollow" />
    </Head>

    <div class="min-h-screen bg-muted/40 pb-10 print:bg-white print:pb-0">
        <div class="mx-auto flex max-w-[148mm] items-center justify-between gap-3 px-4 py-3 print:hidden">
            <div class="min-w-0">
                <p class="truncate font-semibold">{{ branch.shop_name }}</p>
                <p class="text-xs text-muted-foreground">{{ $t('public_invoice.save_hint') }}</p>
            </div>
            <Button class="shrink-0" @click="print"><Download aria-hidden="true" /> {{ $t('public_invoice.save') }}</Button>
        </div>

        <p v-if="invoice.status === 'void'" class="mx-auto mb-3 max-w-[148mm] rounded-md border border-red-200 bg-red-50 p-3 text-sm font-medium text-red-800 print:hidden">
            {{ $t('public_invoice.void') }}
        </p>

        <InvoiceDocument :invoice="invoice" :branch="branch" />

        <section v-if="invoice.status !== 'void'" class="mx-auto mt-4 max-w-[148mm] rounded-md border bg-white p-4 text-sm print:mt-2 print:border-black print:p-2 print:text-[11px]">
            <p v-if="invoice.outstanding <= 0" class="font-semibold text-green-700 print:text-black">{{ $t('public_invoice.paid_in_full') }}</p>
            <template v-else>
                <div class="flex justify-between"><span>{{ $t('public_invoice.paid') }}</span><span class="tabular-nums">{{ invoice.amount_paid_label }}</span></div>
                <div class="flex justify-between font-semibold"><span>{{ $t('public_invoice.outstanding') }}</span><span class="tabular-nums">{{ invoice.outstanding_label }}</span></div>
                <p v-if="invoice.due_date" class="text-muted-foreground print:text-black">{{ $t('public_invoice.due', { date: formatDate(invoice.due_date) }) }}</p>
            </template>
            <div v-if="payments.length" class="mt-3 border-t pt-2">
                <p class="mb-1 font-medium">{{ $t('public_invoice.payments') }}</p>
                <p v-for="payment in payments" :key="payment.id" class="flex justify-between">
                    <span>{{ formatDate(payment.paid_on) }} · {{ payment.method }}</span><span class="tabular-nums">{{ payment.amount_label }}</span>
                </p>
            </div>
        </section>
    </div>
</template>

<style>
@media print {
    @page {
        size: A5 portrait;
        margin: 8mm;
    }
}
</style>
