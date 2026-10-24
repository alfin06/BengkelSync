<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowLeft, History, Lock } from 'lucide-vue-next';
import { buttonVariants } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { cn } from '@/lib/utils';

interface Change {
    change: 'added' | 'removed' | 'changed';
    description: string;
    before: string | null;
    after: string | null;
}

interface Revision {
    id: number;
    /** The version this edit produced ("Revisi 1", ...) */
    version: number;
    reason: string;
    /** Changed after the invoice was locked (by someone allowed to) */
    after_lock: boolean;
    edited_by: string | null;
    edited_at_label: string | null;
    total_before: string;
    total_after: string;
    changes: { lines: Change[]; totals: { field: string; before: string; after: string }[] };
}

/**
 * Every edit of an invoice, newest first: who, when, why, and what changed (SPEC 6).
 */
defineProps<{
    invoice: { id: number; number: string; revision_no: number };
    history: Revision[];
}>();

const tone = { added: 'text-green-700', removed: 'text-destructive', changed: 'text-amber-700' } as const;
</script>

<template>
    <AppLayout>
        <PageHeader :title="$t('invoices.revisions_title', { number: invoice.number })">
            <template #actions>
                <Link :href="route('invoices.show', invoice.id)" :class="buttonVariants({ variant: 'outline' })"><ArrowLeft aria-hidden="true" /> {{ $t('common.back') }}</Link>
            </template>
        </PageHeader>

        <EmptyState v-if="history.length === 0" :icon="History" :title="$t('invoices.no_revisions')" />

        <div v-else class="grid max-w-3xl gap-4">
            <Card v-for="revision in history" :key="revision.id">
                <CardHeader>
                    <CardTitle>{{ $t('invoices.revision', { no: String(revision.version) }) }}</CardTitle>
                    <p class="text-sm text-muted-foreground">{{ revision.edited_at_label }}<template v-if="revision.edited_by"> · {{ revision.edited_by }}</template></p>
                    <p v-if="revision.after_lock" class="flex items-center gap-1 text-sm font-medium text-amber-700"><Lock class="size-3.5" aria-hidden="true" /> {{ $t('shop_settings.edited_after_lock') }}</p>
                </CardHeader>
                <CardContent class="grid gap-3 text-sm">
                    <p><span class="font-medium">{{ $t('common.reason') }}:</span> {{ revision.reason }}</p>

                    <ul v-if="revision.changes.lines.length" class="grid gap-2">
                        <li v-for="(change, index) in revision.changes.lines" :key="index" class="rounded-md border p-2">
                            <p :class="cn('font-medium', tone[change.change])">{{ $t(`invoices.change.${change.change}`) }}: {{ change.description }}</p>
                            <p v-if="change.before" class="text-muted-foreground" :class="change.after && 'line-through'">{{ change.before }}</p>
                            <p v-if="change.after">{{ change.after }}</p>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground">{{ $t('invoices.no_line_changes') }}</p>

                    <dl class="grid gap-1 border-t pt-3">
                        <div v-for="total in revision.changes.totals" :key="total.field" class="flex justify-between gap-2">
                            <dt>{{ $t(`invoices.total_field.${total.field}`) }}</dt>
                            <dd class="tabular-nums"><span class="text-muted-foreground line-through">{{ total.before }}</span> → <span class="font-semibold">{{ total.after }}</span></dd>
                        </div>
                        <div v-if="revision.changes.totals.length === 0" class="flex justify-between gap-2">
                            <dt>{{ $t('invoices.total') }}</dt>
                            <dd class="tabular-nums">{{ revision.total_after }}</dd>
                        </div>
                    </dl>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
