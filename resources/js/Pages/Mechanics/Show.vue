<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Lock, MessageCircle, Pencil, Phone, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import MechanicDialog from '@/Components/Mechanics/MechanicDialog.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Mechanic, MechanicAbilities } from '@/types/mechanics';

const props = defineProps<{ mechanic: Mechanic; can: MechanicAbilities }>();

const editOpen = ref(false);
const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    router.delete(route('mechanics.destroy', props.mechanic.id), {
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
        <PageHeader :title="mechanic.name">
            <template v-if="can.update || can.delete" #actions>
                <Button v-if="can.update" variant="outline" @click="editOpen = true"><Pencil aria-hidden="true" /> {{ $t('common.edit') }}</Button>
                <Button v-if="can.delete" variant="outline" class="text-destructive" @click="confirmDelete = true">
                    <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                    <CardTitle>{{ $t('mechanics.details') }}</CardTitle>
                    <Badge :tone="mechanic.is_active ? 'success' : 'neutral'">
                        {{ mechanic.is_active ? $t('mechanics.status.active') : $t('mechanics.status.inactive') }}
                    </Badge>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="mechanic.phone_e164" class="flex flex-wrap gap-2">
                        <a :href="`tel:${mechanic.phone_e164}`" :class="buttonVariants({ variant: 'outline' })"><Phone aria-hidden="true" /> {{ mechanic.phone }}</a>
                        <a
                            :href="`https://wa.me/${mechanic.phone_e164.replace('+', '')}`"
                            target="_blank"
                            rel="noopener noreferrer"
                            :class="buttonVariants({ variant: 'outline' })"
                        >
                            <MessageCircle aria-hidden="true" /> WhatsApp
                        </a>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">{{ $t('mechanics.no_phone') }}</p>

                    <div v-if="mechanic.notes" class="text-sm">
                        <p class="text-muted-foreground">{{ $t('mechanics.fields.notes') }}</p>
                        <p class="whitespace-pre-line">{{ mechanic.notes }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>{{ $t('mechanics.identity') }}</CardTitle></CardHeader>
                <CardContent class="grid gap-4 text-sm">
                    <div>
                        <p class="text-muted-foreground">{{ $t('mechanics.fields.nik') }}</p>
                        <p class="font-mono tracking-wider">{{ (can.identity ? mechanic.nik : mechanic.nik_masked) ?? '–' }}</p>
                    </div>

                    <div v-if="can.identity">
                        <p class="mb-2 text-muted-foreground">{{ $t('mechanics.fields.ktp') }}</p>
                        <a v-if="mechanic.ktp_url" :href="mechanic.ktp_url" target="_blank" rel="noopener">
                            <img :src="mechanic.ktp_url" :alt="$t('mechanics.fields.ktp')" class="max-h-64 rounded-md border object-contain" />
                        </a>
                        <p v-else>{{ $t('mechanics.no_ktp') }}</p>
                    </div>
                    <p v-else class="flex items-center gap-2 text-muted-foreground">
                        <Lock class="size-4" aria-hidden="true" /> {{ $t('mechanics.identity_hidden') }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <MechanicDialog v-model:open="editOpen" :mechanic="mechanic" :can-manage-identity="can.identity" />

        <ConfirmDialog
            v-model:open="confirmDelete"
            :title="$t('mechanics.delete_confirm_title')"
            :description="$t('mechanics.delete_confirm_description', { name: mechanic.name })"
            :confirm-label="$t('common.delete')"
            destructive
            :processing="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
