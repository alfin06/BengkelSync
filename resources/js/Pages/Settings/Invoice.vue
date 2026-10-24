<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    settings: { tax_enabled: boolean; tax_name: string; tax_rate: string; tax_inclusive: boolean; invoice_footer: string };
}>();

const form = useForm({ ...props.settings, tax_rate: props.settings.tax_rate.replace('.', ',') });

function submit(): void {
    form.put(route('settings.invoice.update'), { preserveScroll: true, onSuccess: () => form.defaults() });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('invoices.settings.title')" :description="$t('invoices.settings.description')" />

        <form class="grid max-w-2xl gap-6" novalidate @submit.prevent="submit">
            <Card>
                <CardHeader><CardTitle>{{ $t('invoices.settings.tax') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-5">
                    <Checkbox id="tax_enabled" v-model="form.tax_enabled" :label="$t('invoices.settings.tax_enabled')" />
                    <p class="-mt-3 text-sm text-muted-foreground">{{ $t('invoices.settings.tax_enabled_hint') }}</p>

                    <template v-if="form.tax_enabled">
                        <div class="grid grid-cols-2 gap-4">
                            <FormField id="tax_name" :label="$t('invoices.settings.tax_name')" :error="form.errors.tax_name" required>
                                <template #default="{ invalid, describedBy }">
                                    <Input id="tax_name" v-model="form.tax_name" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                            <FormField id="tax_rate" :label="$t('invoices.settings.tax_rate')" :error="form.errors.tax_rate" required>
                                <template #default="{ invalid, describedBy }">
                                    <div class="relative">
                                        <Input id="tax_rate" v-model="form.tax_rate" inputmode="decimal" class="pr-8 text-right" :invalid="invalid" :aria-describedby="describedBy" />
                                        <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                                    </div>
                                </template>
                            </FormField>
                        </div>

                        <fieldset class="grid gap-2">
                            <legend class="mb-1 text-sm font-medium">{{ $t('invoices.settings.prices') }}</legend>
                            <div class="grid gap-2 sm:grid-cols-2" role="radiogroup">
                                <button
                                    v-for="inclusive in [false, true]"
                                    :key="String(inclusive)"
                                    type="button"
                                    role="radio"
                                    :aria-checked="form.tax_inclusive === inclusive"
                                    :class="cn('rounded-md border p-3 text-left text-sm', form.tax_inclusive === inclusive ? 'border-primary bg-primary/10' : 'hover:bg-muted')"
                                    @click="form.tax_inclusive = inclusive"
                                >
                                    <span class="block font-medium">{{ inclusive ? $t('invoices.settings.inclusive') : $t('invoices.settings.exclusive') }}</span>
                                    <span class="block text-muted-foreground">{{ inclusive ? $t('invoices.settings.inclusive_hint') : $t('invoices.settings.exclusive_hint') }}</span>
                                </button>
                            </div>
                        </fieldset>
                    </template>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('invoices.settings.footer') }}</CardTitle></CardHeader>
                <CardContent>
                    <FormField id="invoice_footer" :label="$t('invoices.settings.footer_label')" :hint="$t('invoices.settings.footer_hint')" :error="form.errors.invoice_footer">
                        <template #default="{ invalid, describedBy }">
                            <Textarea id="invoice_footer" v-model="form.invoice_footer" rows="3" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button type="submit" :loading="form.processing" :disabled="!form.isDirty">{{ $t('common.save') }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
