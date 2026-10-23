<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Car, ChevronRight, Mail, MapPin, MessageCircle, Pencil, Phone, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Customer } from '@/types/customers';

const props = defineProps<{
    customer: Customer;
    can: { update: boolean; delete: boolean; createVehicle: boolean };
}>();

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    router.delete(route('customers.destroy', props.customer.id), {
        onStart: () => (deleting.value = true),
        onFinish: () => {
            deleting.value = false;
            confirmDelete.value = false;
        },
    });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="customer.name" :description="customer.type === 'company' ? customer.type_label : undefined">
            <template v-if="can.update || can.delete" #actions>
                <Link v-if="can.update" :href="route('customers.edit', customer.id)" :class="buttonVariants({ variant: 'outline' })">
                    <Pencil aria-hidden="true" /> {{ $t('common.edit') }}
                </Link>
                <Button v-if="can.delete" variant="outline" class="text-destructive" @click="confirmDelete = true">
                    <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            <Card>
                <CardHeader><CardTitle>{{ $t('customers.contact') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="customer.phone || customer.whatsapp_link" class="flex flex-wrap gap-2">
                        <a v-if="customer.phone_e164" :href="`tel:${customer.phone_e164}`" :class="buttonVariants({ variant: 'outline' })">
                            <Phone aria-hidden="true" /> {{ customer.phone }}
                        </a>
                        <a
                            v-if="customer.whatsapp_link"
                            :href="customer.whatsapp_link"
                            target="_blank"
                            rel="noopener noreferrer"
                            :class="buttonVariants({ variant: 'outline' })"
                        >
                            <MessageCircle aria-hidden="true" /> {{ $t('customers.whatsapp_chat') }}
                        </a>
                    </div>

                    <dl class="grid gap-3 text-sm">
                        <div v-if="customer.whatsapp && customer.whatsapp !== customer.phone">
                            <dt class="text-muted-foreground">{{ $t('customers.fields.whatsapp') }}</dt>
                            <dd>{{ customer.whatsapp }}</dd>
                        </div>
                        <div v-if="customer.address" class="flex gap-2">
                            <MapPin class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <dd class="whitespace-pre-line">{{ customer.address }}</dd>
                        </div>
                        <div v-if="customer.email" class="flex gap-2">
                            <Mail class="mt-0.5 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <dd><a :href="`mailto:${customer.email}`" class="text-primary hover:underline">{{ customer.email }}</a></dd>
                        </div>
                        <div v-if="customer.npwp">
                            <dt class="text-muted-foreground">{{ $t('customers.fields.npwp') }}</dt>
                            <dd>{{ customer.npwp }}</dd>
                        </div>
                        <div v-if="customer.notes">
                            <dt class="text-muted-foreground">{{ $t('customers.fields.notes') }}</dt>
                            <dd class="whitespace-pre-line">{{ customer.notes }}</dd>
                        </div>
                    </dl>

                    <p v-if="!customer.phone && !customer.address && !customer.email" class="text-sm text-muted-foreground">
                        {{ $t('customers.no_contact') }}
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex-row items-center justify-between gap-3 space-y-0">
                    <CardTitle>{{ $t('customers.vehicles') }}</CardTitle>
                    <Link v-if="can.createVehicle" :href="route('vehicles.create', customer.id)" :class="buttonVariants({ size: 'sm' })">
                        <Plus aria-hidden="true" /> {{ $t('customers.add_vehicle') }}
                    </Link>
                </CardHeader>
                <CardContent>
                    <EmptyState v-if="!customer.vehicles?.length" :icon="Car" :title="$t('customers.no_vehicles')" />
                    <ul v-else class="divide-y rounded-md border">
                        <li v-for="vehicle in customer.vehicles" :key="vehicle.id">
                            <Link :href="route('vehicles.show', vehicle.id)" class="flex min-h-14 items-center gap-3 px-4 py-3 hover:bg-muted/50">
                                <Badge class="font-mono text-sm">{{ vehicle.plate_number }}</Badge>
                                <span class="flex-1 text-sm">{{ vehicle.name }}<span v-if="vehicle.color" class="text-muted-foreground"> · {{ vehicle.color }}</span></span>
                                <ChevronRight class="size-4 text-muted-foreground" aria-hidden="true" />
                            </Link>
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="confirmDelete"
            :title="$t('customers.delete_confirm_title')"
            :description="$t('customers.delete_confirm_description', { name: customer.name })"
            :confirm-label="$t('common.delete')"
            destructive
            :processing="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
