<script setup lang="ts">
import { Plus, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';

/**
 * The checklist typed at check-in: one line per thing to check or do.
 */
defineProps<{ id: string }>();
const tasks = defineModel<string[]>({ required: true });
const draft = ref('');

function add(): void {
    const text = draft.value.trim();
    if (text !== '') {
        tasks.value = [...tasks.value, text];
    }
    draft.value = '';
}

function remove(index: number): void {
    tasks.value = tasks.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="grid gap-2">
        <ul v-if="tasks.length" class="grid gap-1">
            <li v-for="(task, index) in tasks" :key="`${index}-${task}`" class="flex items-center gap-2 rounded-md bg-muted px-3 py-2 text-sm">
                <span class="flex-1">{{ task }}</span>
                <button type="button" class="inline-flex size-9 items-center justify-center rounded-md hover:bg-background" :aria-label="$t('work_orders.remove_task', { task })" @click="remove(index)">
                    <X class="size-4" aria-hidden="true" />
                </button>
            </li>
        </ul>
        <div class="flex gap-2">
            <Input :id="id" v-model="draft" autocomplete="off" :placeholder="$t('work_orders.task_placeholder')" class="flex-1" @keydown.enter.prevent="add" />
            <Button type="button" variant="outline" :aria-label="$t('work_orders.add_task')" @click="add"><Plus aria-hidden="true" /></Button>
        </div>
    </div>
</template>
