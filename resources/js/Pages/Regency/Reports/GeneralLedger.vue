<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import RegencyLayout from '../../../Layouts/RegencyLayout.vue';

const props = defineProps({
    report: { type: Object, required: true },
    year: { type: Number, required: true },
    month: { type: [Number, String], default: '' },
    day: { type: String, default: '' },
    selected_tenant_id: { type: [Number, String], default: '' },
    selected_account_id: { type: [Number, String], default: '' },
    regency_name: { type: String, default: 'Kabupaten' },
});

const selectedYear = ref(props.year);
const selectedMonth = ref(props.month || '');
const selectedDay = ref(props.day || '');
const selectedTenant = ref(props.selected_tenant_id || '');
const selectedAccountId = ref(props.selected_account_id || props.report.account?.row_id || '');

const monthNames = [
    'Semua Bulan (Tahunan)',
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

function money(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value || 0));
}

function applyFilter() {
    router.get('/regency/reports/general-ledger', {
        year: selectedYear.value,
        month: selectedMonth.value || '',
        day: selectedDay.value || undefined,
        tenant_id: selectedTenant.value || '',
        account_id: selectedAccountId.value || '',
    }, { preserveState: true });
}

function downloadPdf() {
    const params = new URLSearchParams({
        year: String(selectedYear.value),
        month: String(selectedMonth.value || ''),
        tenant_id: String(selectedTenant.value || ''),
        account_id: String(selectedAccountId.value || ''),
    });
    window.open(`/regency/reports/general-ledger/pdf?${params.toString()}`, '_blank');
}
</script>

<template>
    <Head :title="`Buku Besar Konsolidasi - ${regency_name}`" />
    <RegencyLayout>
        <div class="space-y-6">
            <!-- Header & Filter -->
            <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Buku Besar Kabupaten {{ regency_name }}</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        {{ report.account?.code }} - {{ report.account?.name }} · {{ report.period?.period_label }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <!-- Akun Selector -->
                    <select
                        v-model="selectedAccountId"
                        class="max-w-xs rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface shadow-sm focus:border-primary focus:outline-none"
                        @change="applyFilter"
                    >
                        <option v-for="acc in report.accounts" :key="acc.row_id" :value="acc.row_id">
                            {{ acc.code }} - {{ acc.name }}
                        </option>
                    </select>

                    <select
                        v-model="selectedTenant"
                        class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface shadow-sm focus:border-primary focus:outline-none"
                        @change="applyFilter"
                    >
                        <option value="">Semua Kecamatan (Gabungan)</option>
                        <option v-for="kec in report.kecamatans" :key="kec.id" :value="kec.id">
                            {{ kec.name }}
                        </option>
                    </select>

                    <select
                        v-model="selectedMonth"
                        class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface shadow-sm focus:border-primary focus:outline-none"
                        @change="applyFilter"
                    >
                        <option v-for="(name, idx) in monthNames" :key="idx" :value="idx === 0 ? '' : idx">
                            {{ name }}
                        </option>
                    </select>

                    <select
                        v-model.number="selectedYear"
                        class="rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface shadow-sm focus:border-primary focus:outline-none"
                        @change="applyFilter"
                    >
                        <option v-for="y in [2024, 2025, 2026, 2027]" :key="y" :value="y">{{ y }}</option>
                    </select>

                    <AppButton variant="secondary" icon="picture_as_pdf" @click="downloadPdf">Cetak PDF</AppButton>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid gap-4 sm:grid-cols-4">
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Saldo Awal</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.opening_balance) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Total Debit</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.total_debit) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Total Kredit</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.total_credit) }}</p>
                </AppCard>
                <AppCard>
                    <span class="text-sm font-semibold text-on-surface-variant">Saldo Akhir</span>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ money(report.closing_balance) }}</p>
                </AppCard>
            </div>

            <!-- Table Entries -->
            <AppCard :padded="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase text-on-surface-variant">
                            <tr>
                                <th class="px-4 py-3">Tanggal</th>
                                <th class="px-4 py-3">No. Jurnal</th>
                                <th v-if="report.is_consolidated" class="px-4 py-3">Kecamatan</th>
                                <th class="px-4 py-3">Keterangan</th>
                                <th class="px-4 py-3 text-right">Debit</th>
                                <th class="px-4 py-3 text-right">Kredit</th>
                                <th class="px-4 py-3 text-right">Saldo</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant">
                            <tr class="bg-surface-container-low/40 font-semibold">
                                <td class="px-4 py-2 text-xs font-mono">{{ report.period?.from }}</td>
                                <td class="px-4 py-2">—</td>
                                <td v-if="report.is_consolidated" class="px-4 py-2">—</td>
                                <td class="px-4 py-2">Saldo Awal Periode</td>
                                <td class="px-4 py-2 text-right">—</td>
                                <td class="px-4 py-2 text-right">—</td>
                                <td class="px-4 py-2 text-right font-bold text-primary">{{ money(report.opening_balance) }}</td>
                            </tr>
                            <tr
                                v-for="(entry, idx) in report.entries"
                                :key="idx"
                                class="hover:bg-surface-container-low/40"
                            >
                                <td class="px-4 py-2 text-xs font-mono text-on-surface-variant">{{ entry.date }}</td>
                                <td class="px-4 py-2 text-xs font-mono">{{ entry.voucher_number }}</td>
                                <td v-if="report.is_consolidated" class="px-4 py-2 text-xs font-semibold text-primary">{{ entry.kecamatan_name }}</td>
                                <td class="px-4 py-2 text-on-surface">{{ entry.description }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ entry.debit ? money(entry.debit) : '—' }}</td>
                                <td class="px-4 py-2 text-right tabular-nums">{{ entry.credit ? money(entry.credit) : '—' }}</td>
                                <td class="px-4 py-2 text-right font-medium text-primary">{{ money(entry.balance) }}</td>
                            </tr>
                            <tr v-if="!report.entries?.length">
                                <td :colspan="report.is_consolidated ? 7 : 6" class="px-6 py-8 text-center text-on-surface-variant">
                                    Tidak ada mutasi transaksi pada akun dan periode ini.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-container-low font-bold text-primary">
                            <tr>
                                <td class="px-4 py-3" :colspan="report.is_consolidated ? 4 : 3">TOTAL MUTASI PERIODE</td>
                                <td class="px-4 py-3 text-right">{{ money(report.total_debit) }}</td>
                                <td class="px-4 py-3 text-right">{{ money(report.total_credit) }}</td>
                                <td class="px-4 py-3 text-right text-base">{{ money(report.closing_balance) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
