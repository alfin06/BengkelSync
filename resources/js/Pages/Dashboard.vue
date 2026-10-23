<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LayoutDashboard } from 'lucide-vue-next';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
</script>

<template>
    <AppLayout>
        <PageHeader
            :title="$t('dashboard.greeting', { name: page.props.auth.user?.name ?? '' })"
            :description="page.props.branch ? $t('dashboard.working_in', { branch: page.props.branch.name }) : undefined"
        />

        <EmptyState
            :icon="LayoutDashboard"
            :title="$t('dashboard.empty_title')"
            :description="$t('dashboard.empty_description')"
        />
    </AppLayout>
</template>
