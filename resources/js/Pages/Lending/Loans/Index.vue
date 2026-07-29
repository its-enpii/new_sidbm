<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    loans: { type: Object, required: true },
    tab: { type: String, default: 'proposal' },
    columns: { type: Array, required: true },
    search: { type: String, default: '' },
    perPage: { type: [Number, String], default: 15 },
    sort: { type: String, default: '' },
    direction: { type: String, default: 'desc' },
});

const tabs = [
    { key: 'proposal', label: 'Proposal' },
    { key: 'verifikasi', label: 'Verifikasi' },
    { key: 'waiting', label: 'Waiting' },
    { key: 'aktif', label: 'Aktif' },
    { key: 'lunas', label: 'Lunas' },
];

function switchTab(tabKey) {
    router.get('/lending/loans', { tab: tabKey }, { preserveState: false });
}

function formatCurrency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value);
}

function formatServiceRate(value) {
    return `${Number(value ?? 0).toFixed(2)}%`;
}

function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
}

const emptyMessages = {
    proposal: { title: 'Belum ada proposal', description: 'Belum ada pengajuan pinjaman kelompok yang baru didaftarkan.' },
    verifikasi: { title: 'Belum ada verifikasi', description: 'Tidak ada pinjaman yang sedang menunggu verifikasi.' },
    waiting: { title: 'Belum ada waiting', description: 'Tidak ada pinjaman yang menunggu keputusan pendanaan.' },
    aktif: { title: 'Belum ada pinjaman aktif', description: 'Tidak ada pinjaman yang sedang aktif berjalan.' },
    lunas: { title: 'Belum ada pinjaman lunas', description: 'Tidak ada pinjaman yang telah dilunasi.' },
};
</script>

<template>
    <Head title="Tahapan Perguliran" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Tahapan Perguliran</h1>
                    <p class="mt-1 text-on-surface-variant">Pantau pergerakan pinjaman dari pengajuan hingga pelunasan.</p>
                </div>
                <Link href="/lending/loans/create"><AppButton icon="add">Register Proposal</AppButton></Link>
            </header>

            <div class="border-b border-outline-variant">
                <nav class="-mb-px flex gap-6" aria-label="Tabs Status Pinjaman">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        class="whitespace-nowrap border-b-2 py-4 px-1 text-sm font-semibold transition-colors duration-150"
                        :class="tab === t.key ? 'border-primary text-primary' : 'border-transparent text-on-surface-variant hover:border-outline hover:text-primary'"
                        @click="switchTab(t.key)"
                    >
                        {{ t.label }}
                    </button>
                </nav>
            </div>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="loans.data"
                        :columns="columns"
                        :pagination="loans"
                        :url="`/lending/loans?tab=${tab}`"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari pinjaman"
                        search-placeholder="Cari nama kelompok atau desa"
                        :empty-title="emptyMessages[tab]?.title || 'Belum ada data pinjaman'"
                        :empty-description="emptyMessages[tab]?.description || 'Tidak ditemukan data pinjaman untuk status ini.'"
                    >
                        <template #cell-group_name="{ row }">
                            <div class="font-semibold text-primary">{{ row.group_name }}</div>
                            <div v-if="row.group_address" class="mt-0.5 text-xs text-on-surface-variant">{{ row.group_address }}</div>
                            <div class="mt-0.5 text-[10px] uppercase tracking-wider text-outline">#{{ row.id }} · {{ row.product?.code || '—' }}</div>
                        </template>
                        <template #cell-proposed_at="{ row }">{{ formatDate(row.proposed_at) }}</template>
                        <template #cell-verified_at="{ row }">{{ formatDate(row.verified_at) }}</template>
                        <template #cell-funded_at="{ row }">{{ formatDate(row.funded_at) }}</template>
                        <template #cell-disbursed_at="{ row }">{{ formatDate(row.disbursed_at) }}</template>
                        <template #cell-completed_at="{ row }">{{ formatDate(row.completed_at) }}</template>
                        <template #cell-next_due_date="{ row }">{{ formatDate(row.next_due_date) }}</template>
                        <template #cell-proposed_amount="{ row }">
                            <span class="font-semibold text-primary">{{ row.proposed_amount !== null ? formatCurrency(row.proposed_amount) : '—' }}</span>
                        </template>
                        <template #cell-verification_amount="{ row }">
                            <span class="font-semibold text-primary">{{ row.verification_amount !== null ? formatCurrency(row.verification_amount) : '—' }}</span>
                        </template>
                        <template #cell-allocated_amount="{ row }">
                            <span class="font-semibold text-primary">{{ row.allocated_amount !== null ? formatCurrency(row.allocated_amount) : '—' }}</span>
                        </template>
                        <template #cell-principal_remaining="{ row }">{{ formatCurrency(row.principal_remaining) }}</template>
                        <template #cell-total_interest_paid="{ row }">{{ formatCurrency(row.total_interest_paid) }}</template>
                        <template #cell-service_rate="{ row }">
                            <span class="font-semibold">{{ formatServiceRate(row.service_rate) }}</span>
                        </template>
                        <template #cell-term_months="{ row }">{{ row.term_months || 0 }} bln</template>
                        <template #cell-beneficiaries_count="{ row }">
                            <span class="inline-flex items-center gap-1 rounded-full bg-primary-fixed-dim/10 px-2 py-0.5 text-xs font-semibold text-primary">{{ row.beneficiaries_count }}</span>
                        </template>
                        <template #cell-actions="{ row }">
                            <Link :href="`/lending/loans/${row.row_id}`" class="inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-bold text-primary hover:bg-primary/10">Detail →</Link>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
