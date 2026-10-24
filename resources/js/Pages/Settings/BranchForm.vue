<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { ImageUp } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * Add a branch, or edit one: the details printed on invoices, kwitansi and SPK, the
 * code used in document numbers, the time zone and the logo.
 */
const props = defineProps<{
    branchItem: {
        id: number;
        name: string;
        code: string;
        timezone: string;
        address: string;
        phone: string;
        npwp: string;
        logo_url: string | null;
    } | null;
    timezones: string[];
    codeLocked: boolean;
}>();

const timezoneLabels: Record<string, string> = {
    'Asia/Jakarta': 'branches.timezone_wib',
    'Asia/Makassar': 'branches.timezone_wita',
    'Asia/Jayapura': 'branches.timezone_wit',
};
const timezoneOptions = computed(() => props.timezones.map((zone) => ({ value: zone, label: trans(timezoneLabels[zone] ?? zone) })));

const form = useForm({
    name: props.branchItem?.name ?? '',
    code: props.branchItem?.code ?? '',
    timezone: props.branchItem?.timezone ?? 'Asia/Jakarta',
    address: props.branchItem?.address ?? '',
    phone: props.branchItem?.phone ?? '',
    npwp: props.branchItem?.npwp ?? '',
    logo: null as File | null,
    remove_logo: false,
});
const preview = ref<string | null>(null);

function pickLogo(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    form.logo = file;
    form.remove_logo = false;
    preview.value = file ? URL.createObjectURL(file) : null;
}

function submit(): void {
    if (props.branchItem === null) {
        form.post(route('settings.branches.store'), { forceFormData: true });

        return;
    }

    // Uploads go as POST with _method=put (CLAUDE.md "Uploads with PUT").
    form.transform((data) => ({ ...data, _method: 'put' })).post(route('settings.branches.update', props.branchItem.id), { forceFormData: true });
}
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="branchItem ? $t('branches.edit_title', { name: branchItem.name }) : $t('branches.add_title')"
            :description="$t('branches.form_description')"
        />

        <Card class="max-w-2xl">
            <CardContent class="pt-6">
                <form class="grid gap-5" novalidate @submit.prevent="submit">
                    <div class="grid gap-5 sm:grid-cols-[1fr_10rem]">
                        <FormField id="branch-name" :label="$t('shop_settings.branch_name')" :error="form.errors.name" required>
                            <template #default="{ invalid, describedBy }">
                                <Input id="branch-name" v-model="form.name" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <FormField
                            id="branch-code"
                            :label="$t('branches.code')"
                            :hint="codeLocked ? $t('branches.code_locked_hint') : $t('branches.code_hint')"
                            :error="form.errors.code"
                            required
                        >
                            <template #default="{ invalid, describedBy }">
                                <Input
                                    id="branch-code"
                                    v-model="form.code"
                                    class="uppercase"
                                    maxlength="6"
                                    autocapitalize="characters"
                                    :disabled="codeLocked"
                                    :invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </div>

                    <FormField id="branch-address" :label="$t('shop_settings.address')" :error="form.errors.address">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="branch-address" v-model="form.address" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField id="branch-phone" :label="$t('fields.phone')" :error="form.errors.phone">
                            <template #default="{ invalid, describedBy }">
                                <Input id="branch-phone" v-model="form.phone" type="tel" inputmode="tel" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                        <FormField id="branch-npwp" label="NPWP" :hint="$t('shop_settings.npwp_hint')" :error="form.errors.npwp">
                            <template #default="{ invalid, describedBy }">
                                <Input id="branch-npwp" v-model="form.npwp" inputmode="numeric" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </div>

                    <FormField id="branch-timezone" :label="$t('branches.timezone')" :hint="$t('branches.timezone_hint')" :error="form.errors.timezone" required>
                        <template #default="{ invalid, describedBy }">
                            <Select id="branch-timezone" v-model="form.timezone" :options="timezoneOptions" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>

                    <FormField id="branch-logo" :label="$t('shop_settings.logo')" :hint="$t('shop_settings.logo_hint')" :error="form.errors.logo">
                        <template #default="{ describedBy }">
                            <div class="flex items-center gap-4">
                                <div class="flex size-20 items-center justify-center rounded-md border bg-white p-1">
                                    <img
                                        v-if="preview || (branchItem?.logo_url && !form.remove_logo)"
                                        :src="preview ?? branchItem?.logo_url ?? ''"
                                        alt=""
                                        class="max-h-full max-w-full object-contain"
                                    />
                                    <ImageUp v-else class="size-6 text-muted-foreground" aria-hidden="true" />
                                </div>
                                <label for="branch-logo" :class="buttonVariants({ variant: 'outline', size: 'sm' })" class="cursor-pointer">{{ $t('shop_settings.choose_logo') }}</label>
                                <input id="branch-logo" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" :aria-describedby="describedBy" @change="pickLogo" />
                            </div>
                        </template>
                    </FormField>
                    <Checkbox v-if="branchItem?.logo_url && !form.logo" id="remove-logo" v-model="form.remove_logo" :label="$t('shop_settings.remove_logo')" />

                    <p v-if="!branchItem" class="rounded-md bg-muted p-3 text-sm text-muted-foreground">{{ $t('branches.add_note') }}</p>

                    <div class="flex justify-end gap-2">
                        <Link :href="route('settings.shop.edit')" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
                        <Button type="submit" :loading="form.processing">{{ branchItem ? $t('common.save') : $t('branches.add') }}</Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </AppLayout>
</template>
