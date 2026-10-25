<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { onMounted } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { formatDate } from '@/lib/format';
import type { DailySummary } from '@/types/reports';

/**
 * The daily summary on A4 (pilot decision), plain black for any printer, with room to
 * sign when the cash drawer is handed over.
 */
const props = defineProps<{
    summary: DailySummary;
    shop: { name: string; branch: string; logo_url: string | null };
    printedBy: string;
}>();

const print = (): void => window.print();
const printedAt = new Date().toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' });

onMounted(print);
</script>

<template>
    <Head :title="`${$t('daily_summary.title')} ${formatDate(props.summary.date)}`" />

    <div class="min-h-screen bg-muted/40 print:bg-white">
        <div class="mx-auto flex max-w-[210mm] items-center justify-between gap-2 px-4 py-3 print:hidden">
            <Link :href="route('reports.daily', { date: summary.date })" :class="buttonVariants({ variant: 'ghost' })"><ArrowLeft aria-hidden="true" /> {{ $t('common.back') }}</Link>
            <Button @click="print"><Printer aria-hidden="true" /> {{ $t('daily_summary.print') }}</Button>
        </div>

        <article class="mx-auto max-w-[210mm] bg-white p-8 text-[11px] leading-snug text-black shadow print:max-w-none print:p-0 print:shadow-none">
            <header class="flex items-start justify-between gap-4 border-b-2 border-black pb-2">
                <div class="flex items-start gap-3">
                    <img v-if="shop.logo_url" :src="shop.logo_url" alt="" class="max-h-14 max-w-24 object-contain" />
                    <div>
                        <p class="text-sm font-bold">{{ shop.name }}</p>
                        <p>{{ shop.branch }}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold uppercase">{{ $t('daily_summary.title') }}</p>
                    <p class="text-sm">{{ formatDate(summary.date) }}</p>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-6 py-3">
                <section>
                    <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.sales') }}</h2>
                    <table class="w-full">
                        <tbody>
                            <tr><td>{{ $t('daily_summary.invoice_count') }}</td><td class="text-right">{{ summary.sales.count }}</td></tr>
                            <tr><td>{{ $t('daily_summary.sales_without_tax') }}</td><td class="text-right font-bold">{{ summary.sales.sales_label }}</td></tr>
                            <tr><td>{{ $t('daily_summary.discounts') }}</td><td class="text-right">{{ summary.sales.discount_label }}</td></tr>
                            <tr v-if="summary.sales.tax_label"><td>{{ $t('daily_summary.tax') }}</td><td class="text-right">{{ summary.sales.tax_label }}</td></tr>
                            <tr class="border-t border-black"><td>{{ $t('daily_summary.invoice_total') }}</td><td class="text-right">{{ summary.sales.total_label }}</td></tr>
                            <tr v-if="summary.sales.unpaid_count">
                                <td>{{ $t('daily_summary.unpaid', { count: String(summary.sales.unpaid_count) }) }}</td><td class="text-right">{{ summary.sales.unpaid_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                <section>
                    <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.received') }}</h2>
                    <table class="w-full">
                        <tbody>
                            <tr v-if="!summary.received.methods.length"><td>{{ $t('daily_summary.nothing') }}</td></tr>
                            <tr v-for="method in summary.received.methods" :key="method.name">
                                <td>{{ method.name }} ({{ method.count }}×)</td><td class="text-right">{{ method.amount_label }}</td>
                            </tr>
                            <tr class="border-t border-black font-bold"><td>{{ $t('daily_summary.received_total') }}</td><td class="text-right">{{ summary.received.total_label }}</td></tr>
                            <tr><td>{{ $t('daily_summary.card_fees') }}</td><td class="text-right">{{ summary.received.surcharge_label }}</td></tr>
                        </tbody>
                    </table>
                </section>
            </div>

            <section class="py-2">
                <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.cash') }}</h2>
                <table class="w-full">
                    <thead>
                        <tr class="text-left">
                            <th class="font-semibold">{{ $t('daily_summary.account') }}</th>
                            <th class="text-right font-semibold">{{ $t('daily_summary.opening') }}</th>
                            <th class="text-right font-semibold">{{ $t('daily_summary.money_in') }}</th>
                            <th class="text-right font-semibold">{{ $t('daily_summary.money_out') }}</th>
                            <th class="text-right font-semibold">{{ $t('daily_summary.closing') }}</th>
                            <th class="w-28 text-right font-semibold">{{ $t('daily_summary.counted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="account in summary.cash" :key="account.name" class="border-t border-gray-400">
                            <td class="py-1">{{ account.name }}</td>
                            <td class="text-right">{{ account.opening_label }}</td>
                            <td class="text-right">{{ account.in_label }}</td>
                            <td class="text-right">{{ account.out_label }}</td>
                            <td class="text-right font-bold">{{ account.closing_label }}</td>
                            <td class="text-right">{{ account.is_physical ? '……………' : '' }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <div class="grid grid-cols-2 gap-6 py-2">
                <section>
                    <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.expenses') }}</h2>
                    <table class="w-full">
                        <tbody>
                            <tr v-if="!summary.expenses.items.length"><td>{{ $t('daily_summary.nothing') }}</td></tr>
                            <tr v-for="(expense, index) in summary.expenses.items" :key="index">
                                <td>{{ expense.description }} <span class="text-gray-600">({{ expense.category }}, {{ expense.account }})</span></td>
                                <td class="text-right align-top">{{ expense.amount_label }}</td>
                            </tr>
                            <tr class="border-t border-black font-bold"><td>{{ $t('daily_summary.total') }}</td><td class="text-right">{{ summary.expenses.total_label }}</td></tr>
                        </tbody>
                    </table>
                    <p v-if="!summary.expenses.includes_restricted" class="mt-1 text-gray-600">{{ $t('daily_summary.restricted_hidden') }}</p>
                </section>

                <section>
                    <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.supplier_payments') }}</h2>
                    <table class="w-full">
                        <tbody>
                            <tr v-if="!summary.supplier_payments.items.length"><td>{{ $t('daily_summary.nothing') }}</td></tr>
                            <tr v-for="(payment, index) in summary.supplier_payments.items" :key="index">
                                <td>{{ payment.supplier }} <span class="text-gray-600">({{ payment.purchase }}, {{ payment.account }})</span></td>
                                <td class="text-right align-top">{{ payment.amount_label }}</td>
                            </tr>
                            <tr class="border-t border-black font-bold"><td>{{ $t('daily_summary.total') }}</td><td class="text-right">{{ summary.supplier_payments.total_label }}</td></tr>
                        </tbody>
                    </table>

                    <h2 class="mb-1 mt-3 border-b border-black font-bold">{{ $t('daily_summary.transfers') }}</h2>
                    <p v-if="!summary.transfers.length">{{ $t('daily_summary.nothing') }}</p>
                    <p v-for="(transfer, index) in summary.transfers" :key="index" class="flex justify-between">
                        <span>{{ transfer.from }} → {{ transfer.to }}</span><span>{{ transfer.amount_label }}</span>
                    </p>
                </section>
            </div>

            <div class="grid grid-cols-2 gap-6 py-2">
                <section>
                    <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.workshop') }}</h2>
                    <p class="flex justify-between"><span>{{ $t('daily_summary.checked_in') }}</span><span>{{ summary.workshop.checked_in }}</span></p>
                    <p class="flex justify-between"><span>{{ $t('daily_summary.completed') }}</span><span>{{ summary.workshop.completed }}</span></p>
                </section>
                <section v-if="summary.cancellations.length">
                    <h2 class="mb-1 border-b border-black font-bold">{{ $t('daily_summary.cancellations') }}</h2>
                    <p v-for="(item, index) in summary.cancellations" :key="index" class="flex justify-between gap-2">
                        <span>{{ $t(`daily_summary.cancelled_${item.kind}`) }} {{ item.number }}<template v-if="item.reason">: {{ item.reason }}</template></span>
                        <span>{{ item.amount_label }}</span>
                    </p>
                </section>
            </div>

            <footer class="mt-6 grid grid-cols-2 gap-10 text-center">
                <div>
                    <p>{{ $t('daily_summary.sign_handed_over') }}</p>
                    <div class="mt-14 border-t border-black pt-1">&nbsp;</div>
                </div>
                <div>
                    <p>{{ $t('daily_summary.sign_checked') }}</p>
                    <div class="mt-14 border-t border-black pt-1">&nbsp;</div>
                </div>
            </footer>
            <p class="mt-4 text-[9px] text-gray-600">{{ $t('daily_summary.printed', { name: printedBy, at: printedAt }) }}</p>
        </article>
    </div>
</template>

<style>
@media print {
    @page {
        size: A4 portrait;
        margin: 12mm;
    }
}
</style>
