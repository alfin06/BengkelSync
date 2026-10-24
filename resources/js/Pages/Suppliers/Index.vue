<script setup lang="ts">
import { MessageCircle, Pencil, Phone, Plus, Truck } from 'lucide-vue-next';
import { ref } from 'vue';
import SupplierDialog from '@/Components/Suppliers/SupplierDialog.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import SearchInput from '@/Components/ui/SearchInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import type { Supplier } from '@/types/inventory';

defineProps<{
    suppliers: Paginated<Supplier>;
    filters: { search: string };
    can: { create: boolean; update: boolean; delete: boolean };
}>();

const dialogOpen = ref(false);
const editing = ref<Supplier | null>(null);

function openDialog(supplier: Supplier | null): void {
    editing.value = supplier;
    dialogOpen.value = true;
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('suppliers.title')" :description="$t('suppliers.description')">
            <template v-if="can.create" #actions>
                <Button @click="openDialog(null)"><Plus aria-hidden="true" /> {{ $t('suppliers.add') }}</Button>
            </template>
        </PageHeader>

        <div class="mb-4">
            <SearchInput :initial="filters.search" :placeholder="$t('suppliers.search_placeholder')" :only="['suppliers', 'filters']" />
        </div>

        <EmptyState
            v-if="suppliers.data.length === 0"
            :icon="Truck"
            :title="filters.search ? $t('suppliers.no_results_title') : $t('suppliers.empty_title')"
            :description="filters.search ? undefined : $t('suppliers.empty_description')"
        >
            <Button v-if="can.create && !filters.search" @click="openDialog(null)"><Plus aria-hidden="true" /> {{ $t('suppliers.add') }}</Button>
        </EmptyState>

        <ul v-else class="grid gap-3 md:grid-cols-2">
            <li v-for="supplier in suppliers.data" :key="supplier.id" class="grid gap-3 rounded-lg border bg-card p-4">
                <div class="flex items-start gap-3">
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ supplier.name }}</span>
                        <span v-if="supplier.sales_name" class="block text-sm text-muted-foreground">{{ $t('suppliers.sales', { name: supplier.sales_name }) }}</span>
                        <span v-if="supplier.address" class="block truncate text-sm text-muted-foreground">{{ supplier.address }}</span>
                    </span>
                    <Button v-if="can.update" variant="ghost" size="icon" :aria-label="$t('common.edit') + ' ' + supplier.name" @click="openDialog(supplier)">
                        <Pencil aria-hidden="true" />
                    </Button>
                </div>
                <div v-if="supplier.phone_e164 || supplier.whatsapp_link" class="flex flex-wrap gap-2">
                    <a v-if="supplier.phone_e164" :href="`tel:${supplier.phone_e164}`" :class="buttonVariants({ variant: 'outline', size: 'sm' })">
                        <Phone aria-hidden="true" /> {{ supplier.phone }}
                    </a>
                    <a v-if="supplier.whatsapp_link" :href="supplier.whatsapp_link" target="_blank" rel="noopener noreferrer" :class="buttonVariants({ variant: 'outline', size: 'sm' })">
                        <MessageCircle aria-hidden="true" /> WhatsApp
                    </a>
                </div>
            </li>
        </ul>

        <div class="mt-4">
            <Pagination :meta="suppliers" />
        </div>

        <SupplierDialog v-model:open="dialogOpen" :supplier="editing" :can-delete="can.delete" />
    </AppLayout>
</template>
