<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Printer } from 'lucide-vue-next';
import { onMounted } from 'vue';
import InvoiceDocument, { type InvoiceBranch } from '@/Components/Invoices/InvoiceDocument.vue';
import { Button, buttonVariants } from '@/Components/ui/button';
import type { Invoice } from '@/types/invoices';

/**
 * The invoice on A5 (pilot decision): plain black text and lines, no shading, so it
 * prints well on 2-ply paper with a dot-matrix or laser printer.
 */
defineProps<{
    invoice: Invoice;
    branch: InvoiceBranch;
}>();

const print = (): void => window.print();

onMounted(print);
</script>

<template>
    <Head :title="invoice.number" />

    <div class="min-h-screen bg-muted/40 print:bg-white">
        <div class="mx-auto flex max-w-[148mm] items-center justify-between gap-2 px-4 py-3 print:hidden">
            <Link :href="route('invoices.show', invoice.id)" :class="buttonVariants({ variant: 'ghost' })"><ArrowLeft aria-hidden="true" /> {{ $t('common.back') }}</Link>
            <Button @click="print"><Printer aria-hidden="true" /> {{ $t('invoices.print') }}</Button>
        </div>

        <InvoiceDocument :invoice="invoice" :branch="branch" />
    </div>
</template>

<style>
@media print {
    @page {
        size: A5 portrait;
        margin: 8mm;
    }
}
</style>
