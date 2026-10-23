<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Button } from '@/Components/ui/button';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps<{ status?: string | null }>();

const form = useForm({ email: '' });
</script>

<template>
    <Head :title="$t('forgot.title')" />

    <GuestLayout :title="$t('forgot.title')" :description="$t('forgot.description')">
        <p v-if="status" class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800" role="status">{{ status }}</p>

        <form class="grid gap-4" novalidate @submit.prevent="form.post(route('password.email'))">
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

            <Button type="submit" class="w-full" :loading="form.processing">{{ $t('forgot.submit') }}</Button>

            <Link :href="route('login')" class="text-center text-sm text-primary underline-offset-4 hover:underline">
                {{ $t('forgot.back') }}
            </Link>
        </form>
    </GuestLayout>
</template>
