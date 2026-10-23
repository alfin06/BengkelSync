<script setup lang="ts" generic="T extends Record<string, unknown>">
import EmptyState from '@/Components/ui/EmptyState.vue';
import type { DataListColumn } from '@/types/ui';

/**
 * A table on tablet/desktop and a list of cards on phones (SPEC 8.1).
 *
 * Slots:
 * - `cell-{key}`: custom content for one column (gets `item`)
 * - `card`: the whole phone card (gets `item`); defaults to label/value rows
 * - `actions`: per-row actions (gets `item`)
 * - `empty`: replaces the default empty state
 */
defineProps<{
    items: T[];
    columns: DataListColumn[];
    rowKey: keyof T & string;
    emptyTitle: string;
    emptyDescription?: string;
}>();
</script>

<template>
    <div>
        <slot v-if="items.length === 0" name="empty">
            <EmptyState :title="emptyTitle" :description="emptyDescription" />
        </slot>

        <template v-else>
            <ul class="divide-y rounded-lg border bg-card md:hidden">
                <li v-for="item in items" :key="String(item[rowKey])" class="p-4">
                    <slot name="card" :item="item">
                        <dl class="grid gap-1 text-sm">
                            <div v-for="column in columns" :key="column.key" class="flex justify-between gap-4">
                                <dt class="text-muted-foreground">{{ column.label }}</dt>
                                <dd :class="['text-right font-medium', column.class]">
                                    <slot :name="`cell-${column.key}`" :item="item">{{ item[column.key] }}</slot>
                                </dd>
                            </div>
                        </dl>
                    </slot>
                    <div v-if="$slots.actions" class="mt-3 flex flex-wrap gap-2">
                        <slot name="actions" :item="item" />
                    </div>
                </li>
            </ul>

            <div class="hidden overflow-x-auto rounded-lg border bg-card md:block">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th v-for="column in columns" :key="column.key" scope="col" :class="['px-4 py-3 font-medium', column.class]">
                                {{ column.label }}
                            </th>
                            <th v-if="$slots.actions" scope="col" class="px-4 py-3"><span class="sr-only">{{ $t('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="item in items" :key="String(item[rowKey])" class="hover:bg-muted/30">
                            <td v-for="column in columns" :key="column.key" :class="['px-4 py-3', column.class]">
                                <slot :name="`cell-${column.key}`" :item="item">{{ item[column.key] }}</slot>
                            </td>
                            <td v-if="$slots.actions" class="px-4 py-2 text-right">
                                <div class="flex justify-end gap-2"><slot name="actions" :item="item" /></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
