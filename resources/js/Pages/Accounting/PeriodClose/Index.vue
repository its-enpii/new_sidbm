<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    months: { type: Array, required: true },
    open_count: { type: Number, required: true },
    closed_count: { type: Number, required: true },
    draft_journals: { type: Number, required: true },
    next_year: { type: Number, required: true },
    next_year_openings_exist: { type: Boolean, required: true },
    can_close_year: { type: Boolean, required: true },
    year_end_preview: { type: Array, required: true },
    net_income: { type: Number, required: true },
    allocation: { type: Object, required: true },
    can_close: { type: Boolean, default: false },
    year_options: { type: Array, required: true },
});

const selectedYear = ref(String(props.year));
const syncing = ref(false);
const forceRewrite = ref(false);

/** periods | year | allocate */
const tab = ref('periods');
const tabs = [
    { key: 'periods', label: '1. Periode', short: 'Periode' },
    { key: 'year', label: '2. Saldo awal', short: 'Saldo awal' },
    { key: 'allocate', label: '3. Alokasi laba', short: 'Alokasi' },
];

watch(
    () => props.year,
    (y) => {
        syncing.value = true;
        selectedYear.value = String(y);
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
);

watch(selectedYear, (value) => {
    if (syncing.value) return;
    const year = Number(value);
    if (!Number.isFinite(year) || year === props.year) return;
    router.get('/accounting/period-close', { year }, { preserveState: false, replace: true });
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

const yearForm = useForm({ year: props.year, force: false });

function closeMonth(month) {
    if (!props.can_close) return;
    if (!confirm(`Tutup periode ${String(month).padStart(2, '0')}/${props.year}? Jurnal draft harus kosong.`)) return;
    router.post(`/accounting/period-close/${props.year}/${month}/close`, {}, { preserveScroll: true });
}

function reopenMonth(month) {
    if (!props.can_close) return;
    if (!confirm(`Buka kembali periode ${String(month).padStart(2, '0')}/${props.year}?`)) return;
    router.post(`/accounting/period-close/${props.year}/${month}/reopen`, {}, { preserveScroll: true });
}

function closeYear() {
    if (!props.can_close) return;
    const msg =
        props.next_year_openings_exist && !forceRewrite.value
            ? `Saldo awal ${props.next_year} dari tutup buku sudah ada. Aktifkan "Paksa tulis ulang" dulu, atau batalkan.`
            : `Tutup seluruh tahun ${props.year} dan tulis saldo awal ${props.next_year}?`;
    if (props.next_year_openings_exist && !forceRewrite.value) {
        alert(msg);
        return;
    }
    if (!confirm(msg)) return;
    yearForm.year = props.year;
    yearForm.force = forceRewrite.value;
    yearForm.post('/accounting/period-close/year', { preserveScroll: true });
}

const statusTone = { open: 'success', closed: 'neutral', missing: 'warning' };
const statusLabel = { open: 'Terbuka', closed: 'Ditutup', missing: 'Belum ada' };

const previewDebit = computed(() => props.year_end_preview.reduce((s, r) => s + Number(r.debit || 0), 0));
const previewCredit = computed(() => props.year_end_preview.reduce((s, r) => s + Number(r.credit || 0), 0));

function emptyCommunity() {
    const o = {};
    for (const line of props.allocation.community_lines || []) o[line.key] = '';
    return o;
}
function emptyVillages() {
    const o = {};
    for (const v of props.allocation.villages || []) o[v.row_id] = '';
    return o;
}

const allocForm = useForm({
    year: props.year,
    date: props.allocation.default_date,
    community: emptyCommunity(),
    villages: emptyVillages(),
    investor: '',
    retained: '',
    note: '',
});

watch(
    () => props.allocation,
    (a) => {
        allocForm.defaults({
            year: props.year,
            date: a.default_date,
            community: emptyCommunity(),
            villages: emptyVillages(),
            investor: '',
            retained: '',
            note: '',
        });
        allocForm.reset();
        allocForm.year = props.year;
        allocForm.date = a.default_date;
    },
);

function n(v) {
    const x = Number(v);
    return Number.isFinite(x) && x > 0 ? x : 0;
}

const communityTotal = computed(() =>
    Object.values(allocForm.community).reduce((s, v) => s + n(v), 0),
);
const villageTotal = computed(() => Object.values(allocForm.villages).reduce((s, v) => s + n(v), 0));
const allocTotal = computed(
    () => communityTotal.value + villageTotal.value + n(allocForm.investor) + n(allocForm.retained),
);
const allocOver = computed(() => allocTotal.value - Number(props.allocation.remaining || 0) > 0.009);

function fillRetained() {
    const rest = Math.max(
        0,
        Math.round(
            Number(props.allocation.remaining || 0) -
                communityTotal.value -
                villageTotal.value -
                n(allocForm.investor),
        ),
    );
    allocForm.retained = rest > 0 ? rest : '';
}

function formatPeriodDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

const typeLabels = {
    asset: 'Aset',
    liability: 'Kewajiban',
    equity: 'Ekuitas',
    revenue: 'Pendapatan',
    expense: 'Beban',
};

function submitAllocation() {
    if (!props.can_close) return;
    if (allocTotal.value <= 0) {
        alert('Isi minimal satu pos alokasi.');
        return;
    }
    if (allocOver.value) {
        alert('Total alokasi melebihi sisa laba.');
        return;
    }
    if (!confirm(`Simpan alokasi laba ${props.year} total ${formatMoney(allocTotal.value)}?`)) return;
    allocForm.year = props.year;
    allocForm.post('/accounting/period-close/allocate', { preserveScroll: true });
}
</script>

<template>
    <Head title="Tutup Buku" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Keuangan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Tutup Buku</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Tutup periode, bawa saldo akun ke tahun depan, lalu alokasi laba — satu langkah per tab.
                    </p>
                </div>
                <div class="w-40">
                    <SmartSelect
                        id="period-close-year"
                        label="Tahun buku"
                        :model-value="Number(selectedYear)"
                        :options="year_options"
                        @update:model-value="(v) => (selectedYear = String(v))"
                    />
                </div>
            </header>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Terbuka</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ open_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Ditutup</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ closed_count }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jurnal draft</p>
                    <p class="mt-2 text-2xl font-bold" :class="draft_journals > 0 ? 'text-error' : 'text-primary'">
                        {{ draft_journals }}
                    </p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Laba/rugi {{ year }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="net_income < 0 ? 'text-error' : 'text-primary'">
                        {{ formatMoney(net_income) }}
                    </p>
                </AppCard>
            </div>

            <!-- Step tabs -->
            <div class="flex flex-wrap gap-2 border-b border-outline-variant pb-3">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    type="button"
                    class="rounded-xl border px-3 py-2 text-sm font-semibold transition sm:px-4"
                    :class="
                        tab === t.key
                            ? 'border-primary bg-primary text-on-primary'
                            : 'border-outline-variant bg-surface-container-lowest text-primary hover:border-primary/40'
                    "
                    @click="tab = t.key"
                >
                    <span class="sm:hidden">{{ t.short }}</span>
                    <span class="hidden sm:inline">{{ t.label }}</span>
                </button>
            </div>

            <!-- TAB 1: periods -->
            <AppCard v-show="tab === 'periods'" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3">
                    <h2 class="text-sm font-bold text-primary">Periode bulanan {{ year }}</h2>
                    <p class="text-xs text-on-surface-variant">
                        Periode tertutup menolak posting jurnal baru.
                    </p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Bulan</th>
                                <th class="px-3 py-2 text-left">Rentang</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-right">Draft</th>
                                <th class="px-3 py-2 text-left">Ditutup</th>
                                <th class="px-3 py-2 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="m in months" :key="m.month" class="border-t border-outline-variant/40">
                                <td class="px-3 py-2 font-medium">{{ m.label }}</td>
                                <td class="px-3 py-2 whitespace-nowrap text-on-surface-variant">
                                    {{ formatPeriodDate(m.starts_at) }} – {{ formatPeriodDate(m.ends_at) }}
                                </td>
                                <td class="px-3 py-2">
                                    <AppBadge :tone="statusTone[m.status] || 'neutral'">
                                        {{ statusLabel[m.status] || m.status }}
                                    </AppBadge>
                                </td>
                                <td
                                    class="px-3 py-2 text-right tabular-nums"
                                    :class="m.draft_journals > 0 ? 'font-semibold text-error' : ''"
                                >
                                    {{ m.draft_journals }}
                                </td>
                                <td class="px-3 py-2 whitespace-nowrap text-on-surface-variant">
                                    {{ m.closed_at || '—' }}
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div v-if="can_close" class="flex justify-end gap-1">
                                        <AppButton
                                            v-if="m.can_close"
                                            variant="secondary"
                                            size="compact"
                                            @click="closeMonth(m.month)"
                                        >
                                            Tutup
                                        </AppButton>
                                        <AppButton
                                            v-if="m.can_reopen"
                                            variant="ghost"
                                            size="compact"
                                            @click="reopenMonth(m.month)"
                                        >
                                            Buka
                                        </AppButton>
                                    </div>
                                    <span v-else class="text-xs text-on-surface-variant">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex justify-end border-t border-outline-variant px-4 py-3">
                    <AppButton variant="secondary" size="compact" @click="tab = 'year'">
                        Lanjut: saldo awal →
                    </AppButton>
                </div>
            </AppCard>

            <!-- TAB 2: year close -->
            <AppCard v-show="tab === 'year'" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3 sm:flex sm:items-start sm:justify-between sm:gap-4">
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-primary">
                            Tutup tahun {{ year }} → saldo awal {{ next_year }}
                        </h2>
                        <p class="mt-1 text-xs text-on-surface-variant">
                            Preview akun aset/kewajiban/ekuitas yang akan jadi saldo awal tahun
                            {{ next_year }}. Pendapatan &amp; beban di-nolkan; laba/rugi ke 3.2.02.01.
                            Ini bukan laporan Neraca — lihat
                            <a href="/accounting/reports/balance-sheet" class="font-semibold text-primary hover:underline">Pelaporan → Neraca</a>.
                        </p>
                    </div>
                    <div v-if="can_close" class="mt-3 flex shrink-0 flex-col gap-2 sm:mt-0 sm:items-end">
                        <AppSwitch
                            v-if="next_year_openings_exist"
                            v-model="forceRewrite"
                            label="Paksa tulis ulang"
                            description="Timpa saldo awal tahun berikutnya"
                            class="min-w-[16rem]"
                        />
                        <AppButton
                            variant="primary"
                            size="compact"
                            :loading="yearForm.processing"
                            :disabled="!can_close_year || yearForm.processing"
                            @click="closeYear"
                        >
                            Tutup tahun {{ year }}
                        </AppButton>
                    </div>
                </div>

                <div
                    v-if="next_year_openings_exist"
                    class="border-b border-outline-variant bg-surface-container-low/50 px-4 py-2 text-sm text-on-surface-variant"
                >
                    Saldo awal {{ next_year }} dari tutup buku sudah ada.
                </div>

                <div class="max-h-[28rem] overflow-auto">
                    <table class="min-w-full text-sm">
                        <thead class="sticky top-0 z-10 bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Kode</th>
                                <th class="px-3 py-2 text-left">Akun</th>
                                <th class="px-3 py-2 text-left">Tipe</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Kredit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="year_end_preview.length === 0">
                                <td colspan="5" class="px-3 py-8 text-center text-on-surface-variant">
                                    Tidak ada saldo akun yang dibawa ke tahun berikutnya.
                                </td>
                            </tr>
                            <tr
                                v-for="row in year_end_preview"
                                :key="row.code"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2 text-on-surface-variant">
                                    {{ typeLabels[row.account_type] || row.account_type }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ row.debit ? formatMoney(row.debit) : '—' }}
                                </td>
                                <td class="px-3 py-2 text-right tabular-nums">
                                    {{ row.credit ? formatMoney(row.credit) : '—' }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="year_end_preview.length">
                            <tr class="border-t-2 border-outline bg-surface-container-low font-semibold">
                                <td class="px-3 py-2" colspan="3">Jumlah preview saldo awal</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(previewDebit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(previewCredit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="flex flex-wrap justify-between gap-2 border-t border-outline-variant px-4 py-3">
                    <AppButton variant="ghost" size="compact" @click="tab = 'periods'">← Periode</AppButton>
                    <AppButton variant="secondary" size="compact" @click="tab = 'allocate'">
                        Lanjut: alokasi laba →
                    </AppButton>
                </div>
            </AppCard>

            <!-- TAB 3: allocate -->
            <AppCard v-show="tab === 'allocate'" class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3">
                    <h2 class="text-sm font-bold text-primary">Alokasi laba tahun {{ year }}</h2>
                    <p class="text-xs text-on-surface-variant">
                        Dr {{ allocation.accounts?.earnings?.code || '3.2.02.01' }} → Cr utang laba / laba ditahan.
                        Tanggal biasanya 1 Jan tahun berikutnya (periode harus terbuka).
                    </p>
                </div>

                <div v-if="allocation.error" class="px-4 py-6 text-sm text-error">{{ allocation.error }}</div>

                <template v-else>
                    <div class="grid gap-3 border-b border-outline-variant p-4 sm:grid-cols-3">
                        <div>
                            <p class="text-xs font-bold uppercase text-on-surface-variant">Laba tersedia</p>
                            <p class="mt-1 text-xl font-bold text-primary">{{ formatMoney(allocation.available) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-on-surface-variant">Sudah dialokasi</p>
                            <p class="mt-1 text-xl font-bold">{{ formatMoney(allocation.already_allocated) }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase text-on-surface-variant">Sisa</p>
                            <p class="mt-1 text-xl font-bold" :class="allocation.remaining > 0 ? 'text-primary' : ''">
                                {{ formatMoney(allocation.remaining) }}
                            </p>
                        </div>
                    </div>

                    <div v-if="allocation.existing?.length" class="border-b border-outline-variant px-4 py-3">
                        <p class="mb-2 text-xs font-bold uppercase text-on-surface-variant">Jurnal alokasi sebelumnya</p>
                        <ul class="space-y-1 text-sm">
                            <li v-for="e in allocation.existing" :key="e.row_id">
                                <Link :href="e.href" class="font-semibold text-primary hover:underline">
                                    #{{ e.id }}
                                </Link>
                                <span class="text-on-surface-variant">
                                    · {{ e.transaction_date }} · {{ e.description }}
                                </span>
                            </li>
                        </ul>
                    </div>

                    <form
                        v-if="can_close && allocation.remaining > 0"
                        class="space-y-6 p-4 sm:p-5"
                        @submit.prevent="submitAllocation"
                    >
                        <div class="grid gap-4 sm:grid-cols-2">
                            <AppDatePicker v-model="allocForm.date" mode="day" label="Tanggal alokasi" />
                            <AppInput
                                v-model="allocForm.note"
                                label="Keterangan (opsional)"
                                placeholder="Alokasi laba …"
                            />
                        </div>

                        <section class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4">
                            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                        {{ allocation.accounts.community.code }}
                                    </p>
                                    <h3 class="text-sm font-bold text-primary">Bagian masyarakat</h3>
                                </div>
                                <p class="text-sm tabular-nums text-on-surface-variant">
                                    Σ <span class="font-semibold text-primary">{{ formatMoney(communityTotal) }}</span>
                                </p>
                            </div>
                            <div class="divide-y divide-outline-variant/50">
                                <div
                                    v-for="line in allocation.community_lines"
                                    :key="line.key"
                                    class="grid gap-3 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_16rem] sm:items-center"
                                >
                                    <p class="text-sm leading-snug text-on-surface">{{ line.label }}</p>
                                    <AppCurrencyInput
                                        v-model="allocForm.community[line.key]"
                                        :label="`Jumlah ${line.label}`"
                                        hide-label
                                        :min="0"
                                        :step="1000"
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            v-if="allocation.villages?.length"
                            class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4"
                        >
                            <div class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                                <div>
                                    <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                        {{ allocation.accounts.village.code }}
                                    </p>
                                    <h3 class="text-sm font-bold text-primary">Bagian desa</h3>
                                </div>
                                <p class="text-sm tabular-nums text-on-surface-variant">
                                    Σ <span class="font-semibold text-primary">{{ formatMoney(villageTotal) }}</span>
                                </p>
                            </div>
                            <div class="max-h-80 divide-y divide-outline-variant/50 overflow-y-auto pr-1">
                                <div
                                    v-for="v in allocation.villages"
                                    :key="v.row_id"
                                    class="grid gap-3 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_16rem] sm:items-center"
                                >
                                    <p class="text-sm font-medium text-on-surface">{{ v.name }}</p>
                                    <AppCurrencyInput
                                        v-model="allocForm.villages[v.row_id]"
                                        :label="`Jumlah ${v.name}`"
                                        hide-label
                                        :min="0"
                                        :step="1000"
                                        placeholder="0"
                                    />
                                </div>
                            </div>
                        </section>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <section class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4">
                                <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                    {{ allocation.accounts.investor.code }}
                                </p>
                                <h3 class="mb-3 text-sm font-bold text-primary">Penyerta modal</h3>
                                <AppCurrencyInput
                                    v-model="allocForm.investor"
                                    label="Jumlah penyerta modal"
                                    hide-label
                                    :min="0"
                                    :step="1000"
                                    placeholder="0"
                                />
                            </section>
                            <section class="rounded-2xl border border-outline-variant/70 bg-surface-container-low/30 p-4">
                                <div class="mb-3 flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-mono text-[11px] font-semibold tracking-wide text-on-surface-variant">
                                            {{ allocation.accounts.retained.code }}
                                        </p>
                                        <h3 class="text-sm font-bold text-primary">Laba ditahan</h3>
                                    </div>
                                    <button
                                        type="button"
                                        class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/5"
                                        @click="fillRetained"
                                    >
                                        Isi sisa
                                    </button>
                                </div>
                                <AppCurrencyInput
                                    v-model="allocForm.retained"
                                    label="Jumlah laba ditahan"
                                    hide-label
                                    :min="0"
                                    :step="1000"
                                    placeholder="0"
                                />
                            </section>
                        </div>

                        <div
                            class="flex flex-col gap-3 rounded-2xl border border-outline-variant bg-surface-container-low/40 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="text-sm">
                                <p class="text-on-surface-variant">
                                    Total alokasi
                                    <span class="ml-1 text-lg font-bold tabular-nums text-primary">
                                        {{ formatMoney(allocTotal) }}
                                    </span>
                                </p>
                                <p
                                    class="mt-0.5 text-xs"
                                    :class="allocOver ? 'font-semibold text-error' : 'text-on-surface-variant'"
                                >
                                    <template v-if="allocOver">
                                        Melebihi sisa {{ formatMoney(allocation.remaining) }}
                                    </template>
                                    <template v-else>
                                        Sisa setelah alokasi
                                        {{ formatMoney(Math.max(0, Number(allocation.remaining || 0) - allocTotal)) }}
                                    </template>
                                </p>
                            </div>
                            <AppButton
                                type="submit"
                                variant="primary"
                                :loading="allocForm.processing"
                                :disabled="allocForm.processing || allocTotal <= 0 || allocOver"
                            >
                                Simpan alokasi
                            </AppButton>
                        </div>
                    </form>

                    <p
                        v-else-if="can_close && allocation.remaining <= 0"
                        class="px-4 py-6 text-sm text-on-surface-variant"
                    >
                        Tidak ada sisa laba untuk dialokasi
                        <span v-if="allocation.already_allocated > 0">
                            (sudah {{ formatMoney(allocation.already_allocated) }}).
                        </span>
                        <span v-else-if="allocation.available <= 0">(laba/rugi ≤ 0).</span>
                    </p>
                </template>

                <div class="flex justify-start border-t border-outline-variant px-4 py-3">
                    <AppButton variant="ghost" size="compact" @click="tab = 'year'">← Saldo awal</AppButton>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
