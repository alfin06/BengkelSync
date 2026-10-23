<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import axios from 'axios';
import { AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import type { Customer } from '@/types/customers';
import type { SelectOption } from '@/types/ui';

const props = defineProps<{
    types: SelectOption[];
    /** Existing customer when editing */
    customer?: Customer;
    submitLabel: string;
    cancelUrl: string;
}>();

const sameAsPhone = !props.customer || !props.customer.whatsapp || props.customer.whatsapp === props.customer.phone;

const form = useForm({
    type: props.customer?.type ?? 'individual',
    name: props.customer?.name ?? '',
    phone: props.customer?.phone ?? '',
    whatsapp_same_as_phone: sameAsPhone,
    whatsapp: sameAsPhone ? '' : (props.customer?.whatsapp ?? ''),
    email: props.customer?.email ?? '',
    address: props.customer?.address ?? '',
    npwp: props.customer?.npwp ?? '',
    notes: props.customer?.notes ?? '',
});

interface PhoneMatch {
    id: number;
    name: string;
    url: string;
}

// Same phone is allowed (families, fleets), so we only warn (decision 1 of the Phase 1 plan).
const phoneMatches = ref<PhoneMatch[]>([]);

async function checkPhone(): Promise<void> {
    phoneMatches.value = [];
    if (form.phone.replace(/\D/g, '').length < 9) {
        return;
    }

    const { data } = await axios.get<PhoneMatch[]>(route('customers.lookup.phone'), {
        params: { phone: form.phone, except: props.customer?.id },
    });
    phoneMatches.value = data;
}

function submit(): void {
    if (props.customer) {
        form.put(route('customers.update', props.customer.id));
    } else {
        form.post(route('customers.store'));
    }
}
</script>

<template>
    <form class="grid gap-5" novalidate @submit.prevent="submit">
        <FormField id="type" :label="$t('customers.fields.type')" :error="form.errors.type" required>
            <template #default="{ invalid, describedBy }">
                <Select id="type" v-model="form.type" :options="types" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <FormField
            id="name"
            :label="form.type === 'company' ? $t('customers.fields.company_name') : $t('customers.fields.name')"
            :error="form.errors.name"
            required
        >
            <template #default="{ invalid, describedBy }">
                <Input id="name" v-model="form.name" autocomplete="off" autofocus :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <FormField id="phone" :label="$t('fields.phone')" :hint="$t('fields.phone_hint')" :error="form.errors.phone">
            <template #default="{ invalid, describedBy }">
                <Input
                    id="phone"
                    v-model="form.phone"
                    type="tel"
                    inputmode="tel"
                    autocomplete="off"
                    placeholder="0812-3456-7890"
                    :invalid="invalid"
                    :aria-describedby="describedBy"
                    @blur="checkPhone"
                />
            </template>
        </FormField>

        <div
            v-if="phoneMatches.length > 0"
            class="flex gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
            role="status"
        >
            <AlertTriangle class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
            <div class="grid gap-1">
                <p>{{ $t('customers.phone_duplicate') }}</p>
                <ul class="list-inside list-disc">
                    <li v-for="match in phoneMatches" :key="match.id">
                        <Link :href="match.url" class="font-medium underline underline-offset-4">{{ match.name }}</Link>
                    </li>
                </ul>
            </div>
        </div>

        <Checkbox id="whatsapp_same_as_phone" v-model="form.whatsapp_same_as_phone" :label="$t('customers.fields.whatsapp_same')" />

        <FormField v-if="!form.whatsapp_same_as_phone" id="whatsapp" :label="$t('customers.fields.whatsapp')" :error="form.errors.whatsapp">
            <template #default="{ invalid, describedBy }">
                <Input
                    id="whatsapp"
                    v-model="form.whatsapp"
                    type="tel"
                    inputmode="tel"
                    autocomplete="off"
                    placeholder="0812-3456-7890"
                    :invalid="invalid"
                    :aria-describedby="describedBy"
                />
            </template>
        </FormField>

        <FormField id="address" :label="$t('customers.fields.address')" :error="form.errors.address">
            <template #default="{ invalid, describedBy }">
                <Textarea id="address" v-model="form.address" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <FormField id="email" :label="$t('fields.email')" :error="form.errors.email">
            <template #default="{ invalid, describedBy }">
                <Input id="email" v-model="form.email" type="email" inputmode="email" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <FormField v-if="form.type === 'company'" id="npwp" :label="$t('customers.fields.npwp')" :error="form.errors.npwp">
            <template #default="{ invalid, describedBy }">
                <Input id="npwp" v-model="form.npwp" inputmode="numeric" placeholder="00.000.000.0-000.000" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <FormField id="notes" :label="$t('customers.fields.notes')" :error="form.errors.notes">
            <template #default="{ invalid, describedBy }">
                <Textarea id="notes" v-model="form.notes" rows="3" :invalid="invalid" :aria-describedby="describedBy" />
            </template>
        </FormField>

        <div class="sticky bottom-16 -mx-4 flex flex-col-reverse gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:flex-row sm:justify-end sm:border-0 sm:bg-transparent sm:p-0 md:bottom-0">
            <Link :href="cancelUrl" :class="buttonVariants({ variant: 'outline' })">{{ $t('common.cancel') }}</Link>
            <Button type="submit" :loading="form.processing">{{ submitLabel }}</Button>
        </div>
    </form>
</template>
