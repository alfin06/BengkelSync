<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import FormField from '@/Components/ui/FormField.vue';
import { Input } from '@/Components/ui/input';
import MoneyInput from '@/Components/ui/MoneyInput.vue';
import { percentOf } from '@/lib/invoice';
import { formatRupiah } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { Invoice, PaymentMethodOption } from '@/types/invoices';

interface CheckoutLine {
    key: string;
    method_id: number | null;
    amount: number | null;
    cash_tendered: number | null;
    reference: string;
}

/**
 * "Bayar" (SPEC 8.1): one checkout, one kwitansi, any mix of methods. Opens with the whole
 * outstanding amount on the cash line. Surcharges and change shown here are a preview;
 * the server calculates the real ones.
 */
const props = defineProps<{
    invoice: Pick<Invoice, 'id' | 'number' | 'outstanding' | 'outstanding_label'>;
    methods: PaymentMethodOption[];
    today: string;
    suggestedDueDate: string;
}>();
const open = defineModel<boolean>('open', { default: false });

const lines = ref<CheckoutLine[]>([]);
const form = useForm({ paid_on: '', due_date: '', note: '' });
const key = (): string => `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
const defaultMethod = computed(() => props.methods.find((method) => method.type === 'cash') ?? props.methods[0]);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    lines.value = [{ key: key(), method_id: defaultMethod.value?.id ?? null, amount: props.invoice.outstanding, cash_tendered: null, reference: '' }];
    form.defaults({ paid_on: props.today, due_date: props.suggestedDueDate, note: '' });
    form.reset();
    form.clearErrors();
});

const methodOf = (line: CheckoutLine): PaymentMethodOption | undefined => props.methods.find((method) => method.id === line.method_id);
const surchargeOf = (line: CheckoutLine): number => percentOf(line.amount ?? 0, methodOf(line)?.surcharge_percent ?? '0');
const changeOf = (line: CheckoutLine): number => (line.cash_tendered ?? 0) - (line.amount ?? 0) - surchargeOf(line);

const entered = computed(() => lines.value.reduce((sum, line) => sum + (line.amount ?? 0), 0));
const surcharge = computed(() => lines.value.reduce((sum, line) => sum + surchargeOf(line), 0));
const remaining = computed(() => props.invoice.outstanding - entered.value);
const tooMuch = computed(() => remaining.value < 0);

function addLine(): void {
    const used = lines.value.map((line) => line.method_id);
    const next = props.methods.find((method) => !used.includes(method.id)) ?? props.methods[0];
    lines.value = [...lines.value, { key: key(), method_id: next?.id ?? null, amount: Math.max(remaining.value, 0) || null, cash_tendered: null, reference: '' }];
}

function removeLine(index: number): void {
    lines.value = lines.value.filter((_, i) => i !== index);
}

function submit(): void {
    form.transform((data) => ({
        paid_on: data.paid_on,
        due_date: remaining.value > 0 ? data.due_date : null,
        note: data.note,
        lines: lines.value.map((line) => ({
            payment_method_id: line.method_id,
            amount: line.amount,
            cash_tendered: methodOf(line)?.type === 'cash' ? line.cash_tendered : null,
            reference: line.reference,
        })),
    }));
    form.post(route('invoices.payments.store', props.invoice.id), { preserveScroll: true, onSuccess: () => (open.value = false) });
}

const errors = computed(() => form.errors as Partial<Record<string, string>>);
const error = (index: number, field: string): string | undefined => errors.value[`lines.${index}.${field}`];
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-xl">
            <form class="grid gap-4" novalidate @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('payments.title', { number: invoice.number }) }}</DialogTitle>
                    <DialogDescription>{{ $t('payments.owed', { amount: invoice.outstanding_label }) }}</DialogDescription>
                </DialogHeader>

                <ol class="grid gap-3">
                    <li v-for="(line, index) in lines" :key="line.key" class="grid gap-3 rounded-md border p-3">
                        <div class="flex items-start gap-2">
                            <div class="grid flex-1 grid-cols-2 gap-2 sm:grid-cols-3" role="radiogroup" :aria-label="$t('payments.method')">
                                <button
                                    v-for="method in methods"
                                    :key="method.id"
                                    type="button"
                                    role="radio"
                                    :aria-checked="line.method_id === method.id"
                                    :class="
                                        cn(
                                            'min-h-11 rounded-md border px-2 text-sm font-medium',
                                            line.method_id === method.id ? 'border-primary bg-primary/10 text-primary' : 'hover:bg-muted',
                                        )
                                    "
                                    @click="line.method_id = method.id"
                                >
                                    {{ method.name }}
                                </button>
                            </div>
                            <Button v-if="lines.length > 1" type="button" variant="ghost" size="icon" :aria-label="$t('payments.remove_line')" @click="removeLine(index)">
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </div>
                        <p v-if="error(index, 'payment_method_id')" class="text-sm font-medium text-destructive" role="alert">{{ error(index, 'payment_method_id') }}</p>

                        <div class="grid grid-cols-2 gap-3">
                            <FormField :id="`pay-amount-${index}`" :label="$t('payments.amount')" :error="error(index, 'amount')" required>
                                <template #default="{ invalid, describedBy }">
                                    <MoneyInput :id="`pay-amount-${index}`" v-model="line.amount" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>

                            <FormField v-if="methodOf(line)?.type === 'cash'" :id="`pay-tendered-${index}`" :label="$t('payments.tendered')" :error="error(index, 'cash_tendered')">
                                <template #default="{ invalid, describedBy }">
                                    <MoneyInput :id="`pay-tendered-${index}`" v-model="line.cash_tendered" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>

                            <FormField
                                v-else-if="methodOf(line)?.needs_reference || methodOf(line)?.type === 'transfer'"
                                :id="`pay-reference-${index}`"
                                :label="$t('payments.reference')"
                                :error="error(index, 'reference')"
                                :required="methodOf(line)?.needs_reference"
                            >
                                <template #default="{ invalid, describedBy }">
                                    <Input :id="`pay-reference-${index}`" v-model="line.reference" autocomplete="off" :invalid="invalid" :aria-describedby="describedBy" />
                                </template>
                            </FormField>
                        </div>

                        <p v-if="surchargeOf(line) > 0" class="text-sm text-muted-foreground">
                            {{ $t('payments.surcharge_line', { percent: `${Number(methodOf(line)?.surcharge_percent)}%`, amount: formatRupiah(surchargeOf(line)) }) }}
                        </p>
                        <p v-if="methodOf(line)?.type === 'cash' && (line.cash_tendered ?? 0) > 0" :class="cn('text-sm font-medium', changeOf(line) < 0 ? 'text-destructive' : 'text-green-700')">
                            {{ changeOf(line) < 0 ? $t('payments.tendered_short', { amount: formatRupiah(-changeOf(line)) }) : $t('payments.change', { amount: formatRupiah(changeOf(line)) }) }}
                        </p>
                    </li>
                </ol>

                <Button v-if="remaining > 0 && lines.length < methods.length" type="button" variant="outline" size="sm" class="justify-self-start" @click="addLine">
                    <Plus aria-hidden="true" /> {{ $t('payments.add_method') }}
                </Button>

                <div class="grid grid-cols-2 gap-3">
                    <FormField id="paid_on" :label="$t('payments.paid_on')" :error="form.errors.paid_on" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="paid_on" v-model="form.paid_on" type="date" :max="today" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                    <FormField v-if="remaining > 0" id="pay-due-date" :label="$t('payments.due_date')" :hint="$t('payments.due_date_hint')" :error="form.errors.due_date" required>
                        <template #default="{ invalid, describedBy }">
                            <Input id="pay-due-date" v-model="form.due_date" type="date" :min="form.paid_on" :invalid="invalid" :aria-describedby="describedBy" />
                        </template>
                    </FormField>
                </div>

                <!-- Summary, always visible above the buttons. -->
                <div class="sticky bottom-0 -mx-5 grid gap-1 border-t bg-background px-5 pt-3 text-sm">
                    <div class="flex justify-between"><span>{{ $t('payments.owed_label') }}</span><span class="tabular-nums">{{ invoice.outstanding_label }}</span></div>
                    <div class="flex justify-between"><span>{{ $t('payments.entered') }}</span><span class="tabular-nums">{{ formatRupiah(entered) }}</span></div>
                    <div v-if="surcharge > 0" class="flex justify-between text-muted-foreground"><span>{{ $t('payments.surcharge') }}</span><span class="tabular-nums">{{ formatRupiah(surcharge) }}</span></div>
                    <div :class="cn('flex justify-between font-semibold', tooMuch ? 'text-destructive' : remaining > 0 ? 'text-amber-700' : 'text-green-700')">
                        <span>{{ tooMuch ? $t('payments.too_much') : $t('payments.remaining') }}</span>
                        <span class="tabular-nums">{{ formatRupiah(Math.abs(remaining)) }}</span>
                    </div>

                    <div class="mt-2 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :loading="form.processing" :disabled="entered <= 0 || tooMuch">
                            {{ $t('payments.save', { amount: formatRupiah(entered + surcharge) }) }}
                        </Button>
                    </div>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
