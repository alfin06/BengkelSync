<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Camera, ShieldCheck, X } from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import Checkbox from '@/Components/ui/Checkbox.vue';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import Textarea from '@/Components/ui/Textarea.vue';
import type { Mechanic } from '@/types/mechanics';

/**
 * Add or edit a mechanic. NIK and KTP fields are only shown to users with
 * mechanic.view_identity; the server rejects them from anyone else.
 */
const props = defineProps<{
    /** null = add a new mechanic */
    mechanic: Mechanic | null;
    canManageIdentity: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });

const form = useForm<{
    name: string;
    phone: string;
    is_active: boolean;
    notes: string;
    nik: string;
    ktp: File | null;
    remove_ktp: boolean;
}>({
    name: '',
    phone: '',
    is_active: true,
    notes: '',
    nik: '',
    ktp: null,
    remove_ktp: false,
});

const preview = ref<string | null>(null);

function clearPreview(): void {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
    preview.value = null;
}

watch(open, (isOpen) => {
    if (!isOpen) {
        clearPreview();

        return;
    }

    form.clearErrors();
    form.defaults({
        name: props.mechanic?.name ?? '',
        phone: props.mechanic?.phone ?? '',
        is_active: props.mechanic?.is_active ?? true,
        notes: props.mechanic?.notes ?? '',
        nik: props.mechanic?.nik ?? '',
        ktp: null,
        remove_ktp: false,
    });
    form.reset();
});

onBeforeUnmount(clearPreview);

function chooseKtp(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    clearPreview();
    form.ktp = file;
    form.remove_ktp = false;
    preview.value = file ? URL.createObjectURL(file) : null;
}

const currentKtp = computed(() => (form.remove_ktp ? null : (preview.value ?? props.mechanic?.ktp_url ?? null)));

function removeKtp(): void {
    clearPreview();
    form.ktp = null;
    form.remove_ktp = Boolean(props.mechanic?.has_ktp);
}

function submit(): void {
    // Identity fields are left out entirely for users who may not manage them.
    form.transform((data) => {
        const payload: Record<string, unknown> = { name: data.name, phone: data.phone, is_active: data.is_active, notes: data.notes };

        if (props.canManageIdentity) {
            payload.nik = data.nik;
            payload.remove_ktp = data.remove_ktp;
            if (data.ktp) {
                payload.ktp = data.ktp;
            }
        }

        // Files need multipart, which only works with POST: spoof PUT for updates.
        return props.mechanic ? { ...payload, _method: 'put' } : payload;
    });

    const url = props.mechanic ? route('mechanics.update', props.mechanic.id) : route('mechanics.store');

    form.post(url, { preserveScroll: true, forceFormData: true, onSuccess: () => (open.value = false) });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent>
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ mechanic ? $t('mechanics.edit_title') : $t('mechanics.create_title') }}</DialogTitle>
                </DialogHeader>

                <FormField id="mechanic-name" :label="$t('mechanics.fields.name')" :error="form.errors.name" required>
                    <template #default="{ invalid, describedBy }">
                        <Input id="mechanic-name" v-model="form.name" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <FormField id="mechanic-phone" :label="$t('fields.phone')" :hint="$t('fields.phone_hint')" :error="form.errors.phone">
                    <template #default="{ invalid, describedBy }">
                        <Input id="mechanic-phone" v-model="form.phone" type="tel" inputmode="tel" autocomplete="off" placeholder="0812-3456-7890" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <fieldset v-if="canManageIdentity" class="grid gap-4 rounded-md border p-3">
                    <legend class="flex items-center gap-1.5 px-1 text-sm font-medium">
                        <ShieldCheck class="size-4 text-primary" aria-hidden="true" /> {{ $t('mechanics.identity') }}
                    </legend>

                    <FormField id="mechanic-nik" :label="$t('mechanics.fields.nik')" :hint="$t('mechanics.nik_hint')" :error="form.errors.nik">
                        <template #default="{ invalid, describedBy }">
                            <Input
                                id="mechanic-nik"
                                v-model="form.nik"
                                inputmode="numeric"
                                autocomplete="off"
                                maxlength="20"
                                class="font-mono tracking-wider"
                                :invalid="invalid"
                                :aria-describedby="describedBy"
                            />
                        </template>
                    </FormField>

                    <FormField id="mechanic-ktp" :label="$t('mechanics.fields.ktp')" :hint="$t('mechanics.ktp_hint')" :error="form.errors.ktp">
                        <template #default="{ describedBy }">
                            <div v-if="currentKtp" class="relative w-fit">
                                <img :src="currentKtp" :alt="$t('mechanics.fields.ktp')" class="max-h-48 rounded-md border object-contain" />
                                <Button
                                    type="button"
                                    variant="secondary"
                                    size="icon"
                                    class="absolute right-1 top-1"
                                    :aria-label="$t('mechanics.remove_ktp')"
                                    @click="removeKtp"
                                >
                                    <X aria-hidden="true" />
                                </Button>
                            </div>
                            <label
                                for="mechanic-ktp"
                                class="flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground hover:bg-muted/50"
                            >
                                <Camera class="size-4" aria-hidden="true" />
                                {{ currentKtp ? $t('mechanics.replace_ktp') : $t('mechanics.choose_ktp') }}
                            </label>
                            <input
                                id="mechanic-ktp"
                                type="file"
                                accept="image/jpeg,image/png,image/webp"
                                class="sr-only"
                                :aria-describedby="describedBy"
                                @change="chooseKtp"
                            />
                        </template>
                    </FormField>
                </fieldset>

                <FormField id="mechanic-notes" :label="$t('mechanics.fields.notes')" :error="form.errors.notes">
                    <template #default="{ invalid, describedBy }">
                        <Textarea id="mechanic-notes" v-model="form.notes" rows="2" :invalid="invalid" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <Checkbox v-if="mechanic" id="mechanic-active" v-model="form.is_active" :label="$t('mechanics.fields.is_active')" />

                <p v-if="form.progress" class="text-sm text-muted-foreground" role="status">
                    {{ $t('mechanics.uploading', { percent: String(form.progress.percentage ?? 0) }) }}
                </p>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit" :loading="form.processing">{{ $t('common.save') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
