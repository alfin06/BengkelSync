<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { ClipboardCheck, History, MapPin, Pencil, SlidersHorizontal, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import StockBadge from '@/Components/Parts/StockBadge.vue';
import Badge from '@/Components/ui/Badge.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import QuantityInput from '@/Components/ui/QuantityInput.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import type { Part, StockMovement } from '@/types/inventory';

const props = defineProps<{
    part: Part;
    movements: Paginated<StockMovement>;
    can: { update: boolean; delete: boolean; adjustStock: boolean; configureStock: boolean };
}>();

// Stock opname: type what is on the shelf, with a reason.
const adjustOpen = ref(false);
const adjustForm = useForm({ counted: '', reason: '' });

/** "24.5" → "24,5": prefill inputs the way Indonesian staff type decimals. */
const asInput = (value: string): string => value.replace('.', ',');

function openAdjust(): void {
    adjustForm.defaults({ counted: asInput(props.part.stock.qty), reason: '' });
    adjustForm.reset();
    adjustForm.clearErrors();
    adjustOpen.value = true;
}

function submitAdjust(): void {
    adjustForm.post(route('parts.stock.adjust', props.part.id), { preserveScroll: true, onSuccess: () => (adjustOpen.value = false) });
}

// Low-stock threshold and shelf location for this branch.
const configureOpen = ref(false);
const configureForm = useForm({ min_stock: '', location: '' });

function openConfigure(): void {
    configureForm.defaults({ min_stock: asInput(props.part.stock.min_stock), location: props.part.stock.location ?? '' });
    configureForm.reset();
    configureForm.clearErrors();
    configureOpen.value = true;
}

function submitConfigure(): void {
    configureForm.put(route('parts.stock.configure', props.part.id), { preserveScroll: true, onSuccess: () => (configureOpen.value = false) });
}

const confirmDelete = ref(false);
const deleting = ref(false);

function destroy(): void {
    router.delete(route('parts.destroy', props.part.id), {
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
        <PageHeader :title="part.name" :description="[part.sku, part.brand, part.category].filter(Boolean).join(' · ') || undefined">
            <template v-if="can.update || can.delete" #actions>
                <Link v-if="can.update" :href="route('parts.edit', part.id)" :class="buttonVariants({ variant: 'outline' })">
                    <Pencil aria-hidden="true" /> {{ $t('common.edit') }}
                </Link>
                <Button v-if="can.delete" variant="outline" class="text-destructive" @click="confirmDelete = true">
                    <Trash2 aria-hidden="true" /> {{ $t('common.delete') }}
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)]">
            <div class="grid content-start gap-6">
                <Card>
                    <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                        <CardTitle>{{ $t('parts.stock_in_branch') }}</CardTitle>
                        <Badge v-if="!part.is_active">{{ $t('parts.status.inactive') }}</Badge>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <p class="text-2xl"><StockBadge :part="part" /></p>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">{{ $t('parts.fields.min_stock') }}</dt>
                                <dd class="font-medium">{{ part.stock.min_label }} {{ part.unit_label }}</dd>
                            </div>
                            <div>
                                <dt class="text-muted-foreground">{{ $t('parts.fields.location') }}</dt>
                                <dd class="flex items-center gap-1 font-medium"><MapPin class="size-4 text-muted-foreground" aria-hidden="true" /> {{ part.stock.location ?? '–' }}</dd>
                            </div>
                        </dl>
                        <div v-if="can.adjustStock || can.configureStock" class="flex flex-wrap gap-2">
                            <Button v-if="can.adjustStock" variant="outline" @click="openAdjust"><ClipboardCheck aria-hidden="true" /> {{ $t('parts.adjust_stock') }}</Button>
                            <Button v-if="can.configureStock" variant="outline" @click="openConfigure"><SlidersHorizontal aria-hidden="true" /> {{ $t('parts.configure_stock') }}</Button>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>{{ $t('parts.prices') }}</CardTitle></CardHeader>
                    <CardContent>
                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <dt class="text-muted-foreground">{{ $t('parts.fields.sell_price') }}</dt>
                                <dd class="text-lg font-semibold tabular-nums">{{ part.price_label }}<span class="text-sm font-normal text-muted-foreground"> / {{ part.unit_label }}</span></dd>
                            </div>
                            <div v-if="part.cost_label">
                                <dt class="text-muted-foreground">{{ $t('parts.fields.cost_price') }}</dt>
                                <dd class="text-lg font-semibold tabular-nums">{{ part.cost_label }}</dd>
                            </div>
                        </dl>
                        <p v-if="part.notes" class="mt-3 whitespace-pre-line text-sm">{{ part.notes }}</p>
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader><CardTitle>{{ $t('parts.history') }}</CardTitle></CardHeader>
                <CardContent>
                    <EmptyState v-if="movements.data.length === 0" :icon="History" :title="$t('parts.history_empty')" />
                    <ol v-else class="divide-y rounded-md border">
                        <li v-for="movement in movements.data" :key="movement.id" class="flex items-start gap-3 p-3 text-sm">
                            <span class="min-w-0 flex-1">
                                <span class="block font-medium">{{ movement.type_label }}</span>
                                <span v-if="movement.note" class="block">{{ movement.note }}</span>
                                <span class="block text-xs text-muted-foreground">{{ movement.created_at_label }}<template v-if="movement.user"> · {{ movement.user }}</template></span>
                            </span>
                            <span class="text-right tabular-nums">
                                <span :class="['block font-semibold', movement.qty < 0 ? 'text-destructive' : 'text-green-700']">{{ movement.qty_label }}</span>
                                <span class="block text-xs text-muted-foreground">{{ $t('parts.balance', { qty: movement.qty_after_label }) }}</span>
                            </span>
                        </li>
                    </ol>
                    <div class="mt-4"><Pagination :meta="movements" /></div>
                </CardContent>
            </Card>
        </div>

        <Dialog v-model:open="adjustOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="submitAdjust">
                    <DialogHeader>
                        <DialogTitle>{{ $t('parts.adjust_stock') }}</DialogTitle>
                        <DialogDescription>{{ $t('parts.adjust_description', { qty: part.stock.qty_label, unit: part.unit_label }) }}</DialogDescription>
                    </DialogHeader>
                    <FormField id="counted" :label="$t('parts.fields.counted')" :error="adjustForm.errors.counted" required>
                        <template #default="{ invalid, describedBy }">
                            <QuantityInput id="counted" v-model="adjustForm.counted" :allow-decimals="part.allows_decimals" :unit="part.unit_label" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="reason" :label="$t('common.reason')" :error="adjustForm.errors.reason" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="reason" v-model="adjustForm.reason" :placeholder="$t('parts.reason_placeholder')" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="adjustOpen = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="adjustForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="configureOpen">
            <DialogContent>
                <form class="grid gap-4" novalidate @submit.prevent="submitConfigure">
                    <DialogHeader>
                        <DialogTitle>{{ $t('parts.configure_stock') }}</DialogTitle>
                        <DialogDescription>{{ $t('parts.min_stock_hint') }}</DialogDescription>
                    </DialogHeader>
                    <FormField id="min_stock" :label="$t('parts.fields.min_stock')" :error="configureForm.errors.min_stock" required>
                        <template #default="{ invalid, describedBy }">
                            <QuantityInput id="min_stock" v-model="configureForm.min_stock" :allow-decimals="part.allows_decimals" :unit="part.unit_label" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField id="location" :label="$t('parts.fields.location')" :error="configureForm.errors.location">
                        <template #default="{ invalid, describedBy }">
                            <Input id="location" v-model="configureForm.location" placeholder="Rak A3" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <DialogFooter>
                        <Button type="button" variant="outline" @click="configureOpen = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="configureForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            v-model:open="confirmDelete"
            :title="$t('parts.delete_confirm_title')"
            :description="$t('parts.delete_confirm_description', { name: part.name })"
            :confirm-label="$t('common.delete')"
            destructive
            :processing="deleting"
            @confirm="destroy"
        />
    </AppLayout>
</template>
