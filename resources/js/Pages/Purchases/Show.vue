<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Ban, Banknote, Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PayDialog from '@/Components/Purchases/PayDialog.vue';
import PurchaseStatusBadge from '@/Components/Purchases/PurchaseStatusBadge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PayAccount, Purchase, SupplierPaymentRow } from '@/types/purchases';

const props = defineProps<{
    purchase: Purchase;
    payAccounts: PayAccount[];
    today: string;
    can: { update: boolean; void: boolean; pay: boolean };
}>();

const payOpen = ref(false);

// Voiding the purchase (only while nothing is paid) or one payment, both with a reason.
const voidOpen = ref(false);
const voiding = ref(false);

function voidPurchase(reason: string | null): void {
    router.post(route('purchases.void', props.purchase.id), { reason }, {
        preserveScroll: true,
        onStart: () => (voiding.value = true),
        onFinish: () => (voiding.value = false),
        onSuccess: () => (voidOpen.value = false),
    });
}

const paymentToVoid = ref<SupplierPaymentRow | null>(null);
const paymentVoidOpen = ref(false);

function askVoidPayment(payment: SupplierPaymentRow): void {
    paymentToVoid.value = payment;
    paymentVoidOpen.value = true;
}

function voidPayment(reason: string | null): void {
    if (!paymentToVoid.value) {
        return;
    }

    router.post(route('supplier-payments.void', paymentToVoid.value.id), { reason }, {
        preserveScroll: true,
        onStart: () => (voiding.value = true),
        onFinish: () => (voiding.value = false),
        onSuccess: () => (paymentVoidOpen.value = false),
    });
}

const isVoid = computed(() => props.purchase.status === 'void');
</script>

<template>
    <AppLayout>
        <PageHeader :title="purchase.number" :description="purchase.supplier.name">
            <template v-if="can.update || can.void" #actions>
                <Link v-if="can.update" :href="route('purchases.edit', purchase.id)" :class="buttonVariants({ variant: 'outline' })">
                    <Pencil aria-hidden="true" /> {{ $t('common.edit') }}
                </Link>
                <Button v-if="can.void" variant="outline" class="text-destructive" @click="voidOpen = true">
                    <Ban aria-hidden="true" /> {{ $t('purchases.void') }}
                </Button>
            </template>
        </PageHeader>

        <p v-if="isVoid" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">
            {{ $t('purchases.void_notice', { reason: purchase.void_reason ?? '' }) }}
        </p>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="grid gap-6 lg:col-span-2">
                <Card>
                    <CardHeader>
                        <CardTitle>{{ $t('purchases.goods') }}</CardTitle>
                    </CardHeader>
                    <CardContent class="p-0 sm:p-0">
                        <ul class="divide-y border-t">
                            <li v-for="line in purchase.lines" :key="line.id" class="flex items-start gap-3 px-4 py-3 text-sm sm:px-6">
                                <span class="min-w-0 flex-1">
                                    <Link :href="route('parts.show', line.part_id)" class="font-medium text-primary hover:underline">{{ line.part_name }}</Link>
                                    <span class="block text-muted-foreground tabular-nums">{{ line.qty_label }} {{ line.unit_label }} × {{ line.unit_cost_label }}</span>
                                </span>
                                <span class="font-medium tabular-nums">{{ line.line_total_label }}</span>
                            </li>
                        </ul>
                        <div class="flex justify-between border-t px-4 py-3 font-semibold sm:px-6">
                            <span>{{ $t('purchases.total') }}</span>
                            <span class="tabular-nums">{{ purchase.total_label }}</span>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('purchases.payments') }}</CardTitle>
                        <Button v-if="can.pay" size="sm" @click="payOpen = true"><Banknote aria-hidden="true" /> {{ $t('purchases.pay') }}</Button>
                    </CardHeader>
                    <CardContent>
                        <p v-if="!purchase.payments?.length" class="text-sm text-muted-foreground">{{ $t('purchases.no_payments') }}</p>
                        <ul v-else class="-my-3 divide-y">
                            <li v-for="payment in purchase.payments" :key="payment.id" class="flex items-start gap-3 py-3 text-sm">
                                <span :class="cn('min-w-0 flex-1', payment.voided && 'text-muted-foreground')">
                                    <span class="block font-medium">{{ formatDate(payment.paid_on) }} · {{ payment.account_name }}</span>
                                    <span v-if="payment.reference || payment.note" class="block">{{ [payment.reference, payment.note].filter(Boolean).join(' · ') }}</span>
                                    <span v-if="payment.payer" class="block text-xs text-muted-foreground">{{ payment.payer }}</span>
                                    <span v-if="payment.voided" class="block text-xs text-destructive">{{ $t('purchases.payment_void_notice', { reason: payment.void_reason ?? '' }) }}</span>
                                </span>
                                <span class="text-right">
                                    <span :class="cn('block font-semibold tabular-nums', payment.voided && 'text-muted-foreground line-through')">{{ payment.amount_label }}</span>
                                    <button v-if="payment.can_void" type="button" class="mt-1 text-xs font-medium text-destructive underline-offset-4 hover:underline" @click="askVoidPayment(payment)">
                                        {{ $t('purchases.void_payment') }}
                                    </button>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <Card class="h-fit">
                <CardContent class="grid gap-3 pt-4 text-sm sm:pt-6">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground">{{ $t('purchases.fields.status') }}</span>
                        <PurchaseStatusBadge :purchase="purchase" />
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ $t('purchases.fields.purchase_date') }}</span>
                        <span>{{ formatDate(purchase.purchase_date) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">{{ $t('purchases.fields.due_date') }}</span>
                        <span :class="purchase.is_overdue && 'font-medium text-destructive'">{{ purchase.due_date ? formatDate(purchase.due_date) : '–' }}</span>
                    </div>
                    <div v-if="purchase.supplier_ref" class="flex justify-between">
                        <span class="text-muted-foreground">{{ $t('purchases.fields.supplier_ref') }}</span>
                        <span>{{ purchase.supplier_ref }}</span>
                    </div>
                    <div class="flex justify-between border-t pt-3">
                        <span class="text-muted-foreground">{{ $t('purchases.paid') }}</span>
                        <span class="tabular-nums">{{ purchase.amount_paid_label }}</span>
                    </div>
                    <div class="flex justify-between text-base font-semibold">
                        <span>{{ $t('purchases.outstanding') }}</span>
                        <span :class="cn('tabular-nums', purchase.outstanding > 0 && !isVoid && 'text-destructive')">{{ purchase.outstanding_label }}</span>
                    </div>
                    <p v-if="purchase.notes" class="whitespace-pre-line border-t pt-3">{{ purchase.notes }}</p>
                </CardContent>
            </Card>
        </div>

        <PayDialog v-if="can.pay" v-model:open="payOpen" :purchase="purchase" :accounts="payAccounts" :today="today" />

        <ConfirmDialog
            v-model:open="voidOpen"
            :title="$t('purchases.void_title', { number: purchase.number })"
            :description="$t('purchases.void_description')"
            :confirm-label="$t('purchases.void')"
            destructive
            require-reason
            :processing="voiding"
            @confirm="voidPurchase"
        />

        <ConfirmDialog
            v-model:open="paymentVoidOpen"
            :title="$t('purchases.void_payment_title', { amount: paymentToVoid?.amount_label ?? '' })"
            :description="$t('purchases.void_payment_description', { account: paymentToVoid?.account_name ?? '' })"
            :confirm-label="$t('purchases.void_payment')"
            destructive
            require-reason
            :processing="voiding"
            @confirm="voidPayment"
        />
    </AppLayout>
</template>
