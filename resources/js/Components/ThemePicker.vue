<script setup>
import AppIcon from './AppIcon.vue';
import { useTheme } from '../composables/useTheme';

const { theme, themes, setTheme } = useTheme();
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" role="listbox" aria-label="Tema tampilan">
        <button
            v-for="t in themes"
            :key="t.id"
            type="button"
            role="option"
            class="flex items-center gap-3 rounded-xl border p-4 text-left transition-colors"
            :class="theme === t.id
                ? 'border-primary-container bg-primary-fixed/40 ring-2 ring-primary-container'
                : 'border-outline-variant bg-surface-container-lowest hover:bg-surface-container-low'"
            :aria-selected="theme === t.id"
            @click="setTheme(t.id)"
        >
            <span class="theme-swatch shrink-0" :data-for="t.id" aria-hidden="true">
                <i /><i /><i />
            </span>
            <span class="min-w-0 flex-1">
                <span class="block font-bold text-primary">{{ t.label }}</span>
                <span class="block text-xs text-on-surface-variant">{{ t.id }}</span>
            </span>
            <AppIcon v-if="theme === t.id" name="check_circle" filled class="text-secondary" />
        </button>
    </div>
</template>
