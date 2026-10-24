<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { formatDate } from '@/lib/format';

interface ReceiptPrint {
    number: string;
    paid_on: string;
    invoice_id: number;
    invoice_number: string;
    customer: string | null;
    customer_phone: string | null;
    vehicle: string | null;
    amount_label: string;
    /** Indonesian words, e.g. "dua ratus ribu rupiah" */
    amount_words: string;
    invoice_total_label: string;
    outstanding_label: string;
    due_date: string | null;
    received_by: string | null;
    note: string | null;
    lines: { id: number; method: string; amount_label: string; surcharge_label: string | null; reference: string | null; change_label: string | null; voided: boolean }[];
}

/**
 * The kwitansi on A5 like the invoice (pilot decision), plain black for 2-ply paper.
 * Cancelled lines stay on it, marked as cancelled.
 */
defineProps<{
    receipt: ReceiptPrint;
    branch: { name: string; address: string | null; phone: string | null; logo_url: string | null };
}>();

const page = usePage();
const print = (): void => window.print();

onMounted(print);
</script>

<template>
    <Head :title="receipt.number" />

    <div class="min-h-screen bg-muted/40 print:bg-white">
        <div class="mx-auto flex max-w-[148mm] items-center justify-between gap-2 px-4 py-3 print:hidden">
            <Link :href="route('invoices.show', receipt.invoice_id)" :class="buttonVariants({ variant: 'ghost' })"><ArrowLeft aria-hidden="true" /> {{ $t('common.back') }}</Link>
            <Button @click="print"><Printer aria-hidden="true" /> {{ $t('payments.print') }}</Button>
        </div>

        <article class="mx-auto max-w-[148mm] bg-white p-6 text-[11px] leading-snug text-black shadow print:max-w-none print:p-0 print:shadow-none">
            <header class="flex items-start justify-between gap-4 border-b-2 border-black pb-2">
                <div class="flex items-start gap-3">
                    <img v-if="branch.logo_url" :src="branch.logo_url" alt="" class="max-h-14 max-w-24 object-contain" />
                    <div>
                        <p class="text-sm font-bold">{{ page.props.tenant?.name }}</p>
                        <p>{{ branch.name }}</p>
                        <p v-if="branch.address" class="whitespace-pre-line">{{ branch.address }}</p>
                        <p v-if="branch.phone">{{ $t('invoices.print_phone', { phone: branch.phone }) }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold">{{ $t('payments.receipt_title') }}</p>
                    <p class="font-mono">{{ receipt.number }}</p>
                    <p>{{ formatDate(receipt.paid_on) }}</p>
                </div>
            </header>

            <dl class="grid grid-cols-[9rem_1fr] gap-x-2 gap-y-1 border-b border-black py-2">
                <dt>{{ $t('payments.received_from') }}</dt>
                <dd class="font-semibold">{{ receipt.customer ?? $t('invoices.walk_in_customer') }}<template v-if="receipt.customer_phone"> · {{ receipt.customer_phone }}</template></dd>
                <dt>{{ $t('payments.amount_in_words') }}</dt>
                <dd class="font-semibold italic">{{ receipt.amount_words }}</dd>
                <dt>{{ $t('payments.for_payment') }}</dt>
                <dd>{{ $t('payments.for_invoice', { number: receipt.invoice_number }) }}<template v-if="receipt.vehicle"> · {{ receipt.vehicle }}</template></dd>
            </dl>

            <table class="mt-2 w-full border-collapse">
                <tbody>
                    <tr v-for="line in receipt.lines" :key="line.id" class="border-b border-dashed border-black align-top">
                        <td class="py-1 pr-2">
                            <span :class="line.voided && 'line-through'">{{ line.method }}<template v-if="line.reference"> · {{ line.reference }}</template></span>
                            <span v-if="line.voided" class="ml-1 font-bold">{{ $t('payments.voided_mark') }}</span>
                            <span v-if="line.surcharge_label" class="block">{{ $t('payments.surcharge') }} {{ line.surcharge_label }}</span>
                            <span v-if="line.change_label" class="block">{{ $t('payments.change', { amount: line.change_label }) }}</span>
                        </td>
                        <td class="whitespace-nowrap py-1 text-right" :class="line.voided && 'line-through'">{{ line.amount_label }}</td>
                    </tr>
                </tbody>
            </table>

            <section class="ml-auto mt-2 grid w-1/2 gap-0.5">
                <div class="flex justify-between border-b-2 border-black pb-0.5 text-sm font-bold"><span>{{ $t('payments.received') }}</span><span>{{ receipt.amount_label }}</span></div>
                <div class="flex justify-between"><span>{{ $t('payments.invoice_total') }}</span><span>{{ receipt.invoice_total_label }}</span></div>
                <div class="flex justify-between font-semibold"><span>{{ $t('payments.still_owed') }}</span><span>{{ receipt.outstanding_label }}</span></div>
                <div v-if="receipt.due_date" class="flex justify-between"><span>{{ $t('payments.due_date') }}</span><span>{{ formatDate(receipt.due_date) }}</span></div>
            </section>

            <p v-if="receipt.note" class="mt-2 whitespace-pre-line">{{ receipt.note }}</p>

            <footer class="mt-6 grid grid-cols-2 gap-6 text-center">
                <div>
                    <div class="h-12 border-b border-black" />
                    <p class="mt-1">{{ $t('invoices.print_sign_customer') }}</p>
                </div>
                <div>
                    <div class="h-12 border-b border-black" />
                    <p class="mt-1">{{ receipt.received_by ?? $t('payments.cashier') }}</p>
                </div>
            </footer>
        </article>
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
