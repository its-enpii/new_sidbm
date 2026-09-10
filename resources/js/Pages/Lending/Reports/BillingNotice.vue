<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCheckbox from '../../../Components/AppCheckbox.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    period: { type: Object, required: true },
    identity: { type: Object, required: true },
    filters: { type: Object, required: true },
    groups: { type: Array, required: true },
    totals: { type: Object, required: true },
    villages: { type: Array, required: true },
    groupsList: { type: Array, default: () => [] },
});

const selectedYear = ref(String(props.filters.year));
const selectedMonth = ref(String(props.filters.month));
const selectedVillage = ref(props.filters.village_row_id || '');
const selectedGroup = ref(props.filters.group_row_id || '');
const onlyDue = ref(Boolean(props.filters.only_due));
const syncing = ref(false);

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

const villageOptions = computed(() => [
    { value: '', label: 'Semua Desa' },
    ...props.villages,
]);

const groupOptions = computed(() => {
    const list = [{ value: '', label: 'Semua Kelompok' }];
    const filtered = selectedVillage.value
        ? props.groupsList.filter((g) => String(g.village_row_id) === String(selectedVillage.value))
        : props.groupsList;

    return [...list, ...filtered];
});

const money = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });
function formatMoney(v) {
    return money.format(Number(v || 0));
}

function apply() {
    if (syncing.value) return;
    router.get(
        '/lending/reports/billing-notice',
        {
            year: selectedYear.value,
            month: selectedMonth.value,
            village_row_id: selectedVillage.value || undefined,
            group_row_id: selectedGroup.value || undefined,
            only_due: onlyDue.value ? '1' : '0',
        },
        { preserveState: false, preserveScroll: true, replace: true },
    );
}

watch([selectedYear, selectedMonth, selectedVillage, selectedGroup, onlyDue], () => {
    if (syncing.value) return;
    apply();
});

const pdfHref = computed(() => {
    const q = new URLSearchParams({
        year: selectedYear.value || String(props.year),
        month: selectedMonth.value || String(props.month),
        only_due: onlyDue.value ? '1' : '0',
    });
    if (selectedVillage.value) q.set('village_row_id', selectedVillage.value);
    if (selectedGroup.value) q.set('group_row_id', selectedGroup.value);

    return `/lending/reports/billing-notice/pdf?${q.toString()}`;
});
</script>

<template>
    <Head title="Surat Tagihan Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Pelaporan Lending</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Daftar Surat Tagihan Pinjaman</h1>
                    <p class="text-sm text-on-surface-variant">
                        Daftar tagihan angsuran per kelompok &amp; pemanfaat periode {{ period.period_label }}
                    </p>
                </div>
                <a :href="pdfHref" target="_blank" rel="noopener">
                    <AppButton variant="secondary" icon="picture_as_pdf" size="compact">Cetak PDF</AppButton>
                </a>
            </div>

            <AppCard class="p-4">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
                    <AppDatePicker v-model="selectedYear" mode="year" label="Tahun" />
                    <SmartSelect v-model="selectedMonth" :options="monthOptions" label="Bulan" />
                    <SmartSelect v-model="selectedVillage" :options="villageOptions" label="Desa" />
                    <SmartSelect v-model="selectedGroup" :options="groupOptions" label="Kelompok" />
                </div>
                <div class="mt-3 pt-3 border-t border-outline-variant/30 flex items-center">
                    <AppCheckbox v-model="onlyDue" variant="inline" label="Hanya tampilkan angsuran yang belum lunas (jatuh tempo)" />
                </div>
            </AppCard>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Total Pemanfaat</p>
                    <p class="mt-2 text-xl font-bold text-primary">{{ totals.members_count }} orang ({{ totals.groups_count }} kelompok)</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tagihan Pokok</p>
                    <p class="mt-2 text-xl font-bold text-primary">Rp {{ formatMoney(totals.principal) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Tagihan Jasa</p>
                    <p class="mt-2 text-xl font-bold text-primary">Rp {{ formatMoney(totals.interest) }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Grand Total Tagihan</p>
                    <p class="mt-2 text-xl font-bold text-error">Rp {{ formatMoney(totals.total) }}</p>
                </AppCard>
            </div>

            <div v-if="groups.length === 0">
                <AppCard class="p-8 text-center text-on-surface-variant">
                    Tidak ada data tagihan pinjaman untuk periode dan filter terpilih.
                </AppCard>
            </div>

            <div v-else class="space-y-4">
                <AppCard v-for="group in groups" :key="group.group_name" class="overflow-hidden p-0">
                    <div class="bg-surface-container-low px-4 py-3 flex flex-wrap items-center justify-between gap-2 border-b border-outline-variant/30">
                        <div>
                            <span class="font-bold text-primary text-base">{{ group.group_name }}</span>
                            <span class="ml-2 text-xs text-on-surface-variant">Desa {{ group.village_name }}</span>
                        </div>
                        <div class="text-xs font-semibold text-primary">
                            Subtotal: Rp {{ formatMoney(group.totals.total) }} ({{ group.totals.members_count }} anggota)
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-surface-container-lowest text-xs uppercase tracking-wide text-on-surface-variant border-b border-outline-variant/20">
                                <tr>
                                    <th class="px-3 py-2 text-left">No. SPK</th>
                                    <th class="px-3 py-2 text-left">Nama Anggota</th>
                                    <th class="px-3 py-2 text-center">Ke</th>
                                    <th class="px-3 py-2 text-center">Jatuh Tempo</th>
                                    <th class="px-3 py-2 text-right">Pokok</th>
                                    <th class="px-3 py-2 text-right">Jasa</th>
                                    <th class="px-3 py-2 text-right">Denda</th>
                                    <th class="px-3 py-2 text-right font-bold">Total</th>
                                    <th class="px-3 py-2 text-left">No. HP</th>
                                    <th class="px-3 py-2 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template v-for="loan in group.loans" :key="loan.loan_row_id">
                                    <tr
                                        v-for="item in loan.items"
                                        :key="`${loan.loan_row_id}-${item.beneficiary_name}-${item.installment_number}`"
                                        class="border-t border-outline-variant/30 hover:bg-surface-container-lowest/50"
                                    >
                                        <td class="px-3 py-2 font-mono text-xs">{{ item.loan_number }}</td>
                                        <td class="px-3 py-2 font-medium text-primary">{{ item.beneficiary_name }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums">{{ item.installment_number }}</td>
                                        <td class="px-3 py-2 text-center tabular-nums text-xs">{{ item.due_date }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">Rp {{ formatMoney(item.principal_due) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">Rp {{ formatMoney(item.interest_due) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">Rp {{ formatMoney(item.penalty_due) }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums font-bold text-primary">Rp {{ formatMoney(item.total_due) }}</td>
                                        <td class="px-3 py-2 text-xs font-mono text-on-surface-variant">{{ item.phone || '—' }}</td>
                                        <td class="px-3 py-2 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-surface-container-high text-on-surface">
                                                {{ item.status }}
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
