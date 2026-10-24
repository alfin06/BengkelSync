<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ArrowLeftRight, Calculator, ChevronRight, Landmark, Lock, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CountCashDialog from '@/Components/Cash/CountCashDialog.vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';
import type { CashAccountSummary } from '@/types/cash';

const props = defineProps<{ accounts: CashAccountSummary[]; hiddenFinancial: boolean }>();

const transferable = computed(() => props.accounts.filter((account) => account.can?.transfer));
const transferOptions = computed(() => transferable.value.map((account) => ({ value: account.id, label: `${account.name} (${account.balance_label})` })));

const transferOpen = ref(false);
const transferForm = useForm({ from_account_id: null as number | null, to_account_id: null as number | null, amount: null as number | null, note: '' });

function openTransfer(): void {
    // Most common case first: from the drawer into petty cash.
    const drawer = transferable.value.find((account) => account.type === 'cash_drawer');
    const petty = transferable.value.find((account) => account.type === 'petty_cash');

    transferForm.defaults({ from_account_id: drawer?.id ?? null, to_account_id: petty?.id ?? null, amount: null, note: '' });
    transferForm.reset();
    transferForm.clearErrors();
    transferOpen.value = true;
}

function submitTransfer(): void {
    transferForm.post(route('cash.transfer'), { preserveScroll: true, onSuccess: () => (transferOpen.value = false) });
}

const countOpen = ref(false);
const counting = ref<CashAccountSummary | null>(null);

function openCount(account: CashAccountSummary): void {
    counting.value = account;
    countOpen.value = true;
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('cash.title')" :description="$t('cash.description')">
            <template v-if="transferable.length >= 2" #actions>
                <Button @click="openTransfer"><ArrowLeftRight aria-hidden="true" /> {{ $t('cash.transfer') }}</Button>
            </template>
        </PageHeader>

        <EmptyState v-if="accounts.length === 0" :icon="Wallet" :title="$t('cash.no_accounts')" />

        <ul v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <li v-for="account in accounts" :key="account.id" class="grid gap-3 rounded-lg border bg-card p-4">
                <Link :href="route('cash.show', account.id)" class="-m-1 flex items-start gap-3 rounded-md p-1 hover:bg-muted/50">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                        <component :is="account.is_physical ? Wallet : Landmark" class="size-5" aria-hidden="true" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ account.name }}</span>
                        <span class="block text-xs text-muted-foreground">{{ account.type_label }}</span>
                        <span :class="cn('mt-1 block text-xl font-semibold tabular-nums', account.balance < 0 && 'text-destructive')">{{ account.balance_label }}</span>
                    </span>
                    <ChevronRight class="mt-2 size-4 text-muted-foreground" aria-hidden="true" />
                </Link>
                <Button v-if="account.can?.adjust" variant="outline" size="sm" @click="openCount(account)">
                    <Calculator aria-hidden="true" /> {{ $t('cash.count') }}
                </Button>
            </li>
        </ul>

        <p v-if="hiddenFinancial" class="mt-4 flex items-center gap-2 text-sm text-muted-foreground">
            <Lock class="size-4" aria-hidden="true" /> {{ $t('cash.hidden_financial') }}
        </p>

        <Dialog v-model:open="transferOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="submitTransfer">
                    <DialogHeader>
                        <DialogTitle>{{ $t('cash.transfer_title') }}</DialogTitle>
                        <DialogDescription>{{ $t('cash.transfer_description') }}</DialogDescription>
                    </DialogHeader>

                    <FormField id="from_account_id" :label="$t('cash.from')" :error="transferForm.errors.from_account_id" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="from_account_id" v-model="transferForm.from_account_id" :options="transferOptions" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="to_account_id" :label="$t('cash.to')" :error="transferForm.errors.to_account_id" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="to_account_id" v-model="transferForm.to_account_id" :options="transferOptions" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="transfer-amount" :label="$t('cash.amount')" :error="transferForm.errors.amount" required>
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput id="transfer-amount" v-model="transferForm.amount" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="transfer-note" :label="$t('cash.note')" :hint="$t('common.optional')" :error="transferForm.errors.note">
                        <template #default="{ invalid, describedBy }">
                            <Input id="transfer-note" v-model="transferForm.note" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="transferOpen = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="transferForm.processing">{{ $t('cash.transfer') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <CountCashDialog v-model:open="countOpen" :account="counting" />
    </AppLayout>
</template>
