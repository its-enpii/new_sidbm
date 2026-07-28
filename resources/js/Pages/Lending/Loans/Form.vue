<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: { type: Array, required: true },
    groups: { type: Array, required: true },
});

const path = '/lending/loans';
const today = (() => {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
})();

const productOptions = computed(() => props.products.map((product) => ({
    value: product.row_id,
    label: `${product.name} · ${product.code}`,
})));

const installmentMethodOptions = [
    { value: 'flat', label: 'Flat' },
    { value: 'annuity', label: 'Anuitas' },
    { value: 'effective', label: 'Efektif' },
];

const principalFrequencyOptions = [
    { value: 'weekly', label: 'Mingguan' },
    { value: 'biweekly', label: 'Dua Mingguan' },
    { value: 'monthly', label: 'Bulanan' },
    { value: 'bimonthly', label: 'Dua Bulanan' },
    { value: 'quarterly', label: 'Tiga Bulanan' },
    { value: 'at_maturity', label: 'Sekaligus di Akhir' },
];

const interestFrequencyOptions = [
    { value: 'weekly', label: 'Mingguan' },
    { value: 'biweekly', label: 'Dua Mingguan' },
    { value: 'monthly', label: 'Bulanan' },
    { value: 'bimonthly', label: 'Dua Bulanan' },
    { value: 'quarterly', label: 'Tiga Bulanan' },
];

const frequencyMultiplier = {
    weekly: 4.3333,
    biweekly: 2.1667,
    monthly: 1,
    bimonthly: 0.5,
    quarterly: 0.3333,
};

const selectedGroupId = ref('');
const selectedProductId = ref('');
const beneficiaryCandidateId = ref('');
const beneficiarySearch = ref('');
const beneficiaryLoading = ref(false);
const beneficiarySearchEmpty = ref(false);
const beneficiaryOptions = ref([]);
const extraBeneficiaries = ref([]);
let beneficiarySearchController = null;

const form = useForm({
    loan_product_id: '',
    group_id: '',
    proposed_at: today,
    principal_amount: '',
    service_rate_total: '',
    term_months: '',
    installment_method: 'flat',
    principal_frequency: 'monthly',
    interest_frequency: 'monthly',
    chair_id: '',
    secretary_id: '',
    treasurer_id: '',
    beneficiary_ids: [],
    beneficiary_amounts: {},
});

function currency(value) {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(value ?? 0);
}

const beneficiaryTotal = computed(() => Object.values(form.beneficiary_amounts ?? {}).reduce((sum, value) => sum + Number(value || 0), 0));

const selectedGroup = computed(() => props.groups.find((group) => String(group.value) === String(selectedGroupId.value)) || null);
const selectedProduct = computed(() => props.products.find((product) => String(product.row_id) === String(selectedProductId.value)) || null);
const memberOptions = computed(() => {
    const existing = selectedGroup.value?.members || [];
    const existingIds = new Set(existing.map((m) => String(m.value)));
    const extras = extraBeneficiaries.value.filter((m) => !existingIds.has(String(m.value)));
    return [...existing, ...extras];
});
const beneficiaryCandidateOptions = computed(() => {
    const exclude = new Set(memberOptions.value.map((m) => String(m.value)));
    return beneficiaryOptions.value.filter((option) => !exclude.has(String(option.value)));
});

const committeeOption = (position) => {
    const officer = selectedGroup.value?.[position];
    if (!officer) return [];
    return [{ value: officer.member_row_id, label: officer.name ?? `Pengurus ${position}` }];
};

const periodPreview = computed(() => {
    const months = Number(form.term_months);
    const total = Number(form.service_rate_total);
    if (!months || !total) return null;
    const principalPeriods = form.principal_frequency === 'at_maturity'
        ? 1
        : Math.max(1, Math.round(months * (frequencyMultiplier[form.principal_frequency] || 0)));
    const interestPeriods = Math.max(1, Math.round(months * (frequencyMultiplier[form.interest_frequency] || 0)));
    return {
        principal: { periods: principalPeriods, perPeriod: (total / principalPeriods).toFixed(3) },
        interest: { periods: interestPeriods, perPeriod: (total / interestPeriods).toFixed(3) },
    };
});

const rateFormatter = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 });
const formatRate = (value) => rateFormatter.format(Number(value ?? 0));

const fillDefaults = () => {
    const product = selectedProduct.value;
    if (!product) return;
    const months = Number(form.term_months);
    const defaultRate = Number(product.default_interest_rate || 0);
    if (!form.term_months && product.default_term_months) form.term_months = String(product.default_term_months);
    if (defaultRate && months) {
        const periods = form.principal_frequency === 'at_maturity'
            ? 1
            : Math.round(months * (frequencyMultiplier[form.principal_frequency] || 0));
        if (!form.service_rate_total) form.service_rate_total = (defaultRate * periods).toFixed(2);
    }
};

watch(selectedGroupId, (value) => {
    form.group_id = value;
    extraBeneficiaries.value = [];
    form.beneficiary_ids = [];
    form.beneficiary_amounts = {};
    const group = selectedGroup.value;
    if (group) {
        if (group.chair) form.chair_id = String(group.chair.member_row_id);
        if (group.secretary) form.secretary_id = String(group.secretary.member_row_id);
        if (group.treasurer) form.treasurer_id = String(group.treasurer.member_row_id);
        form.beneficiary_ids = (group.members || []).map((member) => String(member.value));
        const perBene = Math.round((Number(form.principal_amount) || 0) / Math.max(1, form.beneficiary_ids.length));
        const amounts = {};
        form.beneficiary_ids.forEach((id) => { amounts[id] = perBene || 0; });
        form.beneficiary_amounts = amounts;
    } else {
        form.chair_id = '';
        form.secretary_id = '';
        form.treasurer_id = '';
    }
});
watch(selectedProductId, (value) => {
    form.loan_product_id = value;
    fillDefaults();
});
watch([() => form.term_months, () => form.principal_frequency], () => fillDefaults());

function submit() {
    form.post(path);
}

async function searchBeneficiaries(search = '') {
    beneficiarySearchController?.abort();
    const query = search.trim();
    const controller = new AbortController();
    beneficiarySearchController = controller;
    beneficiaryLoading.value = true;
    beneficiarySearchEmpty.value = false;
    const exclude = memberOptions.value.map((member) => member.value).join(',');
    const groupParam = selectedGroupId.value ? `&group_id=${encodeURIComponent(selectedGroupId.value)}` : '';
    try {
        const response = await fetch(`/lending/loans/beneficiary-options?search=${encodeURIComponent(query)}&exclude=${encodeURIComponent(exclude)}${groupParam}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
        if (!response.ok) throw new Error('Data anggota gagal dimuat.');
        const payload = await response.json();
        beneficiaryOptions.value = payload.data;
        beneficiarySearchEmpty.value = Boolean(query) && payload.data.length === 0;
    } catch (error) {
        if (error.name !== 'AbortError') beneficiaryOptions.value = [];
    } finally {
        if (beneficiarySearchController === controller) { beneficiaryLoading.value = false; beneficiarySearchController = null; }
    }
}

function updateBeneficiarySearch(search) {
    beneficiarySearch.value = search;
    beneficiarySearchEmpty.value = false;
    searchBeneficiaries(search);
}

function addBeneficiary() {
    const candidate = beneficiaryOptions.value.find((item) => String(item.value) === String(beneficiaryCandidateId.value));
    if (!candidate) return;
    if (!extraBeneficiaries.value.some((m) => String(m.value) === String(candidate.value))) {
        extraBeneficiaries.value.push(candidate);
    }
    const id = String(candidate.value);
    if (!form.beneficiary_ids.some((existing) => String(existing) === id)) {
        form.beneficiary_ids.push(id);
    }
    if (!(id in form.beneficiary_amounts)) {
        const principal = Number(form.principal_amount) || 0;
        const count = Math.max(1, form.beneficiary_ids.length);
        form.beneficiary_amounts = { ...form.beneficiary_amounts, [id]: Math.round(principal / count) };
    }
    beneficiaryCandidateId.value = '';
    beneficiarySearch.value = '';
    beneficiarySearchEmpty.value = false;
    searchBeneficiaries('');
}
</script>

<template>
    <Head title="Register Proposal Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <Link :href="path" class="text-sm font-semibold text-primary">← Kembali</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Register Proposal Pinjaman</h1>
                <p class="mt-1 text-on-surface-variant">Daftarkan proposal pinjaman baru untuk kelompok. Pemanfaat adalah anggota terdaftar pada kelompok tersebut.</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <section>
                        <h2 class="font-semibold text-primary">Produk & Kelompok</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <SmartSelect v-model="selectedProductId" label="Produk Pinjaman" :options="productOptions" placeholder="Pilih produk (SPP/UEP/PL)" required searchable :error="form.errors.loan_product_id" />
                            <SmartSelect v-model="selectedGroupId" label="Kelompok" :options="groups.map((g) => ({ value: g.value, label: g.label }))" placeholder="Pilih kelompok" required searchable :error="form.errors.group_id" />
                        </div>
                    </section>

                    <section v-if="selectedProduct" class="rounded-xl border border-secondary/30 bg-secondary/10 px-4 py-3 text-sm text-primary">
                        <p class="font-semibold">{{ selectedProduct.name }} ({{ selectedProduct.code }})</p>
                        <p class="mt-1 text-on-surface-variant">Default: suku jasa {{ formatRate(selectedProduct.default_interest_rate) }}% per periode · Tenor {{ selectedProduct.default_term_months }} bulan.</p>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Detail Pengajuan</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <AppDatePicker v-model="form.proposed_at" label="Tanggal Pengajuan" icon="event" placeholder="Pilih tanggal" :max="today" required :error="form.errors.proposed_at" />
                            <AppCurrencyInput v-model="form.principal_amount" label="Plafon Pinjaman" icon="payments" :min="0" required :error="form.errors.principal_amount" />
                            <AppInput v-model="form.term_months" label="Jangka Waktu (bulan)" icon="schedule" type="number" inputmode="numeric" min="1" max="120" required :error="form.errors.term_months" />
                            <AppInput
                                v-model="form.service_rate_total"
                                label="Prosentase Jasa Total"
                                icon="percent"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                required
                                :error="form.errors.service_rate_total"
                                tooltip="Total jasa sepanjang pinjaman. Contoh: 1,5%/bulan × 12 bulan = 18"
                            />
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <SmartSelect v-model="form.installment_method" label="Metode Hitung Jasa" :options="installmentMethodOptions" required :error="form.errors.installment_method" />
                            <SmartSelect v-model="form.principal_frequency" label="Angsuran Pokok" :options="principalFrequencyOptions" required :error="form.errors.principal_frequency" />
                            <SmartSelect v-model="form.interest_frequency" label="Angsuran Jasa" :options="interestFrequencyOptions" required :error="form.errors.interest_frequency" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Struktur Kelompok (Snapshot)</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Pengurus saat proposal didaftarkan. Disimpan sebagai snapshot.</p>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                            <SmartSelect v-model="form.chair_id" label="Ketua" :options="committeeOption('chair')" :disabled="!selectedGroup" :placeholder="selectedGroup?.chair ? undefined : 'Pilih kelompok dulu'" required :error="form.errors.chair_id" />
                            <SmartSelect v-model="form.secretary_id" label="Sekretaris" :options="committeeOption('secretary')" :disabled="!selectedGroup" :placeholder="selectedGroup?.secretary ? undefined : 'Pilih kelompok dulu'" required :error="form.errors.secretary_id" />
                            <SmartSelect v-model="form.treasurer_id" label="Bendahara" :options="committeeOption('treasurer')" :disabled="!selectedGroup" :placeholder="selectedGroup?.treasurer ? undefined : 'Pilih kelompok dulu'" required :error="form.errors.treasurer_id" />
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Pemanfaat</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">Pilih anggota yang menerima bagian plafon. Plafon dibagi rata ke seluruh pemanfaat aktif.</p>
                        <div class="mt-3">
                            <div v-if="!selectedGroup" class="rounded-xl border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Pilih kelompok terlebih dahulu.</div>
                            <template v-else>
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                    <div class="flex-1"><SmartSelect v-model="beneficiaryCandidateId" label="Cari anggota di luar kelompok" :options="beneficiaryCandidateOptions" searchable :loading="beneficiaryLoading" placeholder="Cari NIK atau nama" @search-change="updateBeneficiarySearch" @search="searchBeneficiaries" /></div>
                                    <AppButton type="button" variant="secondary" icon="person_add" class="min-h-14 w-full sm:w-auto" :disabled="!beneficiaryCandidateId" @click="addBeneficiary">Tambahkan</AppButton>
                                </div>
                                <div v-if="memberOptions.length === 0" class="mt-3 rounded-xl border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Belum ada pemanfaat.</div>
                                <div v-else class="mt-3 overflow-x-auto rounded-xl border border-outline-variant">
                                    <table class="w-full text-left text-sm">
                                        <thead class="bg-surface-container-low text-xs font-bold uppercase tracking-widest text-on-surface-variant">
                                            <tr>
                                                <th class="py-3 px-4">Nama</th>
                                                <th class="py-3 px-4 text-right">Pengajuan (Rp)</th>
                                                <th class="py-3 px-4 text-center">Aktif</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-outline-variant">
                                            <tr v-for="member in memberOptions" :key="member.value">
                                                <td class="py-2 px-4"><span class="font-semibold text-primary">{{ member.label }}</span></td>
                                                <td class="py-2 px-4">
                                                    <AppCurrencyInput v-model="form.beneficiary_amounts[member.value]" label="" hide-label :min="0" :error="form.errors[`beneficiary_amounts.${member.value}`]" placeholder="0" />
                                                </td>
                                                <td class="py-2 px-4 text-center">
                                                    <label class="relative inline-flex cursor-pointer">
                                                        <input type="checkbox" :value="member.value" v-model="form.beneficiary_ids" class="peer sr-only" role="switch">
                                                        <span class="h-6 w-10 rounded-full bg-outline-variant transition peer-checked:bg-primary"></span>
                                                        <span class="absolute left-0.5 top-0.5 size-5 rounded-full bg-surface-container-lowest shadow transition peer-checked:translate-x-4"></span>
                                                    </label>
                                                </td>
                                            </tr>
                                        </tbody>
                                        <tfoot>
                                            <tr class="bg-surface-container-low">
                                                <td class="py-3 px-4 text-right text-xs font-bold uppercase tracking-widest text-on-surface-variant">Total Pengajuan</td>
                                                <td class="py-3 px-4 text-right text-base font-bold text-primary">{{ currency(beneficiaryTotal) }}</td>
                                                <td class="py-3 px-4"></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <p v-if="beneficiaryTotal > 0 && Number(form.principal_amount) > 0 && beneficiaryTotal > Number(form.principal_amount)" class="mt-2 text-sm text-error">Total pengajuan melebihi plafon pinjaman ({{ currency(Number(form.principal_amount)) }}).</p>
                                <p v-if="form.errors.beneficiary_ids" class="mt-2 text-sm text-error">{{ form.errors.beneficiary_ids }}</p>
                                <p v-if="form.errors.beneficiary_amounts" class="mt-2 text-sm text-error">{{ form.errors.beneficiary_amounts }}</p>
                            </template>
                        </div>
                    </section>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-4">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" :disabled="form.processing" icon="save">Simpan Proposal</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>