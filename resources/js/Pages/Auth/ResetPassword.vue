<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps<{ email: string; token: string }>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit(): void {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <Head :title="$t('reset.title')" />

    <GuestLayout :title="$t('reset.title')">
        <form class="grid gap-4" novalidate @submit.prevent="submit">
            <FormField id="email" :label="$t('fields.email')" :error="form.errors.email">
                <template #default="{ invalid, describedBy }">
                    <Input id="email" v-model="form.email" type="email" autocomplete="username" required :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="password" :label="$t('fields.new_password')" :hint="$t('fields.password_hint')" :error="form.errors.password">
                <template #default="{ invalid, describedBy }">
                    <Input id="password" v-model="form.password" type="password" autocomplete="new-password" autofocus required :invalid="invalid" :aria-describedby="describedBy" />
                </template>
            </FormField>

            <FormField id="password_confirmation" :label="$t('fields.password_confirmation')" :error="form.errors.password_confirmation">
                <template #default="{ invalid, describedBy }">
                    <Input
                        id="password_confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        :invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <Button type="submit" class="w-full" :loading="form.processing">{{ $t('reset.submit') }}</Button>
        </form>
    </GuestLayout>
</template>
