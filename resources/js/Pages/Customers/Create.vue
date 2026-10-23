<script setup lang="ts">
import CustomerForm from '@/Components/Customers/CustomerForm.vue';
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { SelectOption } from '@/types/ui';

defineProps<{ types: SelectOption[] }>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('customers.create_title')" />

        <Card class="max-w-2xl">
            <CardContent class="pt-4 sm:pt-6">
                <CustomerForm :types="types" :submit-label="$t('common.save')" :cancel-url="route('customers.index')" />
            </CardContent>
        </Card>
    </AppLayout>
</template>
