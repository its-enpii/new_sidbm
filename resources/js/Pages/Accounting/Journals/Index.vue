<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppModal from '../../../Components/AppModal.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    pagination: { type: Object, required: true },
    filters: { type: Object, required: true },
    sourceOptions: { type: Array, required: true },
    can_reverse: { type: Boolean, default: false },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);
const q = ref(props.filters.q || '');
const source = ref(props.filters.source || 'all');
const syncing = ref(false);

watch(
    () => props.filters,
    (f) => {
        syncing.value = true;
        from.value = f.from;
        to.value = f.to;
        q.value = f.q || '';
        source.value = f.source || 'all';
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
    { deep: true },
);

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}
function formatDate(v) {
    if (!v) return '—';
    const d = new Date(v);
    if (Number.isNaN(d.getTime())) return v;
    return new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }).format(d);
}

function apply(page = 1) {
    if (syncing.value) return;
    router.get(
        '/accounting/journals',
        {
            from: from.value,
            to: to.value,
            q: q.value || undefined,
            source: source.value === 'all' ? undefined : source.value,
            page,
        },
        { preserveState: false, preserveScroll: true, replace: true },
    );
}

let searchTimer;
watch(q, () => {
    if (syncing.value) return;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => apply(1), 350);
});
watch([from, to, source], () => {
    if (syncing.value) return;
    apply(1);
});

const sourceLabel = computed(() => {
    const map = Object.fromEntries(props.sourceOptions.map((o) => [o.value, o.label]));
    return (value) => map[value] || value || '—';
});

// Reverse modal
const reverseOpen = ref(false);
const reverseTarget = ref(null);
const reverseForm = useForm({
    reversal_date: new Date().toISOString().slice(0, 10),
    reason: '',
});

function openReverse(row) {
    reverseTarget.value = row;
    reverseForm.reversal_date = new Date().toISOString().slice(0, 10);
    reverseForm.reason = `Pembatalan jurnal #${row.id}`;
    reverseForm.clearErrors();
    reverseOpen.value = true;
}

function submitReverse() {
    if (!reverseTarget.value) return;
    reverseForm.post(`/accounting/journals/${reverseTarget.value.row_id}/reverse`, {
        preserveScroll: true,
        onSuccess: () => {
            reverseOpen.value = false;
            reverseTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Daftar Jurnal" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Transaksi</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Jurnal</h1>
                    <p class="text-sm text-on-surface-variant">Jurnal posted. Koreksi lewat reverse (immutable).</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="/accounting/journal-entries/create">
                        <AppButton variant="secondary" icon="add" size="compact">Jurnal Umum</AppButton>
                    </a>
                    <a href="/accounting/journal-entries/installment">
                        <AppButton icon="payments" size="compact">Jurnal Angsuran</AppButton>
                    </a>
                </div>
            </header>

            <AppCard class="p-4">
                <div class="grid gap-3 md:grid-cols-4">
                    <AppDatePicker v-model="from" mode="day" label="Dari" />
                    <AppDatePicker v-model="to" mode="day" label="Sampai" />
                    <SmartSelect v-model="source" :options="sourceOptions" label="Sumber" />
                    <AppInput v-model="q" label="Cari" placeholder="No / keterangan / id" />
                </div>
            </AppCard>

            <AppCard class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-surface-container-low text-xs uppercase tracking-wide text-on-surface-variant">
                            <tr>
                                <th class="px-3 py-2 text-left">Tgl</th>
                                <th class="px-3 py-2 text-left">No</th>
                                <th class="px-3 py-2 text-left">Sumber</th>
                                <th class="px-3 py-2 text-left">Keterangan</th>
                                <th class="px-3 py-2 text-right">Nominal</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-right" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="rows.length === 0">
                                <td colspan="7" class="px-3 py-8 text-center text-on-surface-variant">
                                    Tidak ada jurnal pada rentang ini.
                                </td>
                            </tr>
                            <tr
                                v-for="row in rows"
                                :key="row.row_id"
                                class="border-t border-outline-variant/40"
                            >
                                <td class="px-3 py-2 whitespace-nowrap">{{ formatDate(row.transaction_date) }}</td>
                                <td class="px-3 py-2 font-semibold text-primary">#{{ row.id }}</td>
                                <td class="px-3 py-2">
                                    <AppBadge tone="neutral">{{ sourceLabel(row.source_type) }}</AppBadge>
                                </td>
                                <td class="px-3 py-2 max-w-xs truncate" :title="row.description">{{ row.description || '—' }}</td>
                                <td class="px-3 py-2 text-right tabular-nums font-semibold">{{ formatMoney(row.amount) }}</td>
                                <td class="px-3 py-2">
                                    <AppBadge v-if="row.is_reversal" tone="warning">Reversal</AppBadge>
                                    <AppBadge v-else-if="row.already_reversed" tone="error">Dibatalkan</AppBadge>
                                    <AppBadge v-else tone="success">Posted</AppBadge>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-1">
                                        <a
                                            v-if="row.receipt_url"
                                            :href="row.receipt_url"
                                            target="_blank"
                                            rel="noopener"
                                            class="rounded-lg px-2 py-1 text-xs font-semibold text-primary hover:bg-primary/10"
                                        >
                                            Bukti
                                        </a>
                                        <button
                                            v-if="can_reverse && row.can_reverse"
                                            type="button"
                                            class="rounded-lg px-2 py-1 text-xs font-semibold text-error hover:bg-error/10"
                                            @click="openReverse(row)"
                                        >
                                            Reverse
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-if="pagination.last_page > 1"
                    class="flex items-center justify-between border-t border-outline-variant px-4 py-3 text-sm"
                >
                    <p class="text-on-surface-variant">
                        {{ pagination.total }} jurnal · hlm {{ pagination.page }}/{{ pagination.last_page }}
                    </p>
                    <div class="flex gap-2">
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page <= 1"
                            @click="apply(pagination.page - 1)"
                        >
                            Prev
                        </AppButton>
                        <AppButton
                            size="compact"
                            variant="secondary"
                            :disabled="pagination.page >= pagination.last_page"
                            @click="apply(pagination.page + 1)"
                        >
                            Next
                        </AppButton>
                    </div>
                </div>
            </AppCard>

            <AppModal v-model="reverseOpen" title="Batalkan jurnal (reverse)">
                <form class="space-y-4" @submit.prevent="submitReverse">
                    <p class="text-sm text-on-surface-variant">
                        Jurnal <span class="font-semibold text-primary">#{{ reverseTarget?.id }}</span>
                        tidak dihapus. Sistem membuat jurnal lawan (debit↔kredit).
                    </p>
                    <p v-if="reverseTarget" class="rounded-lg bg-surface-container-low px-3 py-2 text-sm">
                        {{ reverseTarget.description || '—' }}
                        · {{ formatMoney(reverseTarget.amount) }}
                    </p>
                    <AppDatePicker v-model="reverseForm.reversal_date" mode="day" label="Tanggal reverse" required />
                    <AppTextarea
                        v-model="reverseForm.reason"
                        label="Alasan"
                        :error="reverseForm.errors.reason"
                        placeholder="Salah nominal / salah akun / duplikat…"
                    />
                    <div class="flex justify-end gap-2">
                        <AppButton type="button" variant="secondary" @click="reverseOpen = false">Batal</AppButton>
                        <AppButton type="submit" variant="danger" icon="undo" :loading="reverseForm.processing">
                            Reverse
                        </AppButton>
                    </div>
                </form>
            </AppModal>
        </div>
    </AuthenticatedLayout>
</template>
