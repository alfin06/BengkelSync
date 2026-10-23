<script setup lang="ts">
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/ui/PageHeader.vue';
import VehicleForm from '@/Components/Vehicles/VehicleForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Customer, VehicleFormOptions } from '@/types/customers';

defineProps<VehicleFormOptions & { customer: Customer }>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('vehicles.create_title')" :description="$t('vehicles.owner_is', { name: customer.name })" />

        <Card class="max-w-2xl">
            <CardContent class="pt-4 sm:pt-6">
                <VehicleForm
                    :customer-id="customer.id"
                    :suggestions="suggestions"
                    :transmissions="transmissions"
                    :fuel-types="fuelTypes"
                    :cancel-url="route('customers.show', customer.id)"
                />
            </CardContent>
        </Card>
    </AppLayout>
</template>
