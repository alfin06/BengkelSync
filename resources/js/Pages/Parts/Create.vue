<script setup lang="ts">
import PartForm from '@/Components/Parts/PartForm.vue';
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { UnitOption } from '@/types/inventory';

defineProps<{
    units: UnitOption[];
    categories: string[];
    brands: string[];
    can: { adjustStock: boolean; viewCost: boolean };
}>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('parts.create_title')" />

        <Card class="max-w-3xl">
            <CardContent class="pt-4 sm:pt-6">
                <PartForm
                    :units="units"
                    :categories="categories"
                    :brands="brands"
                    :can-view-cost="can.viewCost"
                    :can-adjust-stock="can.adjustStock"
                    :cancel-url="route('parts.index')"
                />
            </CardContent>
        </Card>
    </AppLayout>
</template>
