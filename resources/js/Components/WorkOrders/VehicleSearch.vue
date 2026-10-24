<script setup lang="ts">
import axios from 'axios';
import { useDebounceFn } from '@vueuse/core';
import { Car, Plus, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import type { VehicleOption } from '@/types/workOrders';

/**
 * Find a vehicle by plate ("b1234" finds "B 1234 XYZ"). When nothing matches, the user
 * can add the vehicle with the plate already typed.
 */
defineProps<{ id: string; canCreate: boolean; invalid?: boolean; describedBy?: string }>();
const emit = defineEmits<{ pick: [vehicle: VehicleOption]; create: [plate: string] }>();

const term = ref('');
const results = ref<VehicleOption[]>([]);
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
        const { data } = await axios.get<VehicleOption[]>(route('vehicles.lookup'), { params: { q: term.value } });
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
            <Search class="pointer-events-none absolute left-3 top-1/2 size-5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
            <input
                :id="id"
                v-model="term"
                type="search"
                autocomplete="off"
                autocapitalize="characters"
                :placeholder="$t('work_orders.search_plate')"
                :aria-invalid="invalid || undefined"
                :aria-describedby="describedBy"
                :class="[
                    'h-12 w-full rounded-md border border-input bg-background pl-10 pr-3 text-lg uppercase tracking-wide shadow-sm placeholder:normal-case placeholder:tracking-normal focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                    invalid && 'border-destructive',
                ]"
                @input="search"
            />
        </div>

        <p v-if="loading" class="text-sm text-muted-foreground" role="status">{{ $t('common.searching') }}</p>

        <ul v-if="results.length" class="divide-y rounded-md border bg-card">
            <li v-for="vehicle in results" :key="vehicle.id">
                <button type="button" class="flex min-h-14 w-full items-center gap-3 px-3 py-2 text-left hover:bg-muted/50" @click="emit('pick', vehicle)">
                    <Car class="size-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="min-w-0 flex-1">
                        <span class="block font-semibold">{{ vehicle.plate_number }}</span>
                        <span class="block text-sm text-muted-foreground">{{ vehicle.name }} · {{ vehicle.customer_name }}</span>
                        <span v-if="vehicle.open_work_order" class="block text-xs font-medium text-amber-700">
                            {{ $t('work_orders.has_open', { number: vehicle.open_work_order }) }}
                        </span>
                    </span>
                </button>
            </li>
        </ul>

        <div v-if="searched && !loading && canCreate" class="flex flex-wrap items-center gap-2 text-sm">
            <span v-if="results.length === 0" class="text-muted-foreground">{{ $t('work_orders.no_vehicle_found') }}</span>
            <button type="button" class="inline-flex min-h-11 items-center gap-1 font-medium text-primary underline-offset-4 hover:underline" @click="emit('create', term)">
                <Plus class="size-4" aria-hidden="true" /> {{ $t('work_orders.new_vehicle') }}
            </button>
        </div>
    </div>
</template>
