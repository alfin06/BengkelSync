<script setup lang="ts">
import PartForm from '@/Components/Parts/PartForm.vue';
import { Card, CardContent } from '@/Components/ui/card';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Part, UnitOption } from '@/types/inventory';

defineProps<{
    part: Part;
    units: UnitOption[];
    categories: string[];
    brands: string[];
    can: { viewCost: boolean };
}>();
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('parts.edit_title')" :description="part.name" />

        <Card class="max-w-3xl">
            <CardContent class="pt-4 sm:pt-6">
                <PartForm
                    :part="part"
                    :units="units"
                    :categories="categories"
                    :brands="brands"
                    :can-view-cost="can.viewCost"
                    :cancel-url="route('parts.show', part.id)"
                />
            </CardContent>
        </Card>
    </AppLayout>
</template>
