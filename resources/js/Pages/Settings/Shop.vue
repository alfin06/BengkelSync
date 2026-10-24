<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import { Lock, Pencil, Plus, Receipt, RotateCcw, Store, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import Badge from '@/Components/ui/Badge.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';

/**
 * "Toko & cabang": the shop's name and invoice lock period (Owner), and the branches:
 * add and delete (Owner), edit (branch managers, their own branches).
 */
interface BranchRow {
    id: number;
    name: string;
    code: string;
    address: string | null;
    phone: string | null;
    is_active: boolean;
}

const props = defineProps<{
    shop: { name: string; invoice_lock_days: number | null } | null;
    branchList: BranchRow[];
    deletedBranches: BranchRow[];
    can: { updateShop: boolean; updateBranch: boolean; createBranch: boolean; deleteBranch: boolean; taxSettings: boolean };
}>();

const shopForm = useForm({ name: props.shop?.name ?? '', invoice_lock_days: props.shop?.invoice_lock_days === null || props.shop === null ? '' : String(props.shop.invoice_lock_days) });

function saveShop(): void {
    shopForm
        .transform((data) => ({ ...data, invoice_lock_days: data.invoice_lock_days === '' ? null : Number(data.invoice_lock_days) }))
        .put(route('settings.shop.update'), { preserveScroll: true, onSuccess: () => shopForm.defaults() });
}

const deleting = ref<BranchRow | null>(null);
const deleteOpen = ref(false);
const busy = ref(false);

function askDelete(branch: BranchRow): void {
    deleting.value = branch;
    deleteOpen.value = true;
}

function deleteBranch(): void {
    if (deleting.value === null) {
        return;
    }

    busy.value = true;
    router.delete(route('settings.branches.destroy', deleting.value.id), {
        preserveScroll: true,
        onSuccess: () => (deleteOpen.value = false),
        onFinish: () => (busy.value = false),
    });
}

function restoreBranch(branch: BranchRow): void {
    router.post(route('settings.branches.restore', branch.id), {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('shop_settings.title')" :description="$t('shop_settings.description')">
            <template v-if="can.taxSettings" #actions>
                <Link :href="route('settings.invoice.edit')" :class="buttonVariants({ variant: 'outline' })"><Receipt aria-hidden="true" /> {{ $t('nav.invoice_settings') }}</Link>
            </template>
        </PageHeader>

        <div class="grid max-w-2xl gap-6">
            <Card v-if="shop">
                <CardHeader><CardTitle>{{ $t('shop_settings.shop') }}</CardTitle></CardHeader>
                <CardContent>
                    <form class="grid gap-5" novalidate @submit.prevent="saveShop">
                        <FormField id="shop-name" :label="$t('shop_settings.shop_name')" :hint="$t('shop_settings.shop_name_hint')" :error="shopForm.errors.name" required>
                            <template #default="{ invalid, describedBy }">
                                <Input id="shop-name" v-model="shopForm.name" :disabled="!can.updateShop" :invalid="invalid" :aria-describedby="describedBy" />
                            </template>
                        </FormField>

                        <FormField id="lock-days" :label="$t('shop_settings.lock_days')" :hint="$t('shop_settings.lock_days_hint')" :error="shopForm.errors.invoice_lock_days">
                            <template #default="{ invalid, describedBy }">
                                <div class="flex items-center gap-2">
                                    <Lock class="size-4 text-muted-foreground" aria-hidden="true" />
                                    <Input
                                        id="lock-days"
                                        v-model="shopForm.invoice_lock_days"
                                        inputmode="numeric"
                                        class="w-24 text-right"
                                        :placeholder="$t('shop_settings.no_lock')"
                                        :disabled="!can.updateShop"
                                        :invalid="invalid"
                                        :aria-describedby="describedBy"
                                    />
                                    <span class="text-sm text-muted-foreground">{{ $t('shop_settings.days') }}</span>
                                </div>
                            </template>
                        </FormField>

                        <div v-if="can.updateShop" class="flex justify-end">
                            <Button type="submit" :loading="shopForm.processing" :disabled="!shopForm.isDirty">{{ $t('common.save') }}</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card v-if="can.updateBranch">
                <CardHeader class="flex-row items-center justify-between gap-2 space-y-0">
                    <CardTitle>{{ $t('branches.title') }}</CardTitle>
                    <Link v-if="can.createBranch" :href="route('settings.branches.create')" :class="buttonVariants({ size: 'sm' })">
                        <Plus aria-hidden="true" /> {{ $t('branches.add') }}
                    </Link>
                </CardHeader>
                <CardContent>
                    <ul class="divide-y">
                        <li v-for="item in branchList" :key="item.id" class="flex items-start gap-3 py-3 first:pt-0 last:pb-0">
                            <Store class="mt-0.5 size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ item.name }}</span>
                                    <Badge>{{ item.code }}</Badge>
                                    <Badge v-if="item.is_active" tone="success">{{ $t('branches.current') }}</Badge>
                                </div>
                                <p v-if="item.address || item.phone" class="truncate text-sm text-muted-foreground">
                                    {{ [item.address, item.phone].filter(Boolean).join(' · ') }}
                                </p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <Link
                                    :href="route('settings.branches.edit', item.id)"
                                    :class="buttonVariants({ variant: 'ghost', size: 'icon' })"
                                    :aria-label="$t('branches.edit_label', { name: item.name })"
                                >
                                    <Pencil aria-hidden="true" />
                                </Link>
                                <Button
                                    v-if="can.deleteBranch && branchList.length > 1"
                                    variant="ghost"
                                    size="icon"
                                    class="text-destructive"
                                    :aria-label="$t('branches.delete_label', { name: item.name })"
                                    @click="askDelete(item)"
                                >
                                    <Trash2 aria-hidden="true" />
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <div v-if="deletedBranches.length > 0" class="mt-6 border-t pt-4">
                        <h3 class="mb-2 text-sm font-medium text-muted-foreground">{{ $t('branches.deleted_title') }}</h3>
                        <ul class="grid gap-2">
                            <li v-for="item in deletedBranches" :key="item.id" class="flex items-center gap-3">
                                <span class="min-w-0 flex-1 truncate text-sm text-muted-foreground">{{ item.name }} ({{ item.code }})</span>
                                <Button variant="outline" size="sm" @click="restoreBranch(item)"><RotateCcw aria-hidden="true" /> {{ $t('branches.restore') }}</Button>
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>
        </div>

        <ConfirmDialog
            v-model:open="deleteOpen"
            :title="$t('branches.delete_title', { name: deleting?.name ?? '' })"
            :description="$t('branches.delete_description')"
            :confirm-label="$t('common.delete')"
            :processing="busy"
            destructive
            @confirm="deleteBranch"
        />
    </AppLayout>
</template>
