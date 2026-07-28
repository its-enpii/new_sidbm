<script setup>
defineProps({ tone: { type: String, default: 'neutral' } });

const tones = {
    neutral: 'bg-surface-container-low text-outline',
    success: 'bg-secondary-container text-secondary',
    warning: 'bg-tertiary-fixed text-tertiary',
    error: 'bg-error-container text-on-error-container',
    primary: 'bg-primary-fixed text-primary',
};
</script>

<template>
    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold" :class="tones[tone] || tones.neutral"><slot /></span>
</template>
