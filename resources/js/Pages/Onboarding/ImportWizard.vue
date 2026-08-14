<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';
import AppCard from '../../Components/AppCard.vue';
import AppButton from '../../Components/AppButton.vue';
import AppBadge from '../../Components/AppBadge.vue';
import { useMoney } from '../../composables/useMoney';

const props = defineProps({
    accounts: { type: Array, required: true },
    existingOpening: { type: Object, default: null },
});

const { money: formatMoney } = useMoney();
const activeTab = ref('balances');

// Form Saldo Awal
const balanceLines = ref(
    props.accounts.map(acc => {
        const line = props.existingOpening?.lines?.find(l => l.account_row_id === acc.row_id);
        return {
            account_row_id: acc.row_id,
            code: acc.code,
            name: acc.name,
            account_type: acc.account_type,
            debit: line ? line.debit : 0,
            credit: line ? line.credit : 0,
        };
    })
);

const asOfDate = ref(props.existingOpening?.as_of_date || new Date().toISOString().split('T')[0]);

const balanceForm = useForm({
    as_of_date: asOfDate.value,
    lines: [],
});

const totalDebit = computed(() => {
    return balanceLines.value.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0);
});

const totalCredit = computed(() => {
    return balanceLines.value.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0);
});

const isBalanced = computed(() => {
    return Math.abs(totalDebit.value - totalCredit.value) < 0.01 && totalDebit.value > 0;
});

const submitOpeningBalances = () => {
    balanceForm.as_of_date = asOfDate.value;
    balanceForm.lines = balanceLines.value
        .filter(l => (parseFloat(l.debit) || 0) > 0 || (parseFloat(l.credit) || 0) > 0)
        .map(l => ({
            account_row_id: l.account_row_id,
            debit: parseFloat(l.debit) || 0,
            credit: parseFloat(l.credit) || 0,
        }));

    balanceForm.post('/onboarding/opening-balances', {
        preserveScroll: true,
    });
};

// Upload Forms
const memberFileForm = useForm({ file: null });
const groupFileForm = useForm({ file: null });
const loanFileForm = useForm({ file: null });

const uploadMembers = () => {
    if (!memberFileForm.file) return;
    memberFileForm.post('/membership/members/import', {
        preserveScroll: true,
        onSuccess: () => memberFileForm.reset(),
    });
};

const uploadGroups = () => {
    if (!groupFileForm.file) return;
    groupFileForm.post('/membership/groups/import', {
        preserveScroll: true,
        onSuccess: () => groupFileForm.reset(),
    });
};

const uploadLoans = () => {
    if (!loanFileForm.file) return;
    loanFileForm.post('/onboarding/active-loans', {
        preserveScroll: true,
        onSuccess: () => loanFileForm.reset(),
    });
};
</script>

<template>
    <Head title="Migrasi & Saldo Awal Tenant Baru" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header Banner -->
            <header class="rounded-2xl bg-gradient-to-r from-emerald-700 to-teal-800 p-6 text-white shadow-xl">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div>
                        <span class="inline-block rounded-full bg-emerald-500/30 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-emerald-200">
                            Pusat Onboarding Tenant Baru
                        </span>
                        <h1 class="mt-2 text-2xl font-bold">Migrasi Data & Saldo Awal Mandiri</h1>
                        <p class="mt-1 max-w-2xl text-sm text-emerald-100">
                            Impor neraca keuangan awal, daftar kelompok, keanggotaan, dan portofolio pinjaman aktif beserta akumulasi angsurannya.
                        </p>
                    </div>
                </div>
            </header>

            <!-- Navigation Tabs -->
            <div class="border-b border-slate-200 dark:border-slate-800">
                <nav class="-mb-px flex space-x-6">
                    <button
                        @click="activeTab = 'balances'"
                        :class="[
                            'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'balances'
                                ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
                        ]"
                    >
                        ?? 1. Saldo Awal Keuangan (Neraca)
                    </button>
                    <button
                        @click="activeTab = 'masterdata'"
                        :class="[
                            'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'masterdata'
                                ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
                        ]"
                    >
                        ?? 2. Impor Anggota & Kelompok
                    </button>
                    <button
                        @click="activeTab = 'loans'"
                        :class="[
                            'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'loans'
                                ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
                        ]"
                    >
                        ?? 3. Impor Pinjaman Aktif & Angsuran
                    </button>
                    <button
                        @click="activeTab = 'templates'"
                        :class="[
                            'py-3 px-1 border-b-2 font-medium text-sm transition-colors',
                            activeTab === 'templates'
                                ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400'
                        ]"
                    >
                        ?? 4. Template File Excel/CSV
                    </button>
                </nav>
            </div>

            <!-- TAB 1: Saldo Awal Keuangan -->
            <div v-if="activeTab === 'balances'" class="space-y-6">
                <AppCard>
                    <template #header>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div>
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Form Neraca Saldo Awal Akun COA</h2>
                                <p class="text-xs text-slate-500">Masukkan nilai saldo pada tanggal awal konversi operasional aplikasi.</p>
                            </div>
                            <div class="flex items-center space-x-3">
                                <label class="text-xs font-semibold text-slate-700 dark:text-slate-300">Tanggal Saldo Awal:</label>
                                <input
                                    type="date"
                                    v-model="asOfDate"
                                    class="rounded-lg border-slate-300 sm:text-xs dark:bg-slate-900"
                                />
                            </div>
                        </div>
                    </template>

                    <!-- Total Live Balance Tracker Banner -->
                    <div class="mb-6 rounded-xl border p-4 shadow-sm" :class="isBalanced ? 'bg-emerald-50 border-emerald-200 dark:bg-emerald-950/30' : 'bg-amber-50 border-amber-200 dark:bg-amber-950/30'">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-center space-x-4">
                                <div class="text-2xl">{{ isBalanced ? '?' : '??' }}</div>
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                                        Status Saldo Awal: {{ isBalanced ? 'SEIMBANG (BALANCED)' : 'BELUM SEIMBANG' }}
                                    </h3>
                                    <p class="text-xs text-slate-600 dark:text-slate-300">
                                        Total Debit: <strong>Rp {{ formatMoney(totalDebit) }}</strong> | Total Kredit: <strong>Rp {{ formatMoney(totalCredit) }}</strong>
                                    </p>
                                </div>
                            </div>
                            <div>
                                <AppBadge :variant="isBalanced ? 'success' : 'warning'">
                                    Selisih: Rp {{ formatMoney(Math.abs(totalDebit - totalCredit)) }}
                                </AppBadge>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Accounts Table -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                            <thead class="bg-slate-50 dark:bg-slate-800">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Kode Akun</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Nama Akun (COA)</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 dark:text-slate-300">Kategori</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300">Saldo Debit (Rp)</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-700 dark:text-slate-300">Saldo Kredit (Rp)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                                <tr v-for="line in balanceLines" :key="line.account_row_id" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                    <td class="px-4 py-2.5 text-xs font-mono font-bold text-slate-900 dark:text-white">{{ line.code }}</td>
                                    <td class="px-4 py-2.5 text-xs font-medium text-slate-800 dark:text-slate-200">{{ line.name }}</td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500 uppercase">{{ line.account_type }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <input
                                            type="number"
                                            v-model.number="line.debit"
                                            min="0"
                                            step="any"
                                            class="w-36 rounded-md border-slate-300 text-right sm:text-xs focus:ring-emerald-500 dark:bg-slate-900"
                                        />
                                    </td>
                                    <td class="px-4 py-2 text-right">
                                        <input
                                            type="number"
                                            v-model.number="line.credit"
                                            min="0"
                                            step="any"
                                            class="w-36 rounded-md border-slate-300 text-right sm:text-xs focus:ring-emerald-500 dark:bg-slate-900"
                                        />
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-100 dark:bg-slate-800 font-bold text-xs">
                                <tr>
                                    <td colspan="3" class="px-4 py-3 text-right text-slate-900 dark:text-white">TOTAL SALDO AWAL:</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 font-mono">Rp {{ formatMoney(totalDebit) }}</td>
                                    <td class="px-4 py-3 text-right text-emerald-600 font-mono">Rp {{ formatMoney(totalCredit) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="flex justify-end pt-6">
                        <AppButton
                            variant="primary"
                            @click="submitOpeningBalances"
                            :disabled="!isBalanced || balanceForm.processing"
                        >
                            <span v-if="balanceForm.processing">Memproses Saldo...</span>
                            <span v-else>?? Simpan & Posting Saldo Awal Keuangan</span>
                        </AppButton>
                    </div>
                </AppCard>
            </div>

            <!-- TAB 2: Impor Anggota & Kelompok -->
            <div v-if="activeTab === 'masterdata'" class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Impor Anggota -->
                <AppCard>
                    <template #header>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Impor Massal Anggota</h2>
                    </template>
                    <form @submit.prevent="uploadMembers" class="space-y-4">
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            Upload file CSV berisi daftar anggota lengkap (NIK, Nama, Jenis Kelamin, Alamat, Desa, Phone).
                        </p>
                        <div>
                            <input
                                type="file"
                                accept=".csv"
                                @change="e => memberFileForm.file = e.target.files[0]"
                                class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                            />
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <a :href="'/onboarding/templates/download/anggota'" class="text-xs text-emerald-600 hover:underline font-semibold">
                                ?? Download Template CSV Anggota
                            </a>
                            <AppButton type="submit" variant="primary" size="sm" :disabled="!memberFileForm.file || memberFileForm.processing">
                                Upload & Impor Anggota
                            </AppButton>
                        </div>
                    </form>
                </AppCard>

                <!-- Impor Kelompok -->
                <AppCard>
                    <template #header>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Impor Massal Kelompok</h2>
                    </template>
                    <form @submit.prevent="uploadGroups" class="space-y-4">
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            Upload file CSV daftar kelompok usaha/masyarakat (Nama Kelompok, Desa, Alamat, Telepon).
                        </p>
                        <div>
                            <input
                                type="file"
                                accept=".csv"
                                @change="e => groupFileForm.file = e.target.files[0]"
                                class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                            />
                        </div>
                        <div class="flex justify-between items-center pt-2">
                            <a :href="'/onboarding/templates/download/kelompok'" class="text-xs text-emerald-600 hover:underline font-semibold">
                                ?? Download Template CSV Kelompok
                            </a>
                            <AppButton type="submit" variant="primary" size="sm" :disabled="!groupFileForm.file || groupFileForm.processing">
                                Upload & Impor Kelompok
                            </AppButton>
                        </div>
                    </form>
                </AppCard>
            </div>

            <!-- TAB 3: Impor Pinjaman Aktif -->
            <div v-if="activeTab === 'loans'" class="space-y-6">
                <AppCard>
                    <template #header>
                        <h2 class="text-lg font-semibold text-slate-900 dark:text-white">Impor Portofolio Pinjaman Aktif & Akumulasi Realisasi Angsuran</h2>
                    </template>
                    <div class="space-y-4 text-xs text-slate-600 dark:text-slate-300">
                        <p>
                            Fitur ini digunakan untuk mengimpor pinjaman berjalan beserta <strong>akumulasi pokok & bunga yang sudah terbayar</strong> sampai hari ini. Sistem akan secara otomatis mengalokasikan angsuran FIFO pada jadwal bulanan dan menghitung sisa tagihan berjalan.
                        </p>

                        <div class="rounded-lg bg-emerald-50 p-4 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900 text-emerald-900 dark:text-emerald-200">
                            <h4 class="font-bold text-sm">?? Alokasi Otomatis Angsuran Terbayar:</h4>
                            <ul class="list-disc pl-5 mt-1 space-y-1">
                                <li>Nilai <code class="font-mono font-bold">akumulasi_pokok_dibayar</code> dan <code class="font-mono font-bold">akumulasi_bunga_dibayar</code> diisi total akumulasi pembayaran hingga saat migrasi.</li>
                                <li>Sistem membagi jadwal angsuran 1..N bulan dan mengisi status <span class="font-bold text-emerald-700 dark:text-emerald-300">Lunas (Paid)</span> untuk bulan-bulan yang sudah tercukupi.</li>
                                <li>Sisa pokok & sisa bunga yang belum terbayar akan otomatis masuk ke tagihan berjalan.</li>
                            </ul>
                        </div>

                        <form @submit.prevent="uploadLoans" class="space-y-4 pt-2">
                            <div>
                                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Pilih File CSV Pinjaman Aktif & Angsuran:</label>
                                <input
                                    type="file"
                                    accept=".csv"
                                    @change="e => loanFileForm.file = e.target.files[0]"
                                    class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-50 file:text-emerald-700 hover:file:bg-emerald-100"
                                />
                            </div>
                            <div class="flex justify-between items-center pt-2">
                                <a :href="'/onboarding/templates/download/pinjaman-aktif'" class="text-xs text-emerald-600 hover:underline font-semibold">
                                    ?? Download Format Template CSV Pinjaman Aktif (.csv)
                                </a>
                                <AppButton type="submit" variant="primary" size="sm" :disabled="!loanFileForm.file || loanFileForm.processing">
                                    Upload & Impor Pinjaman Aktif
                                </AppButton>
                            </div>
                        </form>
                    </div>
                </AppCard>
            </div>

            <!-- TAB 4: Download Seluruh Template -->
            <div v-if="activeTab === 'templates'" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <AppCard class="hover:border-emerald-500 transition-colors">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">1. Template Saldo Awal</h3>
                    <p class="text-xs text-slate-500 mt-1">Format neraca saldo awal akun COA debit/kredit.</p>
                    <a :href="'/onboarding/templates/download/saldo-awal'" class="mt-4 inline-block text-xs font-semibold text-emerald-600">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-emerald-500 transition-colors">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">2. Template Anggota</h3>
                    <p class="text-xs text-slate-500 mt-1">Format data NIK, Nama, Alamat, Desa, Phone.</p>
                    <a :href="'/onboarding/templates/download/anggota'" class="mt-4 inline-block text-xs font-semibold text-emerald-600">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-emerald-500 transition-colors">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">3. Template Kelompok</h3>
                    <p class="text-xs text-slate-500 mt-1">Format nama kelompok, alamat, desa, pengurus.</p>
                    <a :href="'/onboarding/templates/download/kelompok'" class="mt-4 inline-block text-xs font-semibold text-emerald-600">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-emerald-500 transition-colors">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">4. Template Pinjaman Aktif & Angsuran</h3>
                    <p class="text-xs text-slate-500 mt-1">Format portofolio pinjaman berjalan & akumulasi angsuran.</p>
                    <a :href="'/onboarding/templates/download/pinjaman-aktif'" class="mt-4 inline-block text-xs font-semibold text-emerald-600">?? Download CSV Template</a>
                </AppCard>

                <AppCard class="hover:border-emerald-500 transition-colors">
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">5. Template Aset Tetap Awal</h3>
                    <p class="text-xs text-slate-500 mt-1">Format barang inventaris, harga perolehan, depresiasi.</p>
                    <a :href="'/onboarding/templates/download/aset-tetap'" class="mt-4 inline-block text-xs font-semibold text-emerald-600">?? Download CSV Template</a>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>


