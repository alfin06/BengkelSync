<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({ password: '' });

function submit(): void {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head :title="$t('confirm_password.title')" />

    <GuestLayout :title="$t('confirm_password.title')" :description="$t('confirm_password.description')">
        <form class="grid gap-4" novalidate @submit.prevent="submit">
            <FormField id="password" :label="$t('fields.password')" :error="form.errors.password">
                <template #default="{ invalid, describedBy }">
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        autofocus
                        required
                        :invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <Button type="submit" class="w-full" :loading="form.processing">{{ $t('confirm_password.submit') }}</Button>
        </form>
    </GuestLayout>
</template>
