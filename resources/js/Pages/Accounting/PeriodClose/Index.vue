<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
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
    can_close: { type: Boolean, default: false },
    year_options: { type: Array, required: true },
});

const selectedYear = ref(String(props.year));
const syncing = ref(false);
const forceRewrite = ref(false);

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
    const msg = props.next_year_openings_exist && !forceRewrite.value
        ? `Saldo awal ${props.next_year} dari tutup buku sudah ada. Centang "paksa tulis ulang" dulu, atau batalkan.`
        : `Tutup seluruh tahun ${props.year} dan bawa saldo neraca ke ${props.next_year}?`;
    if (props.next_year_openings_exist && !forceRewrite.value) {
        alert(msg);
        return;
    }
    if (!confirm(msg)) return;
    yearForm.year = props.year;
    yearForm.force = forceRewrite.value;
    yearForm.post('/accounting/period-close/year', { preserveScroll: true });
}

const statusTone = {
    open: 'success',
    closed: 'neutral',
    missing: 'warning',
};
const statusLabel = {
    open: 'Terbuka',
    closed: 'Ditutup',
    missing: 'Belum ada',
};

const previewDebit = computed(() =>
    props.year_end_preview.reduce((s, r) => s + Number(r.debit || 0), 0),
);
const previewCredit = computed(() =>
    props.year_end_preview.reduce((s, r) => s + Number(r.credit || 0), 0),
);
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
                        Tutup periode bulanan (blokir posting) dan bawa saldo neraca ke tahun berikutnya.
                        Alokasi laba multi-akun belum termasuk.
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

            <AppCard class="overflow-hidden p-0">
                <div class="border-b border-outline-variant px-4 py-3">
                    <h2 class="text-sm font-bold text-primary">Periode bulanan {{ year }}</h2>
                    <p class="text-xs text-on-surface-variant">
                        Periode tertutup menolak posting jurnal baru (sudah dicek di JournalPostingService).
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
                            <tr
                                v-for="m in months"
                                :key="m.month"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2 font-medium">{{ m.label }}</td>
                                <td class="px-3 py-2 text-on-surface-variant whitespace-nowrap">
                                    {{ m.starts_at || '—' }} → {{ m.ends_at || '—' }}
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
                                <td class="px-3 py-2 text-on-surface-variant whitespace-nowrap">
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
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="flex flex-col gap-3 border-b border-outline-variant px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-primary">Tutup tahun → saldo awal {{ next_year }}</h2>
                        <p class="text-xs text-on-surface-variant">
                            Akun neraca (aset/kewajiban/ekuitas) dibawa; pendapatan &amp; beban di-reset.
                            Laba/rugi tahun berjalan masuk pembuka akun {{ '3.2.02.01' }}.
                        </p>
                    </div>
                    <div v-if="can_close" class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-2 text-sm text-on-surface-variant">
                            <input v-model="forceRewrite" type="checkbox" class="rounded border-outline-variant" />
                            Paksa tulis ulang
                        </label>
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

                <div v-if="next_year_openings_exist" class="border-b border-outline-variant bg-surface-container-low/50 px-4 py-2 text-sm text-on-surface-variant">
                    Saldo awal {{ next_year }} dari tutup buku sudah ada.
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
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
                                    Tidak ada saldo neraca yang dibawa.
                                </td>
                            </tr>
                            <tr
                                v-for="row in year_end_preview"
                                :key="row.code"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2 font-mono text-xs">{{ row.code }}</td>
                                <td class="px-3 py-2">{{ row.name }}</td>
                                <td class="px-3 py-2 text-on-surface-variant">{{ row.account_type }}</td>
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
                                <td class="px-3 py-2" colspan="3">Jumlah preview</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(previewDebit) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ formatMoney(previewCredit) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
