<script setup lang="ts">
import { MessageCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import Textarea from '@/Components/ui/Textarea.vue';

/**
 * Send an invoice or kwitansi to the customer: opens WhatsApp (app or web) on this
 * device with the number and message filled in; staff press send there. Both can be
 * changed first, e.g. for "Pelanggan umum" or another number of the customer.
 */
const props = defineProps<{
    /** Customer number as 62812…, or null */
    phone: string | null;
    message: string;
}>();
const open = defineModel<boolean>('open', { default: false });

const number = ref('');
const text = ref('');
const invalid = ref(false);

watch(open, (isOpen) => {
    if (isOpen) {
        // Show the familiar 0812… form.
        number.value = props.phone ? props.phone.replace(/^62/, '0') : '';
        text.value = props.message;
        invalid.value = false;
    }
});

/** 0812…, +62 812…, 812… → 62812…; null when it cannot be an Indonesian mobile number. */
function waNumber(input: string): string | null {
    let digits = input.replace(/\D/g, '');
    if (digits.startsWith('0')) {
        digits = `62${digits.slice(1)}`;
    } else if (digits.startsWith('8')) {
        digits = `62${digits}`;
    }

    return /^62\d{8,13}$/.test(digits) ? digits : null;
}

function send(): void {
    const target = waNumber(number.value);
    invalid.value = target === null;
    if (target === null) {
        return;
    }

    window.open(`https://wa.me/${target}?text=${encodeURIComponent(text.value)}`, '_blank', 'noopener');
    open.value = false;
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg">
            <form class="grid gap-4" novalidate @submit.prevent="send">
                <DialogHeader>
                    <DialogTitle>{{ $t('whatsapp.dialog_title') }}</DialogTitle>
                    <DialogDescription>{{ $t('whatsapp.dialog_description') }}</DialogDescription>
                </DialogHeader>

                <FormField id="wa-number" :label="$t('whatsapp.phone')" :hint="$t('whatsapp.phone_hint')" :error="invalid ? $t('whatsapp.phone_invalid') : undefined" required>
                    <template #default="{ invalid: hasError, describedBy }">
                        <Input id="wa-number" v-model="number" type="tel" inputmode="tel" autocomplete="off" :invalid="hasError" :aria-describedby="describedBy" @input="invalid = false" />
                    </template>
                </FormField>

                <FormField id="wa-message" :label="$t('whatsapp.message')" :hint="$t('whatsapp.link_note')">
                    <template #default="{ describedBy }">
                        <Textarea id="wa-message" v-model="text" rows="10" class="text-sm" :aria-describedby="describedBy" />
                    </template>
                </FormField>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                    <Button type="submit"><MessageCircle aria-hidden="true" /> {{ $t('whatsapp.open') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
