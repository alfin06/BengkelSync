<script setup lang="ts">
import { formatDate, formatNumber } from '@/lib/format';
import type { Invoice } from '@/types/invoices';

/**
 * The printed invoice (A5, plain black text and lines, so it prints well on 2-ply paper),
 * used by the print page and by the page customers open from the WhatsApp link.
 */
defineProps<{ invoice: Invoice; branch: InvoiceBranch }>();
</script>

<script lang="ts">
export interface InvoiceBranch {
    shop_name: string;
    name: string;
    address: string | null;
    phone: string | null;
    npwp: string | null;
    footer: string | null;
    logo_url: string | null;
}
</script>

<template>
    <article class="invoice mx-auto max-w-[148mm] bg-white p-6 text-[11px] leading-snug text-black shadow print:max-w-none print:p-0 print:shadow-none">
        <header class="flex items-start justify-between gap-4 border-b-2 border-black pb-2">
            <div class="flex items-start gap-3">
                <img v-if="branch.logo_url" :src="branch.logo_url" alt="" class="max-h-14 max-w-24 object-contain" />
                <div>
                    <p class="text-sm font-bold">{{ branch.shop_name }}</p>
                    <p>{{ branch.name }}</p>
                    <p v-if="branch.address" class="whitespace-pre-line">{{ branch.address }}</p>
                    <p v-if="branch.phone">{{ $t('invoices.print_phone', { phone: branch.phone }) }}</p>
                    <p v-if="branch.npwp">NPWP {{ branch.npwp }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-sm font-bold">{{ $t('invoices.print_title') }}</p>
                <p class="font-mono">{{ invoice.number }}</p>
                <p>{{ formatDate(invoice.issue_date) }}</p>
                <p v-if="invoice.revision_no > 0">{{ $t('invoices.revision', { no: String(invoice.revision_no) }) }}</p>
                <p v-if="invoice.status === 'void'" class="font-bold">{{ $t('invoices.status.void') }}</p>
            </div>
        </header>

        <section class="grid grid-cols-2 gap-x-4 border-b border-black py-2">
            <div>
                <p class="font-semibold">{{ $t('invoices.print_to') }}</p>
                <p>{{ invoice.customer?.name ?? $t('invoices.walk_in_customer') }}</p>
                <p v-if="invoice.customer?.phone">{{ invoice.customer.phone }}</p>
                <p v-if="invoice.customer?.address" class="whitespace-pre-line">{{ invoice.customer.address }}</p>
            </div>
            <div v-if="invoice.vehicle">
                <p class="font-semibold">{{ $t('invoices.fields.vehicle') }}</p>
                <p>{{ invoice.vehicle.plate_number }} · {{ invoice.vehicle.name }}</p>
                <p v-if="invoice.work_order?.odometer_in">{{ formatNumber(invoice.work_order.odometer_in) }} km</p>
                <p v-if="invoice.work_order">{{ invoice.work_order.number }}</p>
            </div>
        </section>

        <table class="mt-2 w-full border-collapse">
            <thead>
                <tr class="border-b border-black text-left">
                    <th class="py-1 pr-2 font-semibold">{{ $t('invoices.fields.description') }}</th>
                    <th class="py-1 pr-2 text-right font-semibold">{{ $t('invoices.fields.qty') }}</th>
                    <th class="py-1 pr-2 text-right font-semibold">{{ $t('invoices.fields.unit_price') }}</th>
                    <th class="py-1 text-right font-semibold">{{ $t('invoices.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="line in invoice.lines" :key="line.id" class="border-b border-dashed border-black align-top">
                    <td class="py-1 pr-2">
                        {{ line.description }}
                        <span v-if="line.discount_amount > 0" class="block">{{ $t('invoices.fields.discount') }} −{{ line.discount_label }}</span>
                    </td>
                    <td class="whitespace-nowrap py-1 pr-2 text-right">{{ line.qty_label }} {{ line.unit_label ?? '' }}</td>
                    <td class="whitespace-nowrap py-1 pr-2 text-right">{{ line.unit_price_label }}</td>
                    <td class="whitespace-nowrap py-1 text-right">{{ line.line_total_label }}</td>
                </tr>
            </tbody>
        </table>

        <section class="ml-auto mt-2 grid w-1/2 gap-0.5">
            <div class="flex justify-between"><span>{{ $t('invoices.subtotal') }}</span><span>{{ invoice.subtotal_label }}</span></div>
            <div v-if="invoice.discount_amount > 0" class="flex justify-between"><span>{{ $t('invoices.fields.invoice_discount') }}</span><span>−{{ invoice.discount_label }}</span></div>
            <div v-if="invoice.tax_enabled" class="flex justify-between">
                <span>{{ invoice.tax_inclusive ? $t('invoices.tax_included', { name: invoice.tax_name, rate: invoice.tax_rate_label }) : `${invoice.tax_name} ${invoice.tax_rate_label}` }}</span>
                <span>{{ invoice.tax_label }}</span>
            </div>
            <div class="flex justify-between border-t-2 border-black pt-0.5 text-sm font-bold"><span>{{ $t('invoices.total') }}</span><span>{{ invoice.total_label }}</span></div>
        </section>

        <p v-if="invoice.notes" class="mt-2 whitespace-pre-line">{{ invoice.notes }}</p>

        <footer class="mt-6 grid grid-cols-2 gap-6 text-center">
            <div>
                <div class="h-12 border-b border-black" />
                <p class="mt-1">{{ $t('invoices.print_sign_customer') }}</p>
            </div>
            <div>
                <div class="h-12 border-b border-black" />
                <p class="mt-1">{{ $t('invoices.print_sign_shop') }}</p>
            </div>
        </footer>

        <p v-if="branch.footer" class="mt-4 whitespace-pre-line text-center">{{ branch.footer }}</p>
    </article>
</template>
