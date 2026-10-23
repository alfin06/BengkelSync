<script setup lang="ts">
import CustomerForm from '@/Components/Customers/CustomerForm.vue';
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Customer } from '@/types/customers';
import type { SelectOption } from '@/types/ui';

defineProps<{ customer: Customer; types: SelectOption[] }>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('customers.edit_title')" :description="customer.name" />

        <Card class="max-w-2xl">
            <CardContent class="pt-4 sm:pt-6">
                <CustomerForm
                    :customer="customer"
                    :types="types"
                    :submit-label="$t('common.save')"
                    :cancel-url="route('customers.show', customer.id)"
                />
            </CardContent>
        </Card>
    </AppLayout>
</template>
