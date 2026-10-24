<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ImageUp, Lock, Receipt } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * "Toko & cabang": the shop's name and invoice lock period (Owner), and the active
 * branch's details printed on invoices, kwitansi and SPK (branch manager).
 */
const props = defineProps<{
    shop: { name: string; invoice_lock_days: number | null } | null;
    branch: { name: string; code: string; address: string; phone: string; npwp: string; logo_url: string | null } | null;
    can: { updateShop: boolean; updateBranch: boolean; taxSettings: boolean };
}>();

const shopForm = useForm({ name: props.shop?.name ?? '', invoice_lock_days: props.shop?.invoice_lock_days === null || props.shop === null ? '' : String(props.shop.invoice_lock_days) });

function saveShop(): void {
    shopForm
        .transform((data) => ({ ...data, invoice_lock_days: data.invoice_lock_days === '' ? null : Number(data.invoice_lock_days) }))
        .put(route('settings.shop.update'), { preserveScroll: true, onSuccess: () => shopForm.defaults() });
}

const branchForm = useForm({
    name: props.branch?.name ?? '',
    address: props.branch?.address ?? '',
    phone: props.branch?.phone ?? '',
    npwp: props.branch?.npwp ?? '',
    logo: null as File | null,
    remove_logo: false,
});
const logoInput = ref<HTMLInputElement | null>(null);
const preview = ref<string | null>(null);

function pickLogo(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    branchForm.logo = file;
    branchForm.remove_logo = false;
    preview.value = file ? URL.createObjectURL(file) : null;
}

function saveBranch(): void {
    branchForm.post(route('settings.branch.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            branchForm.logo = null;
            preview.value = null;
            if (logoInput.value) {
                logoInput.value.value = '';
            }
        },
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('shop_settings.title')" :description="$t('shop_settings.description')">
            <template v-if="can.taxSettings" #actions>
                <Link :href="route('settings.invoice.edit')" :class="buttonVariants({ variant: 'outline' })"><Receipt aria-hidden="true" /> {{ $t('nav.invoice_settings') }}</Link>
            </template>
        </PageHeader>

        <div class="grid max-w-2xl gap-6">
            <Card v-if="shop">
                <CardHeader><CardTitle>{{ $t('shop_settings.shop') }}</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-5" novalidate @submit.prevent="saveShop">
                        <FormField id="shop-name" :label="$t('shop_settings.shop_name')" :hint="$t('shop_settings.shop_name_hint')" :error="shopForm.errors.name" required>
                            <template #default="{ invalid, describedBy }">
                                <Input id="shop-name" v-model="shopForm.name" :disabled="!can.updateShop" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>

                        <FormField id="lock-days" :label="$t('shop_settings.lock_days')" :hint="$t('shop_settings.lock_days_hint')" :error="shopForm.errors.invoice_lock_days">
                            <template #default="{ invalid, describedBy }">
                                <div class="flex items-center gap-2">
                                    <Lock class="size-4 text-muted-foreground" aria-hidden="true" />
                                    <Input
                                        id="lock-days"
                                        v-model="shopForm.invoice_lock_days"
                                        inputmode="numeric"
                                        class="w-24 text-right"
                                        :placeholder="$t('shop_settings.no_lock')"
                                        :disabled="!can.updateShop"
                                        :invalid="invalid"
                                        :aria-describedby="describedBy"
                                    />
                                    <span class="text-sm text-muted-foreground">{{ $t('shop_settings.days') }}</span>
                                </div>
                            </template>
                        </FormField>

                        <div v-if="can.updateShop" class="flex justify-end">
                            <Button type="submit" :loading="shopForm.processing" :disabled="!shopForm.isDirty">{{ $t('common.save') }}</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card v-if="branch">
                <CardHeader><CardTitle>{{ $t('shop_settings.branch', { code: branch.code }) }}</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-5" novalidate @submit.prevent="saveBranch">
                        <FormField id="branch-name" :label="$t('shop_settings.branch_name')" :error="branchForm.errors.name" required>
                            <template #default="{ invalid, describedBy }">
                                <Input id="branch-name" v-model="branchForm.name" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <FormField id="branch-address" :label="$t('shop_settings.address')" :error="branchForm.errors.address">
                            <template #default="{ invalid, describedBy }">
                                <Textarea id="branch-address" v-model="branchForm.address" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <FormField id="branch-phone" :label="$t('fields.phone')" :error="branchForm.errors.phone">
                                <template #default="{ invalid, describedBy }">
                                    <Input id="branch-phone" v-model="branchForm.phone" type="tel" inputmode="tel" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                            <FormField id="branch-npwp" label="NPWP" :hint="$t('shop_settings.npwp_hint')" :error="branchForm.errors.npwp">
                                <template #default="{ invalid, describedBy }">
                                    <Input id="branch-npwp" v-model="branchForm.npwp" inputmode="numeric" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                        </div>

                        <FormField id="branch-logo" :label="$t('shop_settings.logo')" :hint="$t('shop_settings.logo_hint')" :error="branchForm.errors.logo">
                            <template #default="{ describedBy }">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-20 items-center justify-center rounded-md border bg-white p-1">
                                        <img v-if="preview || (branch.logo_url && !branchForm.remove_logo)" :src="preview ?? branch.logo_url ?? ''" alt="" class="max-h-full max-w-full object-contain" />
                                        <ImageUp v-else class="size-6 text-muted-foreground" aria-hidden="true" />
                                    </div>
                                    <label for="branch-logo" :class="buttonVariants({ variant: 'outline', size: 'sm' })" class="cursor-pointer">{{ $t('shop_settings.choose_logo') }}</label>
                                    <input id="branch-logo" ref="logoInput" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" :aria-describedby="describedBy" @change="pickLogo" />
                                </div>
                            </template>
                        </FormField>
                        <Checkbox v-if="branch.logo_url && !branchForm.logo" id="remove-logo" v-model="branchForm.remove_logo" :label="$t('shop_settings.remove_logo')" />

                        <div class="flex justify-end">
                            <Button type="submit" :loading="branchForm.processing" :disabled="!branchForm.isDirty">{{ $t('common.save') }}</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
