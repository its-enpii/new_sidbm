<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import AppIcon from './AppIcon.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] },
    label: { type: String, required: true },
    placeholder: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    clearable: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
    searchable: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    valueKey: { type: String, default: 'value' },
    labelKey: { type: String, default: 'label' },
    groupKey: { type: String, default: 'group' },
    id: { type: String, default: null },
    hideLabel: { type: Boolean, default: false },
    emptyActionLabel: { type: String, default: null },
    excludedValues: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue', 'search', 'search-change', 'empty-action']);
const generatedId = useId();
const selectId = computed(() => props.id || generatedId);
const trigger = ref(null);
const listbox = ref(null);
const open = ref(false);
const placeAbove = ref(false);
const menuStyle = ref({});
const search = ref('');
const highlighted = ref(0);
const searchInput = ref(null);
const selectedCache = ref(null);
let searchTimer;

const selected = computed(() => {
    const current = props.options.find((option) => String(option[props.valueKey]) === String(props.modelValue));

    return current || (selectedCache.value && String(selectedCache.value[props.valueKey]) === String(props.modelValue) ? selectedCache.value : null);
});
const selectedLabel = computed(() => selected.value?.[props.labelKey] || '');
const visibleOptions = computed(() => {
    const excluded = new Set(props.excludedValues.map((value) => String(value)));
    const filtered = excluded.size === 0 ? props.options : props.options.filter((option) => !excluded.has(String(option[props.valueKey])));
    const query = search.value.trim().toLocaleLowerCase();

    if (!query) return filtered;

    return filtered.filter((option) => {
        const label = String(option[props.labelKey]).toLocaleLowerCase();
        const group = String(option[props.groupKey] ?? '').toLocaleLowerCase();
        return label.includes(query) || group.includes(query);
    });
});

/** Flat list for keyboard nav; grouped rows for render (header + options). */
const visibleRows = computed(() => {
    const opts = visibleOptions.value;
    const hasGroup = opts.some((o) => o[props.groupKey]);
    if (!hasGroup) {
        return opts.map((option, index) => ({ kind: 'option', option, index }));
    }

    const rows = [];
    let index = 0;
    let lastGroup = Symbol('start');
    for (const option of opts) {
        const group = option[props.groupKey] || '';
        if (group !== lastGroup) {
            rows.push({ kind: 'header', label: group || 'Lainnya' });
            lastGroup = group;
        }
        rows.push({ kind: 'option', option, index: index++ });
    }
    return rows;
});

function estimatedPopupHeight() {
    const itemHeight = 36;
    const optionHeight = Math.min(visibleRows.value.length, 8) * itemHeight;
    const searchHeight = props.searchable ? 52 : 0;
    return optionHeight + searchHeight + 16;
}

function positionMenu() {
    const triggerEl = trigger.value;
    if (!triggerEl) return;

    const margin = 8;
    const rect = triggerEl.getBoundingClientRect();
    const popupHeight = estimatedPopupHeight();
    const spaceBelow = window.innerHeight - rect.bottom - margin;
    const spaceAbove = rect.top - margin;
    const above = spaceBelow < popupHeight && spaceAbove > spaceBelow;
    placeAbove.value = above;

    const left = Math.min(Math.max(margin, rect.left), window.innerWidth - rect.width - margin);
    const maxList = Math.min(224 + (props.searchable ? 52 : 0), above ? spaceAbove : spaceBelow);

    if (above) {
        menuStyle.value = {
            position: 'fixed',
            left: `${left}px`,
            width: `${rect.width}px`,
            bottom: `${window.innerHeight - rect.top + margin}px`,
            top: 'auto',
            zIndex: 80,
            maxHeight: `${maxList}px`,
        };
    } else {
        menuStyle.value = {
            position: 'fixed',
            left: `${left}px`,
            width: `${rect.width}px`,
            top: `${rect.bottom + margin}px`,
            bottom: 'auto',
            zIndex: 80,
            maxHeight: `${maxList}px`,
        };
    }
}

function rememberSelected(options = props.options) {
    const option = options.find((item) => String(item[props.valueKey]) === String(props.modelValue));
    if (option) selectedCache.value = option;
}

function openMenu() {
    if (props.disabled) return;
    open.value = true;
    highlighted.value = Math.max(0, visibleOptions.value.findIndex((option) => String(option[props.valueKey]) === String(props.modelValue)));
    nextTick(() => {
        positionMenu();
        requestAnimationFrame(() => positionMenu());
        props.searchable && searchInput.value?.focus();
    });
}

function onViewportChange() {
    if (open.value) positionMenu();
}
onMounted(() => {
    window.addEventListener('resize', onViewportChange);
    window.addEventListener('scroll', onViewportChange, true);
});
onBeforeUnmount(() => {
    window.removeEventListener('resize', onViewportChange);
    window.removeEventListener('scroll', onViewportChange, true);
});

function closeMenu() {
    open.value = false;
    if (search.value) emit('search-change', '');
    search.value = '';
}

function choose(option) {
    selectedCache.value = option;
    emit('update:modelValue', option[props.valueKey]);
    closeMenu();
}

function clear() {
    selectedCache.value = null;
    emit('update:modelValue', '');
    closeMenu();
}

function runEmptyAction() {
    const query = search.value.trim();
    if (!props.emptyActionLabel || !query || props.loading || visibleOptions.value.length) return;

    closeMenu();
    emit('empty-action', query);
}

function move(delta) {
    if (!visibleOptions.value.length) return;
    highlighted.value = (highlighted.value + delta + visibleOptions.value.length) % visibleOptions.value.length;
}

function onKeydown(event) {
    if (!open.value && ['ArrowDown', 'Enter', ' '].includes(event.key)) {
        event.preventDefault();
        openMenu();
        return;
    }
    if (!open.value) return;
    if (event.key === 'ArrowDown') { event.preventDefault(); move(1); }
    if (event.key === 'ArrowUp') { event.preventDefault(); move(-1); }
    if (event.key === 'Enter') {
        if (visibleOptions.value[highlighted.value]) { event.preventDefault(); choose(visibleOptions.value[highlighted.value]); }
        else if (props.emptyActionLabel && search.value.trim() && !props.loading) { event.preventDefault(); runEmptyAction(); }
    }
    if (event.key === 'Escape') { event.preventDefault(); closeMenu(); }
}

function onSearch() {
    emit('search-change', search.value);
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => emit('search', search.value), 300);
}

function onDocumentClick(event) {
    if (!event.target.closest(`[data-smart-select="${selectId.value}"]`)) closeMenu();
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    document.removeEventListener('click', onDocumentClick);
});

watch(() => props.options, (options) => {
    rememberSelected(options);
    highlighted.value = Math.min(highlighted.value, Math.max(options.length - 1, 0));
}, { immediate: true });
watch(search, () => {
    highlighted.value = 0;
});
watch(() => props.modelValue, () => rememberSelected());
</script>

<template>
    <div class="space-y-2" :data-smart-select="selectId">
        <label :for="selectId" :class="hideLabel ? 'sr-only' : 'ml-1 block text-sm font-bold uppercase tracking-wider text-primary'">{{ label }}</label>
        <div class="relative">
            <button
                :id="selectId"
                ref="trigger"
                type="button"
                role="combobox"
                :aria-expanded="open"
                :aria-controls="`${selectId}-listbox`"
                :aria-invalid="Boolean(error)"
                :aria-required="required"
                :disabled="disabled"
                class="flex h-14 w-full items-center justify-between rounded-xl border bg-surface-container-lowest px-4 pr-16 text-left text-primary transition focus:border-primary-container focus:ring-2 focus:ring-primary-container/10 focus:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                :class="error ? 'border-error' : 'border-outline-variant'"
                v-bind="$attrs"
                @click="open ? closeMenu() : openMenu()"
                @keydown="onKeydown"
            >
                <span :class="selectedLabel ? 'text-primary' : 'text-outline'">{{ selectedLabel || (placeholder ?? `Pilih ${label.toLowerCase()}`) }}</span>
                <AppIcon :name="open ? 'expand_less' : 'expand_more'" class="absolute right-3 text-xl text-outline" />
            </button>
            <button
                v-if="clearable && selectedLabel"
                type="button"
                class="absolute right-9 top-1/2 z-10 -translate-y-1/2 rounded-full p-1 text-outline hover:bg-surface-container-low hover:text-primary focus:outline-none focus:ring-2 focus:ring-primary-container/20"
                aria-label="Hapus pilihan"
                @click="clear"
            >
                <AppIcon name="close" class="text-lg" />
            </button>
            <Teleport to="body">
                <div
                    v-if="open"
                    :id="`${selectId}-listbox`"
                    ref="listbox"
                    role="listbox"
                    class="flex flex-col overflow-hidden rounded-xl border border-outline-variant bg-surface-container-lowest p-2 shadow-lg"
                    :style="menuStyle"
                    :data-smart-select="selectId"
                >
                    <div v-if="searchable" class="mb-2 shrink-0 bg-surface-container-lowest pb-1"><input ref="searchInput" v-model="search" type="search" class="h-11 w-full rounded-lg border border-outline-variant px-3 text-sm text-primary focus:border-primary focus:outline-none" placeholder="Cari opsi..." @input="onSearch" @keydown="onKeydown"></div>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <div v-if="loading" class="px-3 py-4 text-center text-sm text-on-surface-variant">Memuat...</div>
                        <template v-else>
                            <template v-for="(row, rowIndex) in visibleRows" :key="row.kind === 'header' ? `h-${row.label}-${rowIndex}` : String(row.option[valueKey])">
                                <div
                                    v-if="row.kind === 'header'"
                                    class="px-3 pb-1 pt-2 text-[10px] font-bold uppercase tracking-wider text-on-surface-variant first:pt-1"
                                    role="presentation"
                                >
                                    {{ row.label }}
                                </div>
                                <button
                                    v-else
                                    :id="`${selectId}-option-${row.index}`"
                                    type="button"
                                    role="option"
                                    :aria-selected="String(row.option[valueKey]) === String(modelValue)"
                                    class="flex w-full items-center rounded-lg px-3 py-2 text-left text-sm hover:bg-surface-container-low"
                                    :class="row.index === highlighted ? 'bg-surface-container-low text-primary' : 'text-on-surface'"
                                    @mouseenter="highlighted = row.index"
                                    @click="choose(row.option)"
                                >
                                    {{ row.option[labelKey] }}
                                </button>
                            </template>
                            <button v-if="!visibleOptions.length && emptyActionLabel && search.trim()" type="button" class="flex w-full items-center gap-2 rounded-lg px-3 py-3 text-left text-sm font-semibold text-primary hover:bg-surface-container-low focus:bg-surface-container-low focus:outline-none" @click="runEmptyAction"><AppIcon name="person_add" class="text-xl" />{{ emptyActionLabel }}</button>
                            <div v-else-if="!visibleOptions.length" class="px-3 py-4 text-center text-sm text-on-surface-variant">Tidak ada opsi.</div>
                        </template>
                    </div>
                </div>
            </Teleport>
        </div>
        <p v-if="error" :id="`${selectId}-error`" class="ml-1 text-sm text-error">{{ error }}</p>
        <p v-else-if="hint" :id="`${selectId}-hint`" class="ml-1 text-sm text-on-surface-variant">{{ hint }}</p>
    </div>
</template>
