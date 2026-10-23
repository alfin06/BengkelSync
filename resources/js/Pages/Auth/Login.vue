<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps<{ status?: string | null }>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit(): void {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head :title="$t('login.title')" />

    <GuestLayout :title="$t('login.title')" :description="$t('login.description')">
        <p v-if="status" class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ status }}</p>

        <form class="grid gap-4" novalidate @submit.prevent="submit">
            <FormField id="email" :label="$t('fields.email')" :error="form.errors.email">
                <template #default="{ invalid, describedBy }">
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="username"
                        inputmode="email"
                        autofocus
                        required
                        :invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <FormField id="password" :label="$t('fields.password')" :error="form.errors.password">
                <template #default="{ invalid, describedBy }">
                    <Input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        :invalid="invalid"
                        :aria-describedby="describedBy"
                    />
                </template>
            </FormField>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <Checkbox id="remember" v-model="form.remember" :label="$t('login.remember')" />
                <Link :href="route('password.request')" class="text-sm text-primary underline-offset-4 hover:underline">
                    {{ $t('login.forgot') }}
                </Link>
            </div>

            <Button type="submit" class="w-full" :loading="form.processing">{{ $t('login.submit') }}</Button>
        </form>
    </GuestLayout>
</template>
