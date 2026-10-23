<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { trans } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps<{
    profile: { name: string; email: string; phone: string; locale: string };
    locales: string[];
}>();

const profileForm = useForm({
    name: props.profile.name,
    phone: props.profile.phone,
    locale: props.profile.locale,
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const localeOptions = computed(() => props.locales.map((code) => ({ value: code, label: trans(`locales.${code}`) })));

function saveProfile(): void {
    const localeChanged = profileForm.locale !== props.profile.locale;

    profileForm.patch(route('profile.update'), {
        preserveScroll: true,
        onSuccess: () => {
            // A new language needs a full reload so both PHP and Vue switch.
            if (localeChanged) {
                window.location.reload();

                return;
            }

            // Show what was stored (e.g. the formatted phone number) and mark the form clean.
            profileForm.defaults({ name: props.profile.name, phone: props.profile.phone, locale: props.profile.locale });
            profileForm.reset();
        },
    });
}

function savePassword(): void {
    passwordForm.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
        onError: () => passwordForm.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('profile.title')" :description="$t('profile.description')" />

        <div class="grid max-w-2xl gap-6">
            <Card>
                <form novalidate @submit.prevent="saveProfile">
                    <CardHeader>
                        <CardTitle>{{ $t('profile.info_title') }}</CardTitle>
                        <CardDescription>{{ $t('profile.info_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <FormField id="name" :label="$t('fields.name')" :error="profileForm.errors.name" required>
                            <template #default="{ invalid, describedBy }">
                                <Input id="name" v-model="profileForm.name" autocomplete="name" required :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>

                        <FormField id="email" :label="$t('fields.email')" :hint="$t('profile.email_hint')">
                            <template #default="{ describedBy }">
                                <Input id="email" :model-value="profile.email" type="email" readonly disabled :aria-describedby="describedBy" />
                            </template>
                        </FormField>

                        <FormField id="phone" :label="$t('fields.phone')" :hint="$t('fields.phone_hint')" :error="profileForm.errors.phone">
                            <template #default="{ invalid, describedBy }">
                                <Input
                                    id="phone"
                                    v-model="profileForm.phone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    placeholder="0812-3456-7890"
                                    :invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>

                        <FormField id="locale" :label="$t('fields.language')" :error="profileForm.errors.locale">
                            <template #default="{ invalid, describedBy }">
                                <Select id="locale" v-model="profileForm.locale" :options="localeOptions" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>
                    </CardContent>
                    <CardFooter>
                        <Button type="submit" :loading="profileForm.processing" :disabled="!profileForm.isDirty">
                            {{ $t('common.save') }}
                        </Button>
                    </CardFooter>
                </form>
            </Card>

            <Card>
                <form novalidate @submit.prevent="savePassword">
                    <CardHeader>
                        <CardTitle>{{ $t('profile.password_title') }}</CardTitle>
                        <CardDescription>{{ $t('profile.password_description') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <FormField id="current_password" :label="$t('fields.current_password')" :error="passwordForm.errors.current_password" required>
                            <template #default="{ invalid, describedBy }">
                                <Input
                                    id="current_password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    autocomplete="current-password"
                                    :invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>

                        <FormField
                            id="new_password"
                            :label="$t('fields.new_password')"
                            :hint="$t('fields.password_hint')"
                            :error="passwordForm.errors.password"
                            required
                        >
                            <template #default="{ invalid, describedBy }">
                                <Input
                                    id="new_password"
                                    v-model="passwordForm.password"
                                    type="password"
                                    autocomplete="new-password"
                                    :invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>

                        <FormField id="password_confirmation" :label="$t('fields.password_confirmation')" :error="passwordForm.errors.password_confirmation" required>
                            <template #default="{ invalid, describedBy }">
                                <Input
                                    id="password_confirmation"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    :invalid="invalid"
                                    :aria-describedby="describedBy"
                                />
                            </template>
                        </FormField>
                    </CardContent>
                    <CardFooter>
                        <Button type="submit" :loading="passwordForm.processing">{{ $t('profile.password_submit') }}</Button>
                    </CardFooter>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
