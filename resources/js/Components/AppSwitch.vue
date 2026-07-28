<script setup>
import { useId } from 'vue';
import AppIcon from './AppIcon.vue';

const model = defineModel({ type: Boolean, default: false });
const props = defineProps({
    id: { type: String, default: null },
    label: { type: String, required: true },
    description: { type: String, default: null },
    icon: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

const switchId = props.id || useId();
</script>

<template>
    <label :for="switchId" class="flex min-h-14 cursor-pointer items-center justify-between gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest px-4 py-3 transition focus-within:border-primary-container focus-within:ring-2 focus-within:ring-primary-container/10" :class="disabled && 'cursor-not-allowed opacity-60'">
        <span class="flex items-center gap-3">
            <AppIcon v-if="icon" :name="icon" class="text-xl text-outline" />
            <span>
                <span class="block text-sm font-semibold text-primary">{{ label }}</span>
                <span v-if="description" class="mt-0.5 block text-xs text-on-surface-variant">{{ description }}</span>
            </span>
        </span>
        <span class="relative inline-flex shrink-0">
            <input :id="switchId" v-model="model" type="checkbox" role="switch" class="peer sr-only" :disabled="disabled">
            <span class="h-7 w-12 rounded-full bg-outline-variant transition peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary-container peer-focus-visible:ring-offset-2"></span>
            <span class="absolute left-1 top-1 size-5 rounded-full bg-surface-container-lowest shadow transition peer-checked:translate-x-5"></span>
        </span>
    </label>
</template>
