<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { CreditCard, Pencil, Plus, Trash2, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CashAccountSetting, PaymentMethodSetting } from '@/types/cash';
import type { SelectOption } from '@/types/ui';

const props = defineProps<{
    accounts: CashAccountSetting[];
    methods: PaymentMethodSetting[];
    accountTypes: SelectOption[];
    methodTypes: SelectOption[];
    /** Highest purchase Front Desk may pay on the spot from cash; null = no limit */
    cashPurchaseLimit: number | null;
}>();

// Front Desk's on-the-spot cash purchases
const limitForm = useForm({ cash_purchase_limit: props.cashPurchaseLimit });

function saveLimit(): void {
    limitForm.put(route('settings.payments.cash-purchase-limit.update'), { preserveScroll: true, onSuccess: () => limitForm.defaults() });
}

// Cash accounts
const accountOpen = ref(false);
const editingAccount = ref<CashAccountSetting | null>(null);
const accountForm = useForm({ name: '', type: 'bank', is_active: true, sort: 0 });

function openAccount(account: CashAccountSetting | null): void {
    editingAccount.value = account;
    accountForm.defaults({
        name: account?.name ?? '',
        type: account?.type ?? 'bank',
        is_active: account?.is_active ?? true,
        sort: account?.sort ?? props.accounts.length + 1,
    });
    accountForm.reset();
    accountForm.clearErrors();
    accountOpen.value = true;
}

function saveAccount(): void {
    const options = { preserveScroll: true, onSuccess: () => (accountOpen.value = false) };

    if (editingAccount.value) {
        accountForm.put(route('settings.payments.accounts.update', editingAccount.value.id), options);
    } else {
        accountForm.post(route('settings.payments.accounts.store'), options);
    }
}

// Payment methods
const activeAccounts = computed(() => props.accounts.filter((account) => account.is_active).map((account) => ({ value: account.id, label: account.name })));
const methodOpen = ref(false);
const editingMethod = ref<PaymentMethodSetting | null>(null);
const methodForm = useForm({
    name: '',
    type: 'qris',
    cash_account_id: null as number | null,
    surcharge_percent: '0',
    needs_reference: false,
    is_active: true,
    sort: 0,
});

function openMethod(method: PaymentMethodSetting | null): void {
    editingMethod.value = method;
    methodForm.defaults({
        name: method?.name ?? '',
        type: method?.type ?? 'qris',
        cash_account_id: method?.cash_account_id ?? activeAccounts.value[0]?.value ?? null,
        surcharge_percent: method?.surcharge_percent ?? '0',
        needs_reference: method?.needs_reference ?? false,
        is_active: method?.is_active ?? true,
        sort: method?.sort ?? props.methods.length + 1,
    });
    methodForm.reset();
    methodForm.clearErrors();
    methodOpen.value = true;
}

function saveMethod(): void {
    const options = { preserveScroll: true, onSuccess: () => (methodOpen.value = false) };
    methodForm.transform((data) => ({ ...data, surcharge_percent: String(data.surcharge_percent).replace(',', '.') }));

    if (editingMethod.value) {
        methodForm.put(route('settings.payments.methods.update', editingMethod.value.id), options);
    } else {
        methodForm.post(route('settings.payments.methods.store'), options);
    }
}

// Deleting either kind
const confirmOpen = ref(false);
const deleting = ref(false);
const toDelete = ref<{ url: string; name: string } | null>(null);

function askDelete(url: string, name: string): void {
    toDelete.value = { url, name };
    confirmOpen.value = true;
}

function destroy(): void {
    if (!toDelete.value) {
        return;
    }

    router.delete(toDelete.value.url, {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        onSuccess: () => {
            accountOpen.value = false;
            methodOpen.value = false;
        },
        onFinish: () => {
            deleting.value = false;
            confirmOpen.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('payment_settings.title')" :description="$t('payment_settings.description')" />

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                    <CardTitle>{{ $t('payment_settings.accounts') }}</CardTitle>
                    <Button size="sm" @click="openAccount(null)"><Plus aria-hidden="true" /> {{ $t('payment_settings.add_account') }}</Button>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y rounded-md border">
                        <li v-for="account in accounts" :key="account.id">
                            <button type="button" class="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/50" @click="openAccount(account)">
                                <Wallet class="size-5 text-muted-foreground" aria-hidden="true" />
                                <span class="flex-1">
                                    <span class="block font-medium">{{ account.name }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ account.type_label }}</span>
                                </span>
                                <Badge v-if="!account.is_active">{{ $t('payment_settings.inactive') }}</Badge>
                                <Pencil class="size-4 text-muted-foreground" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                    <CardTitle>{{ $t('payment_settings.methods') }}</CardTitle>
                    <Button size="sm" @click="openMethod(null)"><Plus aria-hidden="true" /> {{ $t('payment_settings.add_method') }}</Button>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y rounded-md border">
                        <li v-for="method in methods" :key="method.id">
                            <button type="button" class="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/50" @click="openMethod(method)">
                                <CreditCard class="size-5 text-muted-foreground" aria-hidden="true" />
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium">{{ method.name }}</span>
                                    <span class="block text-xs text-muted-foreground">
                                        {{ method.type_label }} · {{ $t('payment_settings.into', { account: method.account_name }) }}
                                    </span>
                                    <span class="mt-1 flex flex-wrap gap-1">
                                        <Badge v-if="method.surcharge_percent !== '0'" tone="warning">{{ $t('payment_settings.surcharge', { percent: method.surcharge_percent }) }}</Badge>
                                        <Badge v-if="method.needs_reference" tone="info">{{ $t('payment_settings.reference') }}</Badge>
                                        <Badge v-if="!method.is_active">{{ $t('payment_settings.inactive') }}</Badge>
                                    </span>
                                </span>
                                <Pencil class="size-4 text-muted-foreground" aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="accountOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="saveAccount">
                    <DialogHeader>
                        <DialogTitle>{{ editingAccount ? $t('payment_settings.account_title_edit') : $t('payment_settings.account_title_new') }}</DialogTitle>
                        <DialogDescription>{{ $t('payment_settings.physical_hint') }}</DialogDescription>
                    </DialogHeader>
                    <FormField id="account-name" :label="$t('payment_settings.fields.name')" :error="accountForm.errors.name" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="account-name" v-model="accountForm.name" placeholder="BCA Anton" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="account-type" :label="$t('payment_settings.fields.type')" :error="accountForm.errors.type" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="account-type" v-model="accountForm.type" :options="accountTypes" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="account-sort" :label="$t('payment_settings.fields.sort')" :error="accountForm.errors.sort">
                        <template #default="{ invalid, describedBy }">
                            <Input id="account-sort" v-model.number="accountForm.sort" type="number" inputmode="numeric" min="0" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <Checkbox v-if="editingAccount" id="account-active" v-model="accountForm.is_active" :label="$t('payment_settings.fields.is_active')" />
                    <DialogFooter class="sm:justify-between">
                        <Button
                            v-if="editingAccount"
                            type="button"
                            variant="ghost"
                            class="text-destructive"
                            @click="askDelete(route('settings.payments.accounts.destroy', editingAccount.id), editingAccount.name)"
                        >
                            <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                        </Button>
                        <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                            <Button type="button" variant="outline" @click="accountOpen = false">{{ $t('common.cancel') }}</Button>
                            <Button type="submit" :loading="accountForm.processing">{{ $t('common.save') }}</Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="methodOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="saveMethod">
                    <DialogHeader>
                        <DialogTitle>{{ editingMethod ? $t('payment_settings.method_title_edit') : $t('payment_settings.method_title_new') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="method-name" :label="$t('payment_settings.fields.name')" :error="methodForm.errors.name" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="method-name" v-model="methodForm.name" placeholder="QRIS BCA" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="method-type" :label="$t('payment_settings.fields.type')" :error="methodForm.errors.type" required>
                            <template #default="{ invalid, describedBy }">
                                <Select id="method-type" v-model="methodForm.type" :options="methodTypes" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <FormField id="method-account" :label="$t('payment_settings.fields.account')" :error="methodForm.errors.cash_account_id" required>
                            <template #default="{ invalid, describedBy }">
                                <Select id="method-account" v-model="methodForm.cash_account_id" :options="activeAccounts" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>
                    <FormField id="method-surcharge" :label="$t('payment_settings.fields.surcharge_percent')" :hint="$t('payment_settings.surcharge_hint')" :error="methodForm.errors.surcharge_percent">
                        <template #default="{ invalid, describedBy }">
                            <Input id="method-surcharge" v-model="methodForm.surcharge_percent" inputmode="decimal" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <Checkbox id="method-reference" v-model="methodForm.needs_reference" :label="$t('payment_settings.fields.needs_reference')" />
                    <Checkbox v-if="editingMethod" id="method-active" v-model="methodForm.is_active" :label="$t('payment_settings.fields.is_active')" />
                    <DialogFooter class="sm:justify-between">
                        <Button
                            v-if="editingMethod"
                            type="button"
                            variant="ghost"
                            class="text-destructive"
                            @click="askDelete(route('settings.payments.methods.destroy', editingMethod.id), editingMethod.name)"
                        >
                            <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                        </Button>
                        <div class="flex flex-col-reverse gap-2 sm:ml-auto sm:flex-row">
                            <Button type="button" variant="outline" @click="methodOpen = false">{{ $t('common.cancel') }}</Button>
                            <Button type="submit" :loading="methodForm.processing">{{ $t('common.save') }}</Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Card class="mt-6 lg:max-w-xl">
            <CardHeader>
                <CardTitle>{{ $t('payment_settings.cash_purchase_limit') }}</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="grid gap-4 sm:flex sm:items-end" novalidate @submit.prevent="saveLimit">
                    <FormField
                        id="cash_purchase_limit"
                        class="sm:flex-1"
                        :label="$t('payment_settings.cash_purchase_limit_field')"
                        :hint="$t('payment_settings.cash_purchase_limit_hint')"
                        :error="limitForm.errors.cash_purchase_limit"
                    >
                        <template #default="{ invalid, describedBy }">
                            <MoneyInput id="cash_purchase_limit" v-model="limitForm.cash_purchase_limit" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <Button type="submit" :loading="limitForm.processing" :disabled="!limitForm.isDirty">{{ $t('common.save') }}</Button>
                </form>
            </CardContent>
        </Card>

        <ConfirmDialog
            v-model:open="confirmOpen"
            :title="$t('payment_settings.delete_title')"
            :description="$t('payment_settings.delete_description', { name: toDelete?.name ?? '' })"
            :confirm-label="$t('common.delete')"
            destructive
            :processing="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
