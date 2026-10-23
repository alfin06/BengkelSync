<script setup lang="ts">
import axios from 'axios';
import { useDebounceFn } from '@vueuse/core';
import { Check, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { cn } from '@/lib/utils';

interface CustomerOption {
    id: number;
    name: string;
    phone: string | null;
}

/**
 * Find a customer by name, phone or plate (same search as the customer list).
 * Emits the chosen customer id through v-model.
 */
defineProps<{ id: string; excludeId?: number }>();

const selected = defineModel<number | null>({ default: null });
const term = ref('');
const results = ref<CustomerOption[]>([]);
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
        const { data } = await axios.get<CustomerOption[]>(route('customers.lookup.search'), { params: { q: term.value } });
        results.value = data;
        searched.value = true;
    } finally {
        loading.value = false;
    }
}, 300);
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
                :placeholder="$t('vehicles.search_customer')"
                class="h-11 w-full rounded-md border border-input bg-background pl-9 pr-3 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:h-10 sm:text-sm"
                @input="search"
            />
        </div>

        <p v-if="loading" class="text-sm text-muted-foreground" role="status">{{ $t('common.searching') }}</p>
        <p v-else-if="searched && results.length === 0" class="text-sm text-muted-foreground" role="status">{{ $t('vehicles.no_customer_found') }}</p>

        <ul v-if="results.length" class="max-h-64 divide-y overflow-y-auto rounded-md border" role="listbox" :aria-labelledby="`${id}-label`">
            <li v-for="customer in results" :key="customer.id">
                <button
                    type="button"
                    role="option"
                    :aria-selected="selected === customer.id"
                    :disabled="customer.id === excludeId"
                    :class="
                        cn(
                            'flex min-h-12 w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50',
                            selected === customer.id && 'bg-primary/10',
                        )
                    "
                    @click="selected = customer.id"
                >
                    <span class="flex-1">
                        <span class="block font-medium">{{ customer.name }}</span>
                        <span v-if="customer.phone" class="block text-muted-foreground">{{ customer.phone }}</span>
                    </span>
                    <Check v-if="selected === customer.id" class="size-4 text-primary" aria-hidden="true" />
                </button>
            </li>
        </ul>
    </div>
</template>
