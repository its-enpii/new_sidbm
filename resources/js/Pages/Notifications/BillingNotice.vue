<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppEmptyState from '../../Components/AppEmptyState.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    due_date: { type: String, required: true },
    items: { type: Array, required: true },
    gateway: { type: Object, required: true },
    totals: { type: Object, required: true },
});

const selectedDate = ref(props.due_date);
const syncing = ref(false);
const selected = reactive({});

function hydrateSelection() {
    Object.keys(selected).forEach((k) => delete selected[k]);
    for (const item of props.items) {
        selected[item.installment_row_id] = Boolean(item.can_send);
    }
}
hydrateSelection();

watch(
    () => [props.due_date, props.items],
    () => {
        syncing.value = true;
        selectedDate.value = props.due_date;
        hydrateSelection();
        queueMicrotask(() => {
            syncing.value = false;
        });
    },
    { deep: true },
);

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(value) {
    return money.format(Math.round(Number(value || 0)));
}

function formatDate(value) {
    if (!value) return '—';
    const d = new Date(`${value}T00:00:00`);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' });
}

function goDate(value) {
    if (!value || value === props.due_date) return;
    router.get('/notifications/billing', { due_date: value }, {
        preserveState: false,
        preserveScroll: true,
        replace: true,
    });
}

watch(selectedDate, (value) => {
    if (syncing.value) return;
    if (value && value !== props.due_date) goDate(value);
});

const selectedIds = computed(() =>
    props.items
        .filter((item) => selected[item.installment_row_id])
        .map((item) => item.installment_row_id),
);

const selectedAmount = computed(() =>
    props.items
        .filter((item) => selected[item.installment_row_id])
        .reduce((sum, item) => sum + Number(item.amount || 0), 0),
);

function selectSendable() {
    for (const item of props.items) {
        selected[item.installment_row_id] = Boolean(item.can_send);
    }
}

function clearSelection() {
    for (const item of props.items) {
        selected[item.installment_row_id] = false;
    }
}

const form = useForm({
    due_date: props.due_date,
    installment_row_ids: [],
});

function send() {
    form.due_date = props.due_date;
    form.installment_row_ids = selectedIds.value;
    if (form.installment_row_ids.length === 0) return;
    form.post('/notifications/billing/send', { preserveScroll: true });
}

const gatewayOk = computed(() => props.gateway.configured && props.gateway.enabled);
const sourceLabel = {
    group: 'Kelompok',
    member: 'Anggota',
    beneficiary: 'Pemanfaat',
    none: '—',
};
</script>

<template>
    <Head title="Notifikasi Tagihan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Notifikasi Tagihan</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Pilih tanggal jatuh tempo, centang pinjaman, kirim pesan WhatsApp.
                    </p>
                </div>
                <AppBadge :tone="gatewayOk ? 'success' : 'warning'">
                    {{ gatewayOk ? 'Gateway aktif' : 'Gateway nonaktif' }}
                </AppBadge>
            </header>

            <div class="grid gap-4 md:grid-cols-3">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Jatuh tempo</p>
                    <p class="mt-2 text-lg font-bold text-primary">{{ formatDate(due_date) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">{{ totals.count }} pinjaman · {{ totals.with_phone }} punya nomor</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total tagihan</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ formatMoney(totals.amount) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Dipilih</p>
                    <p class="mt-2 text-2xl font-bold text-primary">{{ selectedIds.length }} · {{ formatMoney(selectedAmount) }}</p>
                    <p class="mt-1 text-xs text-on-surface-variant">Instance {{ gateway.instance }}</p>
                </AppCard>
            </div>

            <AppCard>
                <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                    <AppDatePicker v-model="selectedDate" label="Tanggal Jatuh Tempo" />
                    <div class="flex flex-wrap items-end gap-2">
                        <AppButton variant="secondary" class="h-14" @click="selectSendable">Pilih ber-nomor</AppButton>
                        <AppButton variant="ghost" class="h-14" @click="clearSelection">Kosongkan</AppButton>
                    </div>
                </div>
            </AppCard>

            <AppCard :padded="false">
                <header class="flex flex-col gap-3 border-b border-outline-variant px-6 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Daftar Jatuh Tempo</h2>
                        <p class="text-sm text-on-surface-variant">Hanya pinjaman aktif dengan sisa angsuran.</p>
                    </div>
                    <AppButton
                        icon="send"
                        :loading="form.processing"
                        :disabled="form.processing || !gatewayOk || selectedIds.length === 0"
                        @click="send"
                    >
                        Kirim ({{ selectedIds.length }})
                    </AppButton>
                </header>

                <div v-if="items.length === 0" class="p-6">
                    <AppEmptyState icon="event_available" title="Tidak ada jatuh tempo" description="Ubah tanggal atau pastikan ada angsuran belum lunas." />
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-surface-container-low text-on-surface-variant">
                            <tr>
                                <th class="w-12 px-4 py-3" />
                                <th class="px-4 py-3 font-semibold">Pinjaman</th>
                                <th class="px-4 py-3 font-semibold">Ke</th>
                                <th class="px-4 py-3 text-right font-semibold">Tagihan</th>
                                <th class="px-4 py-3 font-semibold">Nomor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="item in items"
                                :key="item.installment_row_id"
                                class="border-t border-outline-variant"
                                :class="!item.can_send && 'opacity-60'"
                            >
                                <td class="px-4 py-3">
                                    <input
                                        v-model="selected[item.installment_row_id]"
                                        type="checkbox"
                                        class="size-4 rounded border-outline-variant text-primary focus:ring-primary"
                                        :disabled="!item.can_send"
                                    />
                                </td>
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-primary">{{ item.borrower }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ item.loan_number || `#${item.loan_row_id}` }}</p>
                                </td>
                                <td class="px-4 py-3 font-semibold text-primary">{{ item.installment_number }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-primary">{{ formatMoney(item.amount) }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-mono text-sm text-primary">{{ item.phone || '—' }}</p>
                                    <p class="text-xs text-on-surface-variant">{{ sourceLabel[item.phone_source] || item.phone_source }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
