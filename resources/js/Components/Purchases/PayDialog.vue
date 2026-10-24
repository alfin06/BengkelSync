<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import Select from '@/Components/ui/Select.vue';
import type { PayAccount, Purchase } from '@/types/purchases';

/**
 * Pay (part of) a supplier bill. The money leaves the chosen cash account.
 */
const props = defineProps<{ purchase: Purchase; accounts: PayAccount[]; today: string }>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({ amount: null as number | null, cash_account_id: null as number | null, paid_on: '', reference: '', note: '' });
const accountOptions = computed(() => props.accounts.map((account) => ({ value: account.id, label: account.name })));

watch(open, (isOpen) => {
    if (isOpen) {
        form.defaults({ amount: props.purchase.outstanding, cash_account_id: props.accounts[0]?.id ?? null, paid_on: props.today, reference: '', note: '' });
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    form.post(route('purchases.payments.store', props.purchase.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('purchases.pay_title', { supplier: purchase.supplier.name }) }}</DialogTitle>
                    <DialogDescription>{{ $t('purchases.pay_description', { number: purchase.number, amount: purchase.outstanding_label }) }}</DialogDescription>
                </DialogHeader>

                <FormField id="pay-amount" :label="$t('purchases.fields.pay_amount')" :error="form.errors.amount" required>
                    <template #default="{ invalid, describedBy }">
                        <MoneyInput id="pay-amount" v-model="form.amount" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <div class="grid grid-cols-2 gap-3">
                    <FormField id="pay-account" :label="$t('purchases.fields.pay_account')" :error="form.errors.cash_account_id" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="pay-account" v-model="form.cash_account_id" :options="accountOptions" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="pay-date" :label="$t('purchases.fields.paid_on')" :error="form.errors.paid_on" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="pay-date" v-model="form.paid_on" type="date" :min="purchase.purchase_date" :max="today" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <FormField id="pay-reference" :label="$t('purchases.fields.reference')" :hint="$t('purchases.reference_hint')" :error="form.errors.reference">
                    <template #default="{ invalid, describedBy }">
                        <Input id="pay-reference" v-model="form.reference" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="pay-note" :label="$t('purchases.fields.note')" :error="form.errors.note">
                    <template #default="{ invalid, describedBy }">
                        <Input id="pay-note" v-model="form.note" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" :loading="form.processing">{{ $t('purchases.pay') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
