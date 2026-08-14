<script setup>
import { ref, onUnmounted, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';

const props = defineProps({
    tenants: { type: Array, required: true },
    runs: { type: Object, required: true },
});

const showAdvanced = ref(false);
const selectedRun = ref(null);
const activeLogModal = ref(false);
let pollInterval = null;

const tenantOptions = computed(() =>
    props.tenants.map(t => ({
        value: t.row_id,
        label: `${t.name} (Code: ${t.code})`,
    }))
);

const form = useForm({
    tenant_id: props.tenants[0]?.row_id ?? '',
    suffix: '1',
    is_dry_run: false,
    run_immediately: true,
    chunk: 500,
    from_year: 2018,
    to_year: new Date().getFullYear(),
    skip_fiscal: false,
    skip_coa: false,
    skip_accounting: false,
    skip_membership: false,
    skip_lending: false,
    skip_payment_progress: false,
    skip_reconcile: false,
    skip_sequences: false,
    continue_on_error: false,
    no_fail_fast: false,
});

const submitCutover = () => {
    form.post(route('admin.migrations.store'), {
        preserveScroll: true,
        onSuccess: () => {
            if (props.runs.data && props.runs.data.length > 0) {
                openLogModal(props.runs.data[0]);
            }
        },
    });
};

const openLogModal = (run) => {
    selectedRun.value = run;
    activeLogModal.value = true;
    startPolling(run.id);
};

const closeLogModal = () => {
    activeLogModal.value = false;
    stopPolling();
};

const startPolling = (runId) => {
    stopPolling();
    fetchRunDetails(runId);
    pollInterval = setInterval(() => {
        if (selectedRun.value && (selectedRun.value.status === 'running' || selectedRun.value.status === 'pending')) {
            fetchRunDetails(runId);
        } else {
            stopPolling();
        }
    }, 2500);
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

const fetchRunDetails = async (runId) => {
    try {
        const response = await fetch(route('admin.migrations.show', runId));
        if (response.ok) {
            const data = await response.json();
            selectedRun.value = data;
            if (data.status === 'completed' || data.status === 'failed') {
                router.reload({ only: ['runs'] });
            }
        }
    } catch (e) {
        console.error('Failed to poll migration run log:', e);
    }
};

onUnmounted(() => {
    stopPolling();
});

const getStatusVariant = (status) => {
    switch (status) {
        case 'completed': return 'success';
        case 'running': return 'info';
        case 'failed': return 'danger';
        case 'pending': return 'warning';
        default: return 'neutral';
    }
};

const getStepStatusVariant = (status) => {
    switch (status) {
        case 'ok': return 'bg-emerald-500 text-white';
        case 'running': return 'bg-amber-500 text-white animate-pulse';
        case 'failed': return 'bg-rose-500 text-white';
        case 'skipped': return 'bg-slate-300 text-slate-700 dark:bg-slate-700 dark:text-slate-300';
        default: return 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400';
    }
};
</script>

<template>
    <Head title="Migrasi Data Legacy" />

    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header -->
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Migrasi Data Legacy (Cutover)</h1>
                    <p class="mt-1 text-slate-600 dark:text-slate-400">
                        Alat bantu berbasis tampilan GUI untuk mengonversi & memindahkan data dari SIDBM Legacy ke SIDBM Next secara bertahap tanpa perintah terminal.
                    </p>
                </div>
            </header>

            <!-- Grid Form & Banner -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Left: Form Input (2 Columns) -->
                <div class="lg:col-span-2">
                    <AppCard>
                        <template #header>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Form Eksekusi Migrasi Baru</h2>
                        </template>

                        <form @submit.prevent="submitCutover" class="space-y-5">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Tenant Selection via SmartSelect -->
                                <div>
                                    <SmartSelect
                                        v-model="form.tenant_id"
                                        label="Pilih Tenant Target"
                                        :options="tenantOptions"
                                        :error="form.errors.tenant_id"
                                        searchable
                                        required
                                    />
                                </div>

                                <!-- Suffix Lokasi ID via AppInput -->
                                <div>
                                    <AppInput
                                        v-model="form.suffix"
                                        label="ID Lokasi (Suffix Legacy DB)"
                                        type="number"
                                        placeholder="Contoh: 1"
                                        :error="form.errors.suffix"
                                        required
                                    />
                                </div>
                            </div>

                            <!-- Mode Toggles -->
                            <div class="space-y-3 rounded-xl bg-surface-container-low p-4">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.is_dry_run"
                                        class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary-container"
                                    />
                                    <div>
                                        <span class="text-sm font-medium text-primary">Mode Dry-Run (Simulasi / Uji Coba)</span>
                                        <p class="text-xs text-on-surface-variant">Menjalankan simulasi validasi tanpa menyimpan perubahan ke database utama.</p>
                                    </div>
                                </label>

                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        v-model="form.run_immediately"
                                        class="h-4 w-4 rounded border-outline-variant text-primary focus:ring-primary-container"
                                    />
                                    <div>
                                        <span class="text-sm font-medium text-primary">Eksekusi Langsung (Synchronous Execution)</span>
                                        <p class="text-xs text-on-surface-variant">Jalankan langsung di server tanpa menunggu antrean background worker.</p>
                                    </div>
                                </label>
                            </div>

                            <!-- Toggle Opsi Lanjutan -->
                            <div>
                                <AppButton
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    @click="showAdvanced = !showAdvanced"
                                >
                                    {{ showAdvanced ? 'Sembunyikan Opsi Lanjutan' : 'Tampilkan Opsi Lanjutan (Skipping / Chunk Size)' }}
                                </AppButton>
                            </div>

                            <div v-if="showAdvanced" class="space-y-4 rounded-xl border border-outline-variant p-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <AppInput v-model="form.chunk" label="Chunk Size" type="number" min="10" max="5000" />
                                    </div>
                                    <div>
                                        <AppInput v-model="form.from_year" label="Tahun Fiskal Dari" type="number" min="2000" />
                                    </div>
                                    <div>
                                        <AppInput v-model="form.to_year" label="Tahun Fiskal Sampai" type="number" min="2000" />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <span class="block text-xs font-semibold text-primary">Lompati Step (Optional Skipping):</span>
                                    <div class="grid grid-cols-2 gap-2 text-xs">
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_fiscal" /> <span>Skip Fiscal Periods</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_coa" /> <span>Skip COA Import</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_accounting" /> <span>Skip Accounting Jurnal</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_membership" /> <span>Skip Keanggotaan</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_lending" /> <span>Skip Pinjaman</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_payment_progress" /> <span>Skip Progress Angsuran</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_reconcile" /> <span>Skip Rekonsiliasi</span></label>
                                        <label class="flex items-center space-x-2"><input type="checkbox" v-model="form.skip_sequences" /> <span>Skip Sequences</span></label>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end pt-2">
                                <AppButton type="submit" variant="primary" :disabled="form.processing">
                                    <span v-if="form.processing">Sedang Memproses...</span>
                                    <span v-else>Jalankan Migrasi Data</span>
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>
                </div>

                <!-- Right: Information Box -->
                <div>
                    <AppCard>
                        <template #header>
                            <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Petunjuk Alur Migrasi</h2>
                        </template>
                        <div class="space-y-3 text-xs text-slate-600 dark:text-slate-300">
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-emerald-600">1.</span>
                                <p>Pastikan nama database legacy di MySQL lokal (`db_lokasi_ID`) siap diakses.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-emerald-600">2.</span>
                                <p>Isi kode **Suffix Lokasi ID** sesuai ID kecamatan legacy (misal ID 1, 1101, dst).</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-emerald-600">3.</span>
                                <p>Disarankan melakukan **Mode Dry-Run** terlebih dahulu sebelum meluncurkan migrasi riil.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-emerald-600">4.</span>
                                <p>Klik tombol **Detail Log** pada tabel riwayat di bawah untuk memantau progress konsol secara live.</p>
                            </div>
                        </div>
                    </AppCard>
                </div>
            </div>

            <!-- Tabel Riwayat Migrasi -->
            <AppCard class="overflow-hidden">
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Riwayat Process Cutover / Migrasi</h2>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                        <thead class="bg-slate-50 dark:bg-slate-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Tenant</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Suffix</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Mode</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Waktu Mulai</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                            <tr v-for="run in runs.data" :key="run.id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3 text-xs font-mono text-slate-500">#{{ run.id }}</td>
                                <td class="px-4 py-3 text-xs font-medium text-slate-900 dark:text-white">
                                    {{ run.tenant_name }} <span class="text-slate-400">({{ run.tenant_code }})</span>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono text-slate-700 dark:text-slate-300">{{ run.suffix }}</td>
                                <td class="px-4 py-3 text-xs">
                                    <span v-if="run.is_dry_run" class="rounded bg-amber-100 px-2 py-0.5 font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">Simulasi (Dry Run)</span>
                                    <span v-else class="rounded bg-blue-100 px-2 py-0.5 font-semibold text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">Live Write</span>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <AppBadge :variant="getStatusVariant(run.status)">
                                        <span class="capitalize">{{ run.status }}</span>
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-500">
                                    {{ run.started_at ? new Date(run.started_at).toLocaleString('id-ID') : 'Belum dimulai' }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <AppButton variant="secondary" size="xs" @click="openLogModal(run)">
                                        Detail & Log Output
                                    </AppButton>
                                </td>
                            </tr>
                            <tr v-if="runs.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-slate-500">
                                    Belum ada riwayat proses migrasi yang dijalankan.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>

        <!-- Modal Detail Output Log & Live Step Progress -->
        <div v-if="activeLogModal && selectedRun" class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-slate-900/70 p-4">
            <div class="w-full max-w-4xl rounded-xl bg-white shadow-2xl dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center justify-between border-b border-slate-200 p-4 dark:border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">
                            Monitoring Cutover Run #{{ selectedRun.id }} â€” {{ selectedRun.tenant_name }}
                        </h3>
                        <p class="text-xs text-slate-500">Suffix: {{ selectedRun.suffix }} | Mode: {{ selectedRun.is_dry_run ? 'Dry Run' : 'Live' }}</p>
                    </div>
                    <AppButton variant="ghost" size="xs" @click="closeLogModal">?</AppButton>
                </div>

                <div class="space-y-4 p-4">
                    <!-- Step Progress Badges -->
                    <div v-if="selectedRun.steps && selectedRun.steps.length > 0" class="space-y-2">
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Progress Tahapan Migrasi:</span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div v-for="step in selectedRun.steps" :key="step.name" class="rounded-lg border border-slate-200 p-2 dark:border-slate-800 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium truncate text-slate-700 dark:text-slate-200" :title="step.label">{{ step.name }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] uppercase font-bold', getStepStatusVariant(step.status)]">
                                        {{ step.status }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400 truncate">{{ step.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Output Console Terminal Window -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-slate-700 dark:text-slate-300">Terminal Console Log Output:</span>
                            <span v-if="selectedRun.status === 'running'" class="text-xs font-medium text-amber-500 animate-pulse">
                                Live Polling Output...
                            </span>
                        </div>
                        <pre class="h-80 w-full overflow-y-auto rounded-lg bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner border border-slate-800 whitespace-pre-wrap">{{ selectedRun.output_log || 'Menunggu keluaran log...' }}</pre>
                    </div>

                    <div v-if="selectedRun.error_message" class="rounded-lg bg-rose-50 p-3 text-xs text-rose-800 dark:bg-rose-950/40 dark:text-rose-300">
                        <strong>Error Exception:</strong> {{ selectedRun.error_message }}
                    </div>
                </div>

                <div class="flex justify-end border-t border-slate-200 p-4 dark:border-slate-800">
                    <AppButton variant="secondary" @click="closeLogModal">Tutup Window Log</AppButton>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
