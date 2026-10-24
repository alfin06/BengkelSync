<script setup lang="ts">
import axios from 'axios';
import { useDebounceFn } from '@vueuse/core';
import { Plus, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import type { PartOption } from '@/types/purchases';

/**
 * Find a part by name or SKU and pick it. Parts already in the form are shown but
 * cannot be picked again.
 */
defineProps<{ id: string; pickedIds: number[]; invalid?: boolean; describedBy?: string }>();
const emit = defineEmits<{ pick: [part: PartOption] }>();

const term = ref('');
const results = ref<PartOption[]>([]);
const loading = ref(false);
const searched = ref(false);

const search = useDebounceFn(async () => {
    if (term.value.trim().length < 2) {
        results.value = [];
        searched.value = false;

        return;
    }

    loading.value = true;
    try {
        const { data } = await axios.get<PartOption[]>(route('parts.lookup'), { params: { q: term.value } });
        results.value = data;
        searched.value = true;
    } finally {
        loading.value = false;
    }
}, 300);

function pick(part: PartOption): void {
    emit('pick', part);
    term.value = '';
    results.value = [];
    searched.value = false;
}
</script>

<template>
    <div class="grid gap-2">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <input
                :id="id"
                v-model="term"
                type="search"
                autocomplete="off"
                :placeholder="$t('purchases.search_part')"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                :class="[
                    'h-11 w-full rounded-md border border-input bg-background pl-9 pr-3 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-10 sm:text-sm',
                    invalid && 'border-destructive',
                ]"
                @input="search"
            />
        </div>

        <p v-if="loading" class="text-sm text-muted-foreground" role="status">{{ $t('common.searching') }}</p>
        <p v-else-if="searched && results.length === 0" class="text-sm text-muted-foreground" role="status">{{ $t('purchases.no_part_found') }}</p>

        <ul v-if="results.length" class="max-h-72 divide-y overflow-y-auto rounded-md border bg-card">
            <li v-for="part in results" :key="part.id">
                <button
                    type="button"
                    :disabled="pickedIds.includes(part.id)"
                    class="flex min-h-12 w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50"
                    @click="pick(part)"
                >
                    <span class="min-w-0 flex-1">
                        <span class="block font-medium">{{ part.name }}</span>
                        <span class="block text-xs text-muted-foreground">
                            <span v-if="part.sku" class="font-mono">{{ part.sku }} · </span>{{ part.unit_label }}
                            <template v-if="pickedIds.includes(part.id)"> · {{ $t('purchases.already_added') }}</template>
                        </span>
                    </span>
                    <Plus class="size-4 text-primary" aria-hidden="true" />
                </button>
            </li>
        </ul>
    </div>
</template>
