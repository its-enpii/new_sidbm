<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const { can } = useCan();

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    period_label: { type: String, required: true },
    identity: { type: Object, required: true },
    products: { type: Array, required: true },
    villages: { type: Array, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, required: true },
});

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedProduct = ref(props.filters.product || 'all');

const monthOptions = [
    { value: '1', label: 'Januari' },
    { value: '2', label: 'Februari' },
    { value: '3', label: 'Maret' },
    { value: '4', label: 'April' },
    { value: '5', label: 'Mei' },
    { value: '6', label: 'Juni' },
    { value: '7', label: 'Juli' },
    { value: '8', label: 'Agustus' },
    { value: '9', label: 'September' },
    { value: '10', label: 'Oktober' },
    { value: '11', label: 'November' },
    { value: '12', label: 'Desember' },
];

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

const productOptions = computed(() => [
    { value: 'all', label: 'Semua Produk' },
    ...props.products.map((p) => ({ value: p.product_code, label: `${p.product_name} (${p.product_code})` })),
]);

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

function apply() {
    router.get(
        '/lending/reports/tidak-layak',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            product: selectedProduct.value,
        },
        { preserveState: true, replace: true },
    );
}

const pdfUrl = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value,
        month: selectedMonth.value,
        product: selectedProduct.value,
    });
    return `/lending/reports/tidak-layak/pdf?${q.toString()}`;
});

function statusTone(status) {
    return status === 'rejected' ? 'error-soft' : 'warning-soft';
}

function statusLabel(status) {
    return (
        {
            unfeasible: 'Tidak Layak',
            tidak_layak: 'Tidak Layak',
            rejected: 'Ditolak',
            draft: 'Proposal',
        }[status] || status
    );
}
</script>

<template>
    <Head title="Pinjaman Tidak Layak" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Laporan Pinjaman</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Pinjaman Tidak Layak (Kelompok)</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Daftar kelompok pemanfaat yang dinyatakan tidak layak didanai/dicairkan — {{ period_label }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a v-if="can('loans.view')" :href="pdfUrl" target="_blank" class="inline-flex">
                        <AppButton variant="outline" icon="picture_as_pdf">Cetak PDF</AppButton>
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <AppCard bordered>
                    <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Total Kelompok</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.groups_count }}</p>
                </AppCard>
                <AppCard bordered>
                    <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Total Pemanfaat</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ totals.members_count }}</p>
                </AppCard>
                <AppCard bordered>
                    <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Total Alokasi</p>
                    <p class="mt-2 text-2xl font-bold text-error">{{ formatMoney(totals.amount) }}</p>
                </AppCard>
            </div>

            <AppCard padded>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <SmartSelect
                        v-model="selectedYear"
                        label="Tahun"
                        :options="yearOptions"
                        @update:model-value="apply"
                    />
                    <SmartSelect
                        v-model="selectedMonth"
                        label="Bulan"
                        :options="monthOptions"
                        @update:model-value="apply"
                    />
                    <SmartSelect
                        v-model="selectedProduct"
                        label="Produk Pinjaman"
                        :options="productOptions"
                        @update:model-value="apply"
                    />
                </div>
            </AppCard>

            <AppEmptyState
                v-if="!villages.length"
                icon="cancel"
                title="Tidak ada pinjaman tidak layak"
                :description="`Tidak ditemukan kelompok tidak layak pada periode ${period_label}.`"
            />

            <div v-for="village in villages" :key="village.kode_desa" class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-on-surface">Desa {{ village.nama_desa }}</h2>
                        <p class="text-xs text-on-surface-variant">Kode Desa: {{ village.kode_desa }}</p>
                    </div>
                    <AppBadge tone="warning-soft">{{ village.subtotal.groups_count }} Kelompok</AppBadge>
                </div>

                <AppCard class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead class="bg-surface-container-low text-on-surface-variant">
                            <tr>
                                <th class="p-3 font-semibold">No</th>
                                <th class="p-3 font-semibold">Nama Kelompok</th>
                                <th class="p-3 font-semibold">Desa / Alamat</th>
                                <th class="p-3 font-semibold">Status</th>
                                <th class="p-3 font-semibold">Tanggal</th>
                                <th class="p-3 text-right font-semibold">Alokasi</th>
                                <th class="p-3 text-center font-semibold">Anggota</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-outline-variant/20">
                            <tr v-for="(loan, idx) in village.loans" :key="loan.loan_row_id" class="hover:bg-surface-container-low">
                                <td class="p-3 text-on-surface-variant">{{ idx + 1 }}</td>
                                <td class="p-3">
                                    <p class="font-semibold text-on-surface">{{ loan.group_name }}</p>
                                    <p class="text-[11px] text-on-surface-variant">#{{ loan.loan_number }}</p>
                                </td>
                                <td class="p-3 text-on-surface-variant">{{ loan.group_address || '-' }}</td>
                                <td class="p-3">
                                    <AppBadge :tone="statusTone(loan.status)">{{ statusLabel(loan.status) }}</AppBadge>
                                </td>
                                <td class="p-3 text-on-surface-variant">
                                    {{ loan.unfeasible_at || loan.waiting_since || '-' }}
                                </td>
                                <td class="p-3 text-right font-semibold text-error">{{ formatMoney(loan.amount) }}</td>
                                <td class="p-3 text-center">{{ loan.members_count }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-surface-container-low font-bold text-on-surface">
                            <tr class="border-t border-outline-variant/40">
                                <td colspan="5" class="p-3">Subtotal {{ village.nama_desa }}</td>
                                <td class="p-3 text-right text-error">{{ formatMoney(village.subtotal.amount) }}</td>
                                <td class="p-3 text-center">{{ village.subtotal.members_count }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </AppCard>
            </div>

            <AppCard v-if="villages.length" class="bg-surface-container-low">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Grand Total</p>
                        <p class="mt-1 text-sm text-on-surface">
                            {{ totals.groups_count }} kelompok · {{ totals.members_count }} pemanfaat
                        </p>
                    </div>
                    <p class="text-2xl font-bold text-error">{{ formatMoney(totals.amount) }}</p>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
