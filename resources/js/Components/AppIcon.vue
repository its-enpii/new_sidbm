<script setup>
defineProps({
    name: { type: String, required: true },
    filled: { type: Boolean, default: false },
    label: { type: String, default: null },
});
</script>

<template>
    <span
        class="material-symbols-outlined shrink-0"
        :class="{ 'is-filled': filled }"
        :aria-hidden="label ? undefined : 'true'"
        :aria-label="label || undefined"
        :role="label ? 'img' : undefined"
    >{{ name }}</span>
</template>
