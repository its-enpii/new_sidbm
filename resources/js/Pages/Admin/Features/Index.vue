<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenants: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'row_id' },
    direction: { type: String, default: 'desc' },
    globalAiEnabled: { type: Boolean, default: true },
});

const globalEnabled = ref(props.globalAiEnabled);
const updatingGlobal = ref(false);

watch(() => props.globalAiEnabled, (val) => {
    globalEnabled.value = val;
});

function toggleGlobal(val) {
    updatingGlobal.value = true;
    router.patch(
        '/admin/features/ai/global',
        { enabled: val },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingGlobal.value = false;
            },
        }
    );
}

const selectedTenantIds = ref([]);

const allRowsSelected = computed(() => {
    if (!props.tenants.data || props.tenants.data.length === 0) return false;
    return props.tenants.data.every((r) => selectedTenantIds.value.includes(r.row_id));
});

const isIndeterminate = computed(() => {
    if (selectedTenantIds.value.length === 0) return false;
    return !allRowsSelected.value;
});

function toggleSelectAll(checked) {
    if (checked) {
        const ids = props.tenants.data.map((r) => r.row_id);
        selectedTenantIds.value = Array.from(new Set([...selectedTenantIds.value, ...ids]));
    } else {
        const pageIds = new Set(props.tenants.data.map((r) => r.row_id));
        selectedTenantIds.value = selectedTenantIds.value.filter((id) => !pageIds.has(id));
    }
}

const bulkProcessing = ref(false);

function bulkSubmit(actionValue) {
    if (selectedTenantIds.value.length === 0 || bulkProcessing.value) return;
    bulkProcessing.value = true;

    router.post(
        '/admin/features/ai/bulk',
        {
            tenant_ids: selectedTenantIds.value,
            value: actionValue,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedTenantIds.value = [];
            },
            onFinish: () => {
                bulkProcessing.value = false;
            },
        }
    );
}

const updatingTenantId = ref(null);

function updateTenantAi(tenantRowId, newValue) {
    updatingTenantId.value = tenantRowId;
    router.patch(
        `/admin/features/ai/${tenantRowId}`,
        { value: newValue },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingTenantId.value = null;
            },
        }
    );
}

const statusOptions = [
    { value: 'inherit', label: 'Warisi default' },
    { value: 'on', label: 'Aktif (Paksa)' },
    { value: 'off', label: 'Nonaktif (Paksa)' },
];

const columns = [
    { key: 'select', label: 'Pilih' },
    { key: 'name', label: 'Tenant', sortable: true },
    { key: 'plan', label: 'Plan' },
    { key: 'is_training_mode', label: 'Mode Training', sortable: true },
    { key: 'ai_status', label: 'Status AI' },
];
</script>

<template>
    <Head title="Sakelar Fitur AI" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Sakelar Fitur AI Per-Tenant</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Kelola kunci global asisten AI serta aturan akses override per-tenant platform.
                    </p>
                </div>
            </header>

            <!-- Card 1: Master Kill-Switch -->
            <AppCard>
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-primary">Kunci Global Fitur AI</h2>
                            <AppBadge :tone="globalEnabled ? 'success' : 'error'">
                                {{ globalEnabled ? 'Platform Aktif' : 'Platform Nonaktif' }}
                            </AppBadge>
                        </div>
                        <p class="text-sm text-on-surface-variant">
                            Master switch untuk seluruh sistem. Jika dimatikan, asisten AI akan nonaktif untuk seluruh tenant tanpa terkecuali.
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <AppSwitch
                            v-model="globalEnabled"
                            :disabled="updatingGlobal"
                            @update:model-value="toggleGlobal"
                        />
                    </div>
                </div>
            </AppCard>

            <!-- Card 2: Tenant List & Overrides -->
            <AppCard :padded="false">
                <div class="p-6">
                    <div v-if="selectedTenantIds.length > 0" class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-primary/20 bg-primary-container/20 p-4">
                        <div class="flex items-center gap-2 text-sm font-semibold text-primary">
                            <AppIcon name="checklist" class="text-lg" />
                            <span>{{ selectedTenantIds.length }} tenant terpilih</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <AppButton
                                variant="success"
                                size="compact"
                                icon="check"
                                :loading="bulkProcessing"
                                @click="bulkSubmit('on')"
                            >
                                Aktifkan AI
                            </AppButton>
                            <AppButton
                                variant="danger"
                                size="compact"
                                icon="block"
                                :loading="bulkProcessing"
                                @click="bulkSubmit('off')"
                            >
                                Nonaktifkan AI
                            </AppButton>
                            <AppButton
                                variant="secondary"
                                size="compact"
                                icon="undo"
                                :loading="bulkProcessing"
                                @click="bulkSubmit('inherit')"
                            >
                                Warisi Default
                            </AppButton>
                        </div>
                    </div>

                    <SmartDataTable
                        :rows="tenants.data"
                        :columns="columns"
                        :pagination="tenants"
                        url="/admin/features"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari tenant"
                        search-placeholder="Nama, kode, atau kecamatan"
                        empty-title="Belum ada tenant"
                        empty-description="Daftarkan tenant pertama untuk mulai mengelola fitur."
                    >
                        <template #cell-select="{ row }">
                            <AppCheckbox
                                v-model="selectedTenantIds"
                                :value="row.row_id"
                                aria-label="Pilih tenant"
                            />
                        </template>

                        <template #cell-name="{ row }">
                            <Link :href="`/admin/tenants/${row.row_id}`" class="font-semibold text-primary">
                                {{ row.name }}
                            </Link>
                            <span class="block text-xs text-on-surface-variant">{{ row.code }}</span>
                        </template>

                        <template #cell-plan="{ row }">
                            <span class="text-sm font-medium text-primary">{{ row.plan?.name || '—' }}</span>
                        </template>

                        <template #cell-is_training_mode="{ row }">
                            <AppBadge :tone="row.is_training_mode ? 'warning' : 'neutral'">
                                {{ row.is_training_mode ? 'Training' : 'Produksi' }}
                            </AppBadge>
                        </template>

                        <template #cell-ai_status="{ row }">
                            <div class="space-y-1">
                                <div>
                                    <AppBadge v-if="row.ai_enabled === 'on'" tone="success">
                                        Aktif (Paksa)
                                    </AppBadge>
                                    <AppBadge v-else-if="row.ai_enabled === 'off'" tone="error">
                                        Nonaktif (Paksa)
                                    </AppBadge>
                                    <AppBadge v-else tone="neutral">
                                        Warisi default
                                    </AppBadge>
                                </div>
                                <span class="block text-xs text-on-surface-variant">
                                    Efektif:
                                    <strong :class="row.effective_ai_enabled ? 'text-primary font-semibold' : 'text-outline font-normal'">
                                        {{ row.effective_ai_enabled ? 'Aktif' : 'Nonaktif' }}
                                    </strong>
                                </span>
                            </div>
                        </template>

                        <template #actions="{ row }">
                            <div class="min-w-44">
                                <SmartSelect
                                    :id="`select-ai-${row.row_id}`"
                                    label="Pengaturan AI"
                                    hide-label
                                    :model-value="row.ai_enabled"
                                    :options="statusOptions"
                                    :disabled="updatingTenantId === row.row_id"
                                    @update:model-value="(val) => updateTenantAi(row.row_id, val)"
                                />
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
