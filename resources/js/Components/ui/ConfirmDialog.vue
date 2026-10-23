<script setup lang="ts">
import { trans } from 'laravel-vue-i18n';
import { computed, ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import Textarea from '@/Components/ui/Textarea.vue';

/**
 * Confirmation for destructive or financial actions (SPEC 8.1 "Safe actions").
 * Say exactly what will happen in `description`. With `requireReason` the confirm
 * button stays disabled until a reason is typed; the reason is emitted with `confirm`.
 */
const props = withDefaults(
    defineProps<{
        title: string;
        description: string;
        confirmLabel?: string;
        destructive?: boolean;
        requireReason?: boolean;
        processing?: boolean;
    }>(),
    { confirmLabel: undefined, destructive: false, requireReason: false, processing: false },
);

const emit = defineEmits<{ confirm: [reason: string | null] }>();
const open = defineModel<boolean>('open', { default: false });
const reason = ref('');

const canConfirm = computed(() => !props.requireReason || reason.value.trim().length > 0);

watch(open, (isOpen) => {
    if (isOpen) {
        reason.value = '';
    }
});

function confirm(): void {
    if (canConfirm.value) {
        emit('confirm', props.requireReason ? reason.value.trim() : null);
    }
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent role="alertdialog">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription>{{ description }}</DialogDescription>
            </DialogHeader>

            <FormField v-if="requireReason" id="confirm-reason" :label="trans('common.reason')" required>
                <template #default="{ describedBy }">
                    <Textarea id="confirm-reason" v-model="reason" :aria-describedby="describedBy" rows="3" />
                </template>
            </FormField>

            <DialogFooter>
                <Button variant="outline" :disabled="processing" @click="open = false">
                    {{ trans('common.cancel') }}
                </Button>
                <Button
                    :variant="destructive ? 'destructive' : 'default'"
                    :disabled="!canConfirm"
                    :loading="processing"
                    @click="confirm"
                >
                    {{ confirmLabel ?? trans('common.confirm') }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
