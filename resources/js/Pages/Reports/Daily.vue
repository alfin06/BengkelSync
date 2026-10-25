<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ChevronLeft, ChevronRight, Printer } from 'lucide-vue-next';
import { computed } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import type { DailySummary } from '@/types/reports';

/**
 * "Rekap harian": one day of the branch at a glance, e.g. to check the cash drawer in the
 * evening. Every figure comes from the server.
 */
const props = defineProps<{ summary: DailySummary; today: string }>();

const isToday = computed(() => props.summary.date >= props.today);

function shift(days: number): string {
    const date = new Date(`${props.summary.date}T00:00:00`);
    date.setDate(date.getDate() + days);

    return [date.getFullYear(), String(date.getMonth() + 1).padStart(2, '0'), String(date.getDate()).padStart(2, '0')].join('-');
}

function go(date: string): void {
    if (date) {
        router.get(route('reports.daily'), date === props.today ? {} : { date }, { preserveScroll: true });
    }
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('daily_summary.title')" :description="$t('daily_summary.description')">
            <template #actions>
                <a :href="route('reports.daily.print', { date: summary.date })" target="_blank" rel="noopener" :class="buttonVariants({ variant: 'outline' })">
                    <Printer aria-hidden="true" /> {{ $t('daily_summary.print') }}
                </a>
            </template>
        </PageHeader>

        <div class="mb-6 flex items-center gap-2">
            <Button variant="outline" size="icon" :aria-label="$t('daily_summary.previous_day')" @click="go(shift(-1))"><ChevronLeft aria-hidden="true" /></Button>
            <Input type="date" class="w-44" :model-value="summary.date" :max="today" :aria-label="$t('daily_summary.date')" @change="(event: Event) => go((event.target as HTMLInputElement).value)" />
            <Button variant="outline" size="icon" :disabled="isToday" :aria-label="$t('daily_summary.next_day')" @click="go(shift(1))"><ChevronRight aria-hidden="true" /></Button>
            <span class="text-sm text-muted-foreground">{{ formatDate(summary.date) }}</span>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader><CardTitle>{{ $t('daily_summary.sales') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <div class="flex justify-between"><span>{{ $t('daily_summary.invoice_count') }}</span><span class="tabular-nums">{{ summary.sales.count }}</span></div>
                    <div class="flex justify-between"><span>{{ $t('daily_summary.sales_without_tax') }}</span><span class="font-semibold tabular-nums">{{ summary.sales.sales_label }}</span></div>
                    <div class="flex justify-between text-muted-foreground"><span>{{ $t('daily_summary.discounts') }}</span><span class="tabular-nums">{{ summary.sales.discount_label }}</span></div>
                    <div v-if="summary.sales.tax_label" class="flex justify-between text-muted-foreground"><span>{{ $t('daily_summary.tax') }}</span><span class="tabular-nums">{{ summary.sales.tax_label }}</span></div>
                    <div class="flex justify-between border-t pt-2"><span>{{ $t('daily_summary.invoice_total') }}</span><span class="tabular-nums">{{ summary.sales.total_label }}</span></div>
                    <div v-if="summary.sales.unpaid_count" class="flex justify-between text-orange-700">
                        <span>{{ $t('daily_summary.unpaid', { count: String(summary.sales.unpaid_count) }) }}</span><span class="tabular-nums">{{ summary.sales.unpaid_label }}</span>
                    </div>
                    <ul v-if="summary.sales.invoices.length" class="mt-2 divide-y rounded-md border">
                        <li v-for="invoice in summary.sales.invoices" :key="invoice.id" class="flex items-center gap-2 px-3 py-2">
                            <Link :href="route('invoices.show', invoice.id)" class="min-w-0 flex-1 truncate font-medium text-primary">{{ invoice.number }}</Link>
                            <span class="hidden truncate text-muted-foreground sm:inline">{{ invoice.customer ?? $t('invoices.walk_in_customer') }}</span>
                            <Badge :tone="invoice.status === 'paid' ? 'success' : 'warning'">{{ invoice.status_label }}</Badge>
                            <span class="tabular-nums">{{ invoice.total_label }}</span>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('daily_summary.received') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <p v-if="!summary.received.methods.length" class="text-muted-foreground">{{ $t('daily_summary.nothing') }}</p>
                    <div v-for="method in summary.received.methods" :key="method.name" class="flex justify-between gap-2">
                        <span>{{ method.name }} <span class="text-muted-foreground">· {{ $t('daily_summary.times', { count: String(method.count) }) }}</span></span>
                        <span class="tabular-nums">{{ method.amount_label }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ $t('daily_summary.received_total') }}</span><span class="tabular-nums">{{ summary.received.total_label }}</span></div>
                    <div class="flex justify-between text-muted-foreground"><span>{{ $t('daily_summary.card_fees') }}</span><span class="tabular-nums">{{ summary.received.surcharge_label }}</span></div>
                </CardContent>
            </Card>

            <Card class="lg:col-span-2">
                <CardHeader>
                    <CardTitle>{{ $t('daily_summary.cash') }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ $t('daily_summary.cash_hint') }}</p>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-sm">
                        <thead class="text-left text-muted-foreground">
                            <tr>
                                <th class="py-2 font-medium">{{ $t('daily_summary.account') }}</th>
                                <th class="py-2 text-right font-medium">{{ $t('daily_summary.opening') }}</th>
                                <th class="py-2 text-right font-medium">{{ $t('daily_summary.money_in') }}</th>
                                <th class="py-2 text-right font-medium">{{ $t('daily_summary.money_out') }}</th>
                                <th class="py-2 text-right font-medium">{{ $t('daily_summary.closing') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="account in summary.cash" :key="account.name">
                                <td class="py-2 font-medium">{{ account.name }}</td>
                                <td class="py-2 text-right tabular-nums">{{ account.opening_label }}</td>
                                <td class="py-2 text-right tabular-nums text-green-700">{{ account.in_label }}</td>
                                <td class="py-2 text-right tabular-nums text-red-700">{{ account.out_label }}</td>
                                <td class="py-2 text-right font-semibold tabular-nums">{{ account.closing_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>{{ $t('daily_summary.expenses') }}</CardTitle>
                    <p v-if="!summary.expenses.includes_restricted" class="text-sm text-muted-foreground">{{ $t('daily_summary.restricted_hidden') }}</p>
                </CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <p v-if="!summary.expenses.items.length" class="text-muted-foreground">{{ $t('daily_summary.nothing') }}</p>
                    <div v-for="(expense, index) in summary.expenses.items" :key="index" class="flex justify-between gap-2">
                        <span class="min-w-0">{{ expense.description }} <span class="text-muted-foreground">· {{ expense.category }} · {{ expense.account }}</span></span>
                        <span class="tabular-nums">{{ expense.amount_label }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ $t('daily_summary.total') }}</span><span class="tabular-nums">{{ summary.expenses.total_label }}</span></div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('daily_summary.supplier_payments') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <p v-if="!summary.supplier_payments.items.length" class="text-muted-foreground">{{ $t('daily_summary.nothing') }}</p>
                    <div v-for="(payment, index) in summary.supplier_payments.items" :key="index" class="flex justify-between gap-2">
                        <span class="min-w-0">{{ payment.supplier }} <span class="text-muted-foreground">· {{ payment.purchase }} · {{ payment.account }}</span></span>
                        <span class="tabular-nums">{{ payment.amount_label }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-semibold"><span>{{ $t('daily_summary.total') }}</span><span class="tabular-nums">{{ summary.supplier_payments.total_label }}</span></div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('daily_summary.transfers') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <p v-if="!summary.transfers.length" class="text-muted-foreground">{{ $t('daily_summary.nothing') }}</p>
                    <div v-for="(transfer, index) in summary.transfers" :key="index" class="flex justify-between gap-2">
                        <span>{{ transfer.from }} → {{ transfer.to }}</span>
                        <span class="tabular-nums">{{ transfer.amount_label }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('daily_summary.workshop') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <div class="flex justify-between"><span>{{ $t('daily_summary.checked_in') }}</span><span class="tabular-nums">{{ summary.workshop.checked_in }}</span></div>
                    <div class="flex justify-between"><span>{{ $t('daily_summary.completed') }}</span><span class="tabular-nums">{{ summary.workshop.completed }}</span></div>
                </CardContent>
            </Card>

            <Card v-if="summary.cancellations.length" class="border-orange-300 lg:col-span-2">
                <CardHeader><CardTitle>{{ $t('daily_summary.cancellations') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-2 text-sm">
                    <div v-for="(item, index) in summary.cancellations" :key="index" class="flex justify-between gap-2">
                        <span class="min-w-0">
                            <span class="font-medium">{{ $t(`daily_summary.cancelled_${item.kind}`) }} {{ item.number }}</span>
                            <span v-if="item.reason" class="text-muted-foreground"> · {{ item.reason }}</span>
                        </span>
                        <span class="tabular-nums">{{ item.amount_label }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
