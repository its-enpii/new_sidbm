<script setup>
import { ref, onUnmounted, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AppIcon from '../../../Components/AppIcon.vue';

const props = defineProps({
    tenants: { type: Array, required: true },
    runs: { type: Object, required: true },
    legacy_config: { type: Object, default: () => ({ host: '127.0.0.1', port: 3306, database: 'sidbm' }) },
    discovered_suffixes: { type: Array, default: () => [] },
});

const selectedRun = ref(null);
const activeLogModal = ref(false);
let pollInterval = null;

const tenantOptions = computed(() =>
    props.tenants.map(t => ({
        value: t.row_id,
        label: `${t.name} (Code: ${t.code})`,
    }))
);

const suffixOptions = computed(() => {
    if (!props.discovered_suffixes || props.discovered_suffixes.length === 0) {
        return [{ value: '1', label: 'Suffix 1 (Default Manual Input)' }];
    }
    return props.discovered_suffixes.map(s => ({
        value: s.suffix,
        label: `Suffix ${s.suffix} ? ${s.transaksi_table} (${s.transaksi_count !== null ? s.transaksi_count.toLocaleString('id-ID') + ' transaksi' : 'Tabel Ditemukan'}${s.min_date ? ' | ' + s.min_date + ' s/d ' + s.max_date : ''})`,
    }));
});

const form = useForm({
    tenant_id: props.tenants[0]?.row_id ?? '',
    suffix: '1',
    is_dry_run: false,
    run_immediately: true,
    chunk: 500,
    from_year: '2018',
    to_year: String(new Date().getFullYear()),
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
    form.post('/admin/migrations', {
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
        const response = await fetch('/admin/migrations/' + runId);
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
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Migrasi Data Legacy (Cutover)</h1>
                    <p class="mt-1 text-on-surface-variant">
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
                            <h2 class="text-lg font-bold text-primary">Form Eksekusi Migrasi Baru</h2>
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

                                <!-- Suffix Lokasi ID Selection -->
                                <div>
                                    <SmartSelect
                                        v-if="props.discovered_suffixes && props.discovered_suffixes.length > 0"
                                        v-model="form.suffix"
                                        label="ID Lokasi (Suffix Terdeteksi)"
                                        :options="suffixOptions"
                                        :error="form.errors.suffix"
                                        required
                                    />
                                    <AppInput
                                        v-else
                                        v-model="form.suffix"
                                        label="ID Lokasi (Suffix Legacy DB)"
                                        type="number"
                                        placeholder="Contoh: 1"
                                        :error="form.errors.suffix"
                                        required
                                    />
                                </div>
                            </div>

                            <!-- Mode Toggles via AppSwitch -->
                            <div class="space-y-3 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <AppSwitch
                                    v-model="form.is_dry_run"
                                    label="Mode Dry-Run (Simulasi / Uji Coba)"
                                    description="Menjalankan simulasi validasi tanpa menyimpan perubahan ke database utama."
                                    icon="science"
                                />

                                <AppSwitch
                                    v-model="form.run_immediately"
                                    label="Eksekusi Langsung (Synchronous Execution)"
                                    description="Jalankan langsung di server tanpa menunggu antrean background worker."
                                    icon="bolt"
                                />
                            </div>

                            <!-- Opsi Lanjutan & Skipping -->
                            <div class="space-y-4 rounded-xl border border-outline-variant p-4">
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                    <div>
                                        <AppInput v-model="form.chunk" label="Chunk Size" type="number" min="10" max="5000" />
                                    </div>
                                    <div>
                                        <AppDatePicker v-model="form.from_year" label="Tahun Fiskal Dari" mode="year" placeholder="Pilih Tahun" />
                                    </div>
                                    <div>
                                        <AppDatePicker v-model="form.to_year" label="Tahun Fiskal Sampai" mode="year" placeholder="Pilih Tahun" />
                                    </div>
                                </div>

                                <div class="space-y-3">
                                    <span class="block text-xs font-bold uppercase tracking-wider text-primary">Lompati Step (Optional Skipping):</span>
                                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <AppSwitch v-model="form.skip_fiscal" label="Lompati Periode Fiskal" description="Skip Fiscal Periods" />
                                        <AppSwitch v-model="form.skip_coa" label="Lompati Bagan Akun (COA)" description="Skip COA Import" />
                                        <AppSwitch v-model="form.skip_accounting" label="Lompati Jurnal Akuntansi" description="Skip Accounting Jurnal" />
                                        <AppSwitch v-model="form.skip_membership" label="Lompati Keanggotaan" description="Skip Keanggotaan" />
                                        <AppSwitch v-model="form.skip_lending" label="Lompati Data Pinjaman" description="Skip Pinjaman" />
                                        <AppSwitch v-model="form.skip_payment_progress" label="Lompati Progress Angsuran" description="Skip Progress Angsuran" />
                                        <AppSwitch v-model="form.skip_reconcile" label="Lompati Rekonsiliasi" description="Skip Rekonsiliasi" />
                                        <AppSwitch v-model="form.skip_sequences" label="Lompati Sequences" description="Skip Sequences" />
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end pt-2">
                                <AppButton type="submit" variant="primary" icon="play_arrow" :disabled="form.processing">
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
                            <h2 class="text-lg font-bold text-primary">Petunjuk Alur Migrasi</h2>
                        </template>
                        <div class="space-y-3 text-xs text-on-surface-variant leading-relaxed">
                            <div class="rounded-lg border border-outline-variant bg-surface-container-low p-3 space-y-1">
                                <span class="block font-bold text-primary text-xs">Koneksi Database Legacy Aktif:</span>
                                <p>Host: <code class="font-mono text-primary font-bold">{{ props.legacy_config?.host || '127.0.0.1' }}:{{ props.legacy_config?.port || 3306 }}</code></p>
                                <p>Database: <code class="font-mono text-primary font-bold">{{ props.legacy_config?.database || 'sidbm' }}</code></p>
                            </div>

                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-secondary shrink-0">1.</span>
                                <p>Sistem membaca database MySQL legacy <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">{{ props.legacy_config?.database || 'sidbm' }}</code> yang telah dikonfigurasi di file <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">.env</code>.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-secondary shrink-0">2.</span>
                                <p>Tabel legacy dibedakan oleh akhiran angka (Suffix ID), seperti <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">transaksi_1</code> atau <code class="rounded bg-surface-container px-1 py-0.5 font-mono text-[11px]">saldo_1</code>.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-secondary shrink-0">3.</span>
                                <p>Pilihlah <strong>Suffix Terdeteksi</strong> dari daftar dropdown. Sistem akan otomatis memindai tabel yang tersedia.</p>
                            </div>
                            <div class="flex items-start space-x-2">
                                <span class="font-bold text-secondary shrink-0">4.</span>
                                <p>Disarankan melakukan <strong>Mode Dry-Run</strong> terlebih dahulu untuk mensimulasikan validasi data tanpa mengubah database utama.</p>
                            </div>
                        </div>
                    </AppCard>
                </div>
            </div>

            <!-- Tabel Riwayat Executions -->
            <AppCard>
                <template #header>
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-primary">Riwayat Eksekusi Migrasi (Cutover Runs)</h2>
                        <AppButton variant="ghost" size="compact" icon="refresh" @click="router.reload({ only: ['runs'] })">
                            Refresh Data
                        </AppButton>
                    </div>
                </template>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-lowest text-xs uppercase tracking-wider text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3 font-bold">ID Run</th>
                                <th class="px-4 py-3 font-bold">Tenant Target</th>
                                <th class="px-4 py-3 font-bold">Suffix</th>
                                <th class="px-4 py-3 font-bold">Mode</th>
                                <th class="px-4 py-3 font-bold">Status</th>
                                <th class="px-4 py-3 font-bold">Waktu Mulai</th>
                                <th class="px-4 py-3 text-right font-bold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr v-for="run in runs.data" :key="run.id" class="transition hover:bg-surface-container-low/50">
                                <td class="px-4 py-3 font-mono font-bold text-primary">#{{ run.id }}</td>
                                <td class="px-4 py-3 font-medium text-on-surface">{{ run.tenant_name }}</td>
                                <td class="px-4 py-3 font-mono text-on-surface-variant">{{ run.suffix }}</td>
                                <td class="px-4 py-3">
                                    <AppBadge :tone="run.is_dry_run ? 'neutral' : 'warning'">
                                        {{ run.is_dry_run ? 'Dry Run' : 'Live Cutover' }}
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-3">
                                    <AppBadge :tone="getStatusVariant(run.status)">
                                        <span class="capitalize">{{ run.status }}</span>
                                    </AppBadge>
                                </td>
                                <td class="px-4 py-3 text-xs text-on-surface-variant">
                                    {{ run.started_at ? new Date(run.started_at).toLocaleString('id-ID') : 'Belum dimulai' }}
                                </td>
                                <td class="px-4 py-3 text-right text-xs">
                                    <AppButton variant="secondary" size="compact" icon="terminal" @click="openLogModal(run)">
                                        Detail & Log Output
                                    </AppButton>
                                </td>
                            </tr>
                            <tr v-if="runs.data.length === 0">
                                <td colspan="7" class="px-4 py-8 text-center text-xs text-on-surface-variant">
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
            <div class="w-full max-w-4xl rounded-xl bg-surface-container-lowest shadow-2xl border border-outline-variant">
                <div class="flex items-center justify-between border-b border-outline-variant p-4">
                    <div>
                        <h3 class="text-lg font-bold text-primary">
                            Monitoring Cutover Run #{{ selectedRun.id }} ? {{ selectedRun.tenant_name }}
                        </h3>
                        <p class="text-xs text-on-surface-variant">Suffix: {{ selectedRun.suffix }} | Mode: {{ selectedRun.is_dry_run ? 'Dry Run' : 'Live' }}</p>
                    </div>
                    <AppButton variant="ghost" size="compact" icon="close" @click="closeLogModal" />
                </div>

                <div class="space-y-4 p-4">
                    <!-- Step Progress Badges -->
                    <div v-if="selectedRun.steps && selectedRun.steps.length > 0" class="space-y-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-primary">Progress Tahapan Migrasi:</span>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div v-for="step in selectedRun.steps" :key="step.name" class="rounded-lg border border-outline-variant p-2 text-xs">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium truncate text-primary" :title="step.label">{{ step.name }}</span>
                                    <span :class="['px-1.5 py-0.5 rounded text-[10px] uppercase font-bold', getStepStatusVariant(step.status)]">
                                        {{ step.status }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-on-surface-variant truncate">{{ step.label }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Output Console Terminal Window -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-primary">Terminal Console Log Output:</span>
                            <span v-if="selectedRun.status === 'running'" class="text-xs font-medium text-amber-500 animate-pulse">
                                Live Polling Output...
                            </span>
                        </div>
                        <pre class="h-80 w-full overflow-y-auto rounded-lg bg-slate-950 p-4 font-mono text-xs text-emerald-400 shadow-inner border border-slate-800 whitespace-pre-wrap">{{ selectedRun.output_log || 'Menunggu keluaran log...' }}</pre>
                    </div>

                    <div v-if="selectedRun.error_message" class="rounded-lg bg-error-container p-3 text-xs text-error">
                        <strong>Error Exception:</strong> {{ selectedRun.error_message }}
                    </div>
                </div>

                <div class="flex justify-end border-t border-outline-variant p-4">
                    <AppButton variant="secondary" @click="closeLogModal">Tutup Window Log</AppButton>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>


