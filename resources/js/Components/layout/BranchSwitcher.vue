<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Store } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Select from '@/Components/ui/Select.vue';

/**
 * Shown only when the user can work in more than one branch (SPEC section 9).
 * The server re-checks branch access on every switch and every request.
 */
const page = usePage();
const branches = computed(() => page.props.branches);
const selected = ref<number | string | null>(page.props.branch?.id ?? null);
const options = computed(() => branches.value.map((branch) => ({ value: branch.id, label: branch.name })));

function switchBranch(): void {
    if (selected.value === page.props.branch?.id) {
        return;
    }

    router.post(route('branch.switch'), { branch_id: selected.value });
}
</script>

<template>
    <div v-if="branches.length > 1" class="w-44 sm:w-56">
        <Select
            id="branch-switcher"
            v-model="selected"
            :options="options"
            :aria-label="$t('branch.switch')"
            @change="switchBranch"
        />
    </div>
    <p v-else-if="page.props.branch" class="flex items-center gap-2 text-sm text-muted-foreground">
        <Store class="size-4" aria-hidden="true" />
        <span class="truncate">{{ page.props.branch.name }}</span>
    </p>
</template>
