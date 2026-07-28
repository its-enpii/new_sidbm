<script setup>
defineOptions({ inheritAttrs: false });

import { computed, ref, useId, watch } from 'vue';
import AppIcon from './AppIcon.vue';
import AppTooltip from './AppTooltip.vue';

const model = defineModel({ type: [String, Number], default: '' });
const props = defineProps({
    id: { type: String, default: null },
    label: { type: String, required: true },
    icon: { type: String, default: 'payments' },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    placeholder: { type: String, default: null },
    readonly: { type: Boolean, default: false },
    hideLabel: { type: Boolean, default: false },
    tooltip: { type: String, default: null },
    min: { type: Number, default: null },
    max: { type: Number, default: null },
    step: { type: Number, default: 1000 },
});

const generatedId = useId();
const inputId = props.id || generatedId;
const focused = ref(false);

const formatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

function parseDigits(value) {
    if (value === null || value === undefined || value === '') return '';
    return String(value).replace(/\D+/g, '');
}

function toNumber(value) {
    const digits = parseDigits(value);
    return digits === '' ? null : Number(digits);
}

const formatted = computed(() => {
    const digits = parseDigits(model.value);
    if (digits === '') return focused.value ? '' : '0';
    return formatter.format(Number(digits));
});

function onInput(event) {
    const digits = parseDigits(event.target.value);
    model.value = digits === '' ? '' : Number(digits);
}

function onFocus(event) {
    focused.value = true;
    if (parseDigits(model.value) === '') event.target.select();
}

function onBlur() {
    focused.value = false;
    const number = toNumber(model.value);
    if (number === null) { model.value = ''; return; }
    if (props.min !== null && number < props.min) model.value = props.min;
    else if (props.max !== null && number > props.max) model.value = props.max;
}

function adjust(delta) {
    const current = toNumber(model.value) ?? 0;
    const next = current + delta;
    if (props.min !== null && next < props.min) return;
    if (props.max !== null && next > props.max) return;
    model.value = next;
}

watch(model, () => {
    const number = toNumber(model.value);
    if (number !== null && props.min !== null && number < props.min) model.value = props.min;
});
</script>

<template>
    <div class="space-y-2">
        <div v-if="!hideLabel" class="flex items-center gap-1.5 ml-1">
            <label :for="inputId" class="block text-sm font-bold uppercase tracking-wider text-primary">{{ label }}</label>
            <AppTooltip v-if="tooltip" :id="`${inputId}-tooltip`" :text="tooltip" />
        </div>
        <label v-else :for="inputId" class="sr-only">{{ label }}</label>
        <div class="relative">
            <AppIcon v-if="icon" :name="icon" class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-xl text-outline" />
            <input
                :id="inputId"
                :value="formatted"
                inputmode="numeric"
                :aria-invalid="Boolean(error)"
                :aria-describedby="[
                    error && `${inputId}-error`,
                    hint && `${inputId}-hint`,
                    tooltip && `${inputId}-tooltip`
                ].filter(Boolean).join(' ') || undefined"
                :readonly="readonly"
                :placeholder="readonly ? undefined : (placeholder ?? `Masukkan ${label.toLowerCase()}`)"
                class="h-14 w-full rounded-xl border bg-surface-container-lowest pl-12 pr-20 text-primary transition placeholder:text-outline focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none read-only:cursor-default read-only:bg-surface-container-low read-only:text-on-surface-variant"
                :class="error ? 'border-error' : 'border-outline-variant'"
                v-bind="$attrs"
                @input="onInput"
                @focus="onFocus"
                @blur="onBlur"
                @keydown.up.prevent="adjust(step)"
                @keydown.down.prevent="adjust(-step)"
            >
            <div class="absolute right-2 top-1/2 flex h-10 -translate-y-1/2 items-center gap-1">
                <button v-if="!readonly" type="button" tabindex="-1" class="flex h-9 w-9 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none" aria-label="Kurangi nilai" @click="adjust(-step)">
                    <AppIcon name="remove" class="text-lg" />
                </button>
                <button v-if="!readonly" type="button" tabindex="-1" class="flex h-9 w-9 items-center justify-center rounded-lg text-on-surface-variant transition hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none" aria-label="Tambah nilai" @click="adjust(step)">
                    <AppIcon name="add" class="text-lg" />
                </button>
            </div>
        </div>
        <p v-if="error" :id="`${inputId}-error`" class="ml-1 text-sm text-error">{{ error }}</p>
        <p v-else-if="hint" :id="`${inputId}-hint`" class="ml-1 text-sm text-on-surface-variant">{{ hint }}</p>
    </div>
</template>
