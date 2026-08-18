<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import TrendBarChart from '../../../Components/TrendBarChart.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useMoney } from '../../../composables/useMoney.js';

const props = defineProps({
    year: { type: Number, required: true },
    availableYears: { type: Array, default: () => [] },
    purposeFilter: { type: String, default: '' },
    months: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
    byPurpose: { type: Array, default: () => [] },
});

const { money } = useMoney();

const yearOptions = computed(() =>
    props.availableYears.map((y) => ({ value: String(y), label: String(y) })),
);

const purposeOptions = [
    { value: '', label: 'Semua keperluan' },
    { value: 'subscription', label: 'Langganan' },
    { value: 'setup', label: 'Setup' },
    { value: 'support', label: 'Support' },
    { value: 'training', label: 'Pelatihan' },
    { value: 'custom_dev', label: 'Custom dev' },
    { value: 'other', label: 'Lainnya' },
];

const purposeLabels = {
    subscription: 'Langganan',
    setup: 'Setup',
    support: 'Support',
    training: 'Pelatihan',
    custom_dev: 'Custom dev',
    other: 'Lainnya',
};

const chartData = computed(() =>
    props.months.map((row) => ({
        key: row.bulan,
        label: row.label,
        disbursed: row.total_tagihan,
        collected: row.total_terbayar,
    })),
);

function navigate(overrides) {
    const params = {
        year: overrides.year ?? props.year,
        purpose: (overrides.purpose ?? props.purposeFilter) || undefined,
    };
    router.get('/admin/revenue', params, { preserveState: true, replace: true });
}

function pctTerbayar(row) {
    if (!row.total_tagihan) return 0;
    return Math.round((row.total_terbayar / row.total_tagihan) * 100);
}
</script>

<template>
    <Head :title="`Pendapatan ${year}`" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Pendapatan Tahunan</h1>
                    <p class="mt-1 text-on-surface-variant">Rekapitulasi invoice &amp; pemasukan platform tahun {{ year }}.</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="min-w-36">
                        <SmartSelect
                            :model-value="String(year)"
                            label="Tahun"
                            hide-label
                            :options="yearOptions"
                            @update:model-value="(v) => navigate({ year: Number(v) })"
                        />
                    </div>
                    <div class="min-w-44">
                        <SmartSelect
                            :model-value="purposeFilter"
                            label="Keperluan"
                            hide-label
                            :options="purposeOptions"
                            @update:model-value="(v) => navigate({ purpose: v })"
                        />
                    </div>
                </div>
            </header>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Total Invoice</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ summary.total_invoice }}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ summary.lunas }} lunas · {{ summary.belum_lunas }} belum</p>
                </AppCard>
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Total Tagihan</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ money(summary.total_tagihan) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Sudah Terbayar</p>
                    <p class="mt-2 text-3xl font-bold text-secondary">{{ money(summary.total_terbayar) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Outstanding</p>
                    <p class="mt-2 text-3xl font-bold" :class="summary.outstanding > 0 ? 'text-error' : 'text-secondary'">{{ money(summary.outstanding) }}</p>
                </AppCard>
            </div>

            <AppCard>
                <template #header>
                    <h2 class="font-bold text-primary">Grafik Bulanan</h2>
                    <div class="flex items-center gap-4 text-xs text-on-surface-variant">
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-sm bg-primary" /> Tagihan</span>
                        <span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-sm bg-secondary" /> Terbayar</span>
                    </div>
                </template>
                <TrendBarChart :data="chartData" />
            </AppCard>

            <AppCard :padded="false">
                <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary">Rincian per Bulan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low text-left text-on-surface-variant">
                                <th class="whitespace-nowrap px-6 py-3">Bulan</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Invoice</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Tagihan</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Terbayar</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">% Bayar</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Lunas</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Belum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in months" :key="row.bulan" class="border-b border-outline-variant last:border-0 hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-6 py-3 font-semibold text-primary">{{ row.label }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ row.total_invoice }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ money(row.total_tagihan) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-secondary">{{ money(row.total_terbayar) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">
                                    <AppBadge :tone="pctTerbayar(row) >= 100 ? 'success' : pctTerbayar(row) >= 50 ? 'warning' : 'neutral'">{{ pctTerbayar(row) }}%</AppBadge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ row.lunas }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ row.belum_lunas }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-outline-variant bg-surface-container-low font-bold">
                                <td class="whitespace-nowrap px-6 py-3 text-primary">Total</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ summary.total_invoice }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ money(summary.total_tagihan) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-secondary">{{ money(summary.total_terbayar) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">
                                    <AppBadge :tone="summary.total_tagihan && summary.total_terbayar >= summary.total_tagihan ? 'success' : 'warning'">
                                        {{ summary.total_tagihan ? Math.round((summary.total_terbayar / summary.total_tagihan) * 100) : 0 }}%
                                    </AppBadge>
                                </td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ summary.lunas }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ summary.belum_lunas }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>

            <AppCard v-if="byPurpose.length" :padded="false">
                <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary">Breakdown per Keperluan</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-outline-variant bg-surface-container-low text-left text-on-surface-variant">
                                <th class="whitespace-nowrap px-6 py-3">Keperluan</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Jumlah</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Tagihan</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">Terbayar</th>
                                <th class="whitespace-nowrap px-6 py-3 text-right">% Kontribusi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in byPurpose" :key="row.purpose" class="border-b border-outline-variant last:border-0 hover:bg-surface-container-low/50">
                                <td class="whitespace-nowrap px-6 py-3 font-semibold text-primary">{{ purposeLabels[row.purpose] || row.purpose || '—' }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ row.jumlah }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">{{ money(row.total_tagihan) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums text-secondary">{{ money(row.total_terbayar) }}</td>
                                <td class="whitespace-nowrap px-6 py-3 text-right tabular-nums">
                                    {{ summary.total_tagihan ? Math.round((row.total_tagihan / summary.total_tagihan) * 100) : 0 }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
