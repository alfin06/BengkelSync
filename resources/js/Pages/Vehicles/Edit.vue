<script setup lang="ts">
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/ui/PageHeader.vue';
import VehicleForm from '@/Components/Vehicles/VehicleForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Vehicle, VehicleFormOptions } from '@/types/customers';

defineProps<VehicleFormOptions & { vehicle: Vehicle }>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('vehicles.edit_title')" :description="vehicle.plate_number" />

        <Card class="max-w-2xl">
            <CardContent class="pt-4 sm:pt-6">
                <VehicleForm
                    :vehicle="vehicle"
                    :suggestions="suggestions"
                    :transmissions="transmissions"
                    :fuel-types="fuelTypes"
                    :cancel-url="route('vehicles.show', vehicle.id)"
                />
            </CardContent>
        </Card>
    </AppLayout>
</template>
