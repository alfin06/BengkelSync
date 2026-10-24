<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import type { CashAccountSummary } from '@/types/cash';

/**
 * Cash count ("Hitung kas"): enter the money actually there, with a reason.
 */
const props = defineProps<{ account: CashAccountSummary | null }>();
const open = defineModel<boolean>('open', { default: false });

const form = useForm({ counted_amount: null as number | null, reason: '' });

watch(open, (isOpen) => {
    if (isOpen && props.account) {
        form.defaults({ counted_amount: props.account.balance, reason: '' });
        form.reset();
        form.clearErrors();
    }
});

function submit(): void {
    if (!props.account) {
        return;
    }

    form.post(route('cash.count', props.account.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent v-if="account">
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('cash.count_title', { account: account.name }) }}</DialogTitle>
                    <DialogDescription>{{ $t('cash.count_description', { balance: account.balance_label }) }}</DialogDescription>
                </DialogHeader>

                <FormField id="counted_amount" :label="$t('cash.counted_amount')" :error="form.errors.counted_amount" required>
                    <template #default="{ invalid, describedBy }">
                        <MoneyInput id="counted_amount" v-model="form.counted_amount" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="count-reason" :label="$t('common.reason')" :error="form.errors.reason" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="count-reason" v-model="form.reason" :placeholder="$t('cash.count_reason_placeholder')" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
