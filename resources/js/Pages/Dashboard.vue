<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../Components/AppBadge.vue';
import AppButton from '../Components/AppButton.vue';
import AppCard from '../Components/AppCard.vue';
import AppEmptyState from '../Components/AppEmptyState.vue';
import AppIcon from '../Components/AppIcon.vue';
import AuthenticatedLayout from '../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    unitName: { type: String, default: null },
    as_of: { type: String, required: true },
    cards: { type: Array, required: true },
    pipeline: { type: Array, required: true },
    trend: { type: Array, required: true },
    recent_journals: { type: Array, required: true },
    upcoming_due: { type: Array, required: true },
    overdue_summary: { type: Object, required: true },
    counts: { type: Object, required: true },
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
const numberFmt = new Intl.NumberFormat('id-ID');

function formatValue(card) {
    if (card.format === 'money') return money.format(Math.round(Number(card.value || 0)));
    return numberFmt.format(Number(card.value || 0));
}

function formatMoney(value) {
    return money.format(Math.round(Number(value || 0)));
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(`${value}T00:00:00`);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' });
}

const trendMax = computed(() => {
    let max = 0;
    for (const row of props.trend) {
        max = Math.max(max, Number(row.disbursed || 0), Number(row.collected || 0));
    }
    return max || 1;
});

function barHeight(value) {
    return `${Math.max(4, Math.round((Number(value || 0) / trendMax.value) * 100))}%`;
}

const pipelineTotal = computed(() => props.pipeline.reduce((sum, row) => sum + Number(row.count || 0), 0));

const quickActions = [
    { label: 'Register Proposal', href: '/lending/loans/create', icon: 'assignment_add' },
    { label: 'Jurnal Angsuran', href: '/accounting/journal-entries/installment', icon: 'payments' },
    { label: 'Jurnal Umum', href: '/accounting/journal-entries/create', icon: 'receipt_long' },
    { label: 'E-Budgeting', href: '/budgeting', icon: 'account_balance_wallet' },
];

const sourceLabel = {
    loan: 'Pinjaman',
    installment: 'Angsuran',
    manual: 'Manual',
    general: 'Umum',
};
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout :unit-name="unitName">
        <div class="mx-auto max-w-7xl space-y-8">
            <section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Ringkasan operasional</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary sm:text-3xl">
                        {{ unitName || 'Dashboard' }}
                    </h1>
                    <p class="mt-1 text-on-surface-variant">
                        Data live per {{ formatDate(as_of) }} · {{ counts.active_loans }} pinjaman aktif ·
                        {{ counts.members }} anggota
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-for="action in quickActions" :key="action.href" :href="action.href">
                        <AppButton variant="secondary" size="compact" :icon="action.icon">{{ action.label }}</AppButton>
                    </Link>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="KPI utama">
                <AppCard v-for="card in cards" :key="card.key" bordered>
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div
                            class="grid size-10 place-items-center rounded-lg"
                            :class="card.tone === 'error' ? 'bg-error-container text-on-error-container' : 'bg-primary-fixed/40 text-primary'"
                        >
                            <AppIcon :name="card.icon" />
                        </div>
                        <AppBadge v-if="card.tone === 'error'" tone="error">Perhatian</AppBadge>
                    </div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">{{ card.label }}</p>
                    <p class="mt-2 text-2xl font-bold" :class="card.tone === 'error' ? 'text-error' : 'text-primary'">
                        {{ formatValue(card) }}
                    </p>
                    <p v-if="card.hint" class="mt-1 text-xs text-on-surface-variant">{{ card.hint }}</p>
                </AppCard>
            </section>

            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
                <section class="card-shadow flex min-h-0 flex-col rounded-xl bg-surface-container-lowest p-6 xl:col-span-2">
                    <header class="mb-4 flex shrink-0 items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Tren 6 Bulan</h2>
                            <p class="text-sm text-on-surface-variant">Pencairan vs penerimaan angsuran</p>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-semibold text-on-surface-variant">
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-primary" />Cair</span>
                            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-sm bg-secondary" />Terima</span>
                        </div>
                    </header>

                    <div v-if="trend.length" class="flex min-h-[12rem] flex-1 items-end gap-3 sm:gap-4">
                        <div v-for="row in trend" :key="row.key" class="flex h-full min-w-0 flex-1 flex-col items-center gap-2">
                            <div class="flex min-h-0 w-full flex-1 items-end justify-center gap-1.5">
                                <div
                                    class="w-3 rounded-t bg-primary sm:w-3.5"
                                    :style="{ height: barHeight(row.disbursed) }"
                                    :title="`Cair ${formatMoney(row.disbursed)}`"
                                />
                                <div
                                    class="w-3 rounded-t bg-secondary sm:w-3.5"
                                    :style="{ height: barHeight(row.collected) }"
                                    :title="`Terima ${formatMoney(row.collected)}`"
                                />
                            </div>
                            <p class="shrink-0 truncate text-[11px] font-semibold text-on-surface-variant">{{ row.label }}</p>
                        </div>
                    </div>
                    <div v-else class="flex flex-1 items-center">
                        <AppEmptyState icon="show_chart" title="Belum ada tren" description="Pencairan dan angsuran akan tampil di sini." />
                    </div>
                </section>

                <section class="card-shadow flex min-h-0 flex-col rounded-xl bg-surface-container-lowest p-6">
                    <header class="mb-4 flex shrink-0 items-center justify-between">
                        <h2 class="text-lg font-bold text-primary">Pipeline Pinjaman</h2>
                        <AppBadge tone="primary">{{ pipelineTotal }}</AppBadge>
                    </header>
                    <div class="flex flex-1 flex-col justify-between gap-3">
                        <Link
                            v-for="stage in pipeline"
                            :key="stage.status"
                            :href="`/lending/loans?tab=${stage.status === 'draft' ? 'proposal' : stage.status === 'verified' ? 'verifikasi' : stage.status === 'waiting' ? 'waiting' : 'aktif'}`"
                            class="flex items-center justify-between rounded-xl border border-outline-variant px-4 py-3 transition hover:bg-surface-container-low"
                        >
                            <div>
                                <p class="font-semibold text-primary">{{ stage.label }}</p>
                                <p class="text-xs text-on-surface-variant">{{ formatMoney(stage.amount) }}</p>
                            </div>
                            <p class="text-xl font-bold text-primary">{{ stage.count }}</p>
                        </Link>
                    </div>
                </section>
            </div>

            <!-- Jurnal + Jatuh Tempo: fixed max height, internal scroll -->
            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-3">
                <section class="card-shadow flex max-h-[28rem] min-h-0 flex-col rounded-xl bg-surface-container-lowest xl:col-span-2">
                    <header class="flex shrink-0 items-center justify-between border-b border-outline-variant px-6 py-4">
                        <div>
                            <h2 class="text-lg font-bold text-primary">Jurnal Terbaru</h2>
                            <p class="text-sm text-on-surface-variant">Posted, {{ recent_journals.length }} entri terakhir</p>
                        </div>
                        <Link href="/accounting/journal-entries/create">
                            <AppButton variant="ghost" size="compact">Buat jurnal</AppButton>
                        </Link>
                    </header>

                    <div v-if="recent_journals.length" class="min-h-0 flex-1 overflow-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="sticky top-0 z-10 bg-surface-container-low text-on-surface-variant">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">Tanggal</th>
                                    <th class="px-6 py-3 font-semibold">No / Uraian</th>
                                    <th class="px-6 py-3 font-semibold">Sumber</th>
                                    <th class="px-6 py-3 text-right font-semibold">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="row in recent_journals"
                                    :key="row.row_id"
                                    class="border-t border-outline-variant"
                                >
                                    <td class="whitespace-nowrap px-6 py-3 text-on-surface-variant">{{ formatDate(row.transaction_date) }}</td>
                                    <td class="px-6 py-3">
                                        <p class="font-semibold text-primary">{{ row.journal_number || `#${row.row_id}` }}</p>
                                        <p class="line-clamp-1 text-xs text-on-surface-variant">{{ row.description || '—' }}</p>
                                    </td>
                                    <td class="px-6 py-3">
                                        <AppBadge tone="neutral">{{ sourceLabel[row.source_type] || row.source_type || '—' }}</AppBadge>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-3 text-right font-semibold text-primary">
                                        {{ formatMoney(row.amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="flex flex-1 items-center p-6">
                        <AppEmptyState icon="receipt_long" title="Belum ada jurnal posted" description="Transaksi yang di-post akan tampil di sini." />
                    </div>
                </section>

                <section class="card-shadow flex max-h-[28rem] min-h-0 flex-col rounded-xl bg-surface-container-lowest">
                    <header class="flex shrink-0 items-center justify-between gap-2 border-b border-outline-variant px-6 py-4">
                        <h2 class="flex items-center gap-2 text-lg font-bold text-primary">
                            <AppIcon name="event_note" class="text-tertiary" />
                            Jatuh Tempo
                        </h2>
                        <AppBadge v-if="overdue_summary.count" tone="error">{{ overdue_summary.count }} lewat</AppBadge>
                    </header>

                    <div v-if="upcoming_due.length" class="min-h-0 flex-1 space-y-3 overflow-y-auto px-6 py-4">
                        <article
                            v-for="item in upcoming_due"
                            :key="item.row_id"
                            class="rounded-lg border-l-4 bg-surface-container-low p-3"
                            :class="item.overdue ? 'border-error' : 'border-tertiary'"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-bold text-primary">{{ item.borrower }}</p>
                                    <p class="text-xs text-on-surface-variant">
                                        {{ item.loan_number || 'Pinjaman' }} · {{ formatDate(item.due_date) }}
                                    </p>
                                </div>
                                <p class="shrink-0 text-sm font-bold" :class="item.overdue ? 'text-error' : 'text-primary'">
                                    {{ formatMoney(item.amount) }}
                                </p>
                            </div>
                        </article>
                    </div>
                    <div v-else class="flex flex-1 items-center px-6 py-4">
                        <AppEmptyState icon="event_available" title="Tidak ada jatuh tempo 14 hari" />
                    </div>

                    <div class="shrink-0 border-t border-outline-variant px-6 py-4">
                        <Link href="/accounting/journal-entries/installment" class="block">
                            <AppButton variant="secondary" class="w-full" icon="payments">Catat angsuran</AppButton>
                        </Link>
                    </div>
                </section>
            </div>

            <section class="relative overflow-hidden rounded-xl bg-primary p-6 text-on-primary">
                <AppIcon name="verified" class="absolute -bottom-8 -right-5 text-[8rem] text-on-primary/5" />
                <div class="relative space-y-2">
                    <h2 class="text-lg font-bold">Siap operasional</h2>
                    <p class="text-sm leading-6 text-primary-fixed-dim">
                        KPI dihitung dari jurnal posted dan jadwal angsuran pinjaman aktif — tanpa salinan saldo legacy.
                    </p>
                    <Link href="/accounting/tax-estimate" class="inline-flex text-sm font-bold text-on-primary underline-offset-2 hover:underline">
                        Lihat taksiran pajak →
                    </Link>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
