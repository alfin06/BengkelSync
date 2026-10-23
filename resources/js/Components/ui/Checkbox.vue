<script setup lang="ts">
defineOptions({ inheritAttrs: false });

defineProps<{ label: string; id: string }>();

const model = defineModel<boolean>({ default: false });
</script>

<template>
    <label :for="id" class="inline-flex min-h-11 cursor-pointer items-center gap-3 text-sm sm:min-h-0">
        <input
            :id="id"
            v-model="model"
            v-bind="$attrs"
            type="checkbox"
            class="size-5 rounded border-input text-primary focus:ring-2 focus:ring-ring"
        />
        <span>{{ label }}</span>
    </label>
</template>
