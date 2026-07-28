<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppIcon from './AppIcon.vue';

const page = usePage();
const visible = ref(false);
const message = ref('');
const tone = ref('success');
let timer;

const toneClass = computed(() => ({
    success: 'border-secondary/30 bg-secondary-container text-secondary',
    error: 'border-error/30 bg-error-container text-error',
    warning: 'border-tertiary/30 bg-tertiary-fixed text-tertiary',
    info: 'border-primary/30 bg-primary-fixed text-primary',
}[tone.value] || 'border-outline-variant bg-surface-container-lowest text-primary'));

const iconName = computed(() => ({
    success: 'check_circle',
    error: 'error',
    warning: 'warning',
    info: 'info',
}[tone.value] || 'info'));

function normalize(value) {
    if (value == null || value === '') return '';
    if (typeof value === 'string') return value;
    if (typeof value === 'object' && typeof value.message === 'string') return value.message;
    return '';
}

function show(nextTone, value) {
    const text = normalize(value);
    if (!text) return;
    clearTimeout(timer);
    tone.value = nextTone;
    message.value = text;
    visible.value = true;
    timer = setTimeout(() => { visible.value = false; }, 4500);
}

function dismiss() {
    clearTimeout(timer);
    visible.value = false;
}

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash || typeof flash !== 'object') return;
        if (flash.success != null) show('success', flash.success);
        else if (flash.error != null) show('error', flash.error);
        else if (flash.warning != null) show('warning', flash.warning);
        else if (flash.info != null) show('info', flash.info);
    },
    { deep: true, immediate: true },
);

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Teleport to="body">
        <Transition name="toast">
            <div
                v-if="visible"
                role="status"
                aria-live="polite"
                class="pointer-events-none fixed inset-x-0 top-4 z-[100] flex justify-center px-4 sm:justify-end sm:px-6"
            >
                <div
                    class="pointer-events-auto flex max-w-md items-start gap-3 rounded-2xl border px-4 py-3 shadow-lg"
                    :class="toneClass"
                >
                    <AppIcon :name="iconName" class="mt-0.5 text-xl" />
                    <p class="flex-1 text-sm font-semibold leading-snug">{{ message }}</p>
                    <button
                        type="button"
                        class="grid size-8 shrink-0 place-items-center rounded-full hover:bg-on-surface/10 focus:outline-none focus:ring-2 focus:ring-current/30"
                        aria-label="Tutup notifikasi"
                        @click="dismiss"
                    >
                        <AppIcon name="close" class="text-lg" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
    transition: opacity 180ms ease, transform 180ms ease;
}
.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(-0.5rem);
}
@media (prefers-reduced-motion: reduce) {
    .toast-enter-active,
    .toast-leave-active {
        transition: none;
    }
}
</style>
