<script setup>
import { Head } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppCurrencyInput from '../../../Components/AppCurrencyInput.vue';
import AppDatePicker from '../../../Components/AppDatePicker.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import { useMoney } from '../../../composables/useMoney';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    products: { type: Array, default: () => [] },
    defaultSimulation: { type: Object, required: true },
    frequencyOptions: { type: Array, required: true },
    methodOptions: { type: Array, required: true },
    roundingOptions: { type: Array, required: true },
});

const { money } = useMoney();

const form = reactive({
    selectedProduct: '',
    borrower_name: '',
    principal_amount: props.defaultSimulation.parameters.principal_amount || 10000000,
    term_months: props.defaultSimulation.parameters.term_months || 12,
    interest_rate: props.defaultSimulation.parameters.interest_rate || 12,
    installment_method: props.defaultSimulation.parameters.installment_method || 'flat',
    principal_frequency: props.defaultSimulation.parameters.principal_frequency || 'monthly',
    interest_frequency: props.defaultSimulation.parameters.interest_frequency || 'monthly',
    rounding_step: props.defaultSimulation.parameters.rounding_step || 500,
    start_date: props.defaultSimulation.parameters.start_date || new Date().toISOString().slice(0, 10),
});

const productOptions = computed(() => [
    { value: '', label: 'Pilih Template Produk (Kustom / Manual)' },
    ...props.products.map((p) => ({
        value: p.code,
        label: `${p.name} (${p.code})`,
        data: p,
    })),
]);

function onProductChange(code) {
    if (!code) return;
    const prod = props.products.find((p) => p.code === code);
    if (!prod) return;

    if (prod.interest_rate) form.interest_rate = Number(prod.interest_rate);
    if (prod.term_months) form.term_months = Number(prod.term_months);
    if (prod.rounding_step !== undefined) form.rounding_step = Math.max(500, Number(prod.rounding_step));
    if (prod.min_amount && form.principal_amount < prod.min_amount) {
        form.principal_amount = prod.min_amount;
    }
}

const FREQUENCY_MONTHS = {
    monthly: 1,
    quarterly: 3,
    semi_annually: 6,
    annually: 12,
    at_maturity: 0,
};

function roundVal(amount, step) {
    const s = Math.max(500, parseInt(step, 10) || 500);
    return Math.round(amount / s) * s;
}

function advanceDate(baseDateStr, months) {
    const d = new Date(baseDateStr);
    d.setMonth(d.getMonth() + months);
    return d.toISOString().slice(0, 10);
}

// Client-side real-time calculation engine
const simulationResult = computed(() => {
    const principal = Math.max(0, Number(form.principal_amount || 0));
    const termMonths = Math.max(1, parseInt(form.term_months || 12, 10));
    const rateAnnual = Math.max(0, Number(form.interest_rate || 0));
    const method = form.installment_method || 'flat';
    const principalFreq = form.principal_frequency || 'monthly';
    const interestFreq = form.interest_frequency || 'monthly';
    const roundingStep = Math.max(500, parseInt(form.rounding_step || 500, 10));
    const startDate = form.start_date || new Date().toISOString().slice(0, 10);

    let schedule = [];

    if (method === 'annuity') {
        const monthlyRate = (rateAnnual / 100) / 12;
        const rawPmt = monthlyRate > 0
            ? principal * (monthlyRate * Math.pow(1 + monthlyRate, termMonths)) / (Math.pow(1 + monthlyRate, termMonths) - 1)
            : principal / termMonths;
        const pmt = roundVal(rawPmt, roundingStep);

        let remaining = principal;
        for (let i = 1; i <= termMonths; i++) {
            const iDue = roundVal(remaining * monthlyRate, roundingStep);
            let pDue = 0;
            if (i === termMonths) {
                pDue = remaining;
                remaining = 0;
            } else {
                pDue = Math.max(0, Math.round((pmt - iDue) * 100) / 100);
                remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);
            }

            schedule.push({
                number: i,
                due_date: advanceDate(startDate, i),
                principal_due: pDue,
                interest_due: iDue,
                total_due: Math.round((pDue + iDue) * 100) / 100,
                remaining_principal: remaining,
            });
        }
    } else if (method === 'declining') {
        const pStep = FREQUENCY_MONTHS[principalFreq] || 1;
        const pPeriods = principalFreq === 'at_maturity' ? 1 : Math.max(1, Math.round(termMonths / pStep));
        const rawP = pPeriods > 0 ? principal / pPeriods : principal;
        const roundedP = roundVal(rawP, roundingStep);
        const monthsPerPeriod = pPeriods > 0 ? Math.round(termMonths / pPeriods) : 1;
        const periodicRate = (rateAnnual / 100) * (monthsPerPeriod / 12);

        let accumulatedP = 0;
        let remaining = principal;

        for (let i = 1; i <= pPeriods; i++) {
            const pDue = (i === pPeriods)
                ? Math.round((principal - accumulatedP) * 100) / 100
                : roundedP;
            accumulatedP += pDue;

            const iDue = roundVal(remaining * periodicRate, roundingStep);
            remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);
            const m = principalFreq === 'at_maturity' ? termMonths : i * pStep;

            schedule.push({
                number: i,
                due_date: advanceDate(startDate, m),
                principal_due: pDue,
                interest_due: iDue,
                total_due: Math.round((pDue + iDue) * 100) / 100,
                remaining_principal: remaining,
            });
        }
    } else {
        // Flat calculation
        const pStep = FREQUENCY_MONTHS[principalFreq] || 1;
        const iStep = FREQUENCY_MONTHS[interestFreq] || 1;
        const pPeriods = principalFreq === 'at_maturity' ? 1 : Math.max(1, Math.round(termMonths / pStep));
        const iPeriods = interestFreq === 'at_maturity' ? 1 : Math.max(1, Math.round(termMonths / iStep));
        const totalInterest = principal * (rateAnnual / 100) * (termMonths / 12);

        const roundedP = roundVal(pPeriods > 0 ? principal / pPeriods : principal, roundingStep);
        const roundedI = roundVal(iPeriods > 0 ? totalInterest / iPeriods : totalInterest, roundingStep);

        if (principalFreq === interestFreq) {
            let accP = 0;
            let accI = 0;
            let remaining = principal;

            for (let i = 1; i <= pPeriods; i++) {
                const pDue = (i === pPeriods)
                    ? Math.round((principal - accP) * 100) / 100
                    : roundedP;
                accP += pDue;

                const iDue = (i === pPeriods)
                    ? Math.round((totalInterest - accI) * 100) / 100
                    : roundedI;
                accI += iDue;

                remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);
                const m = principalFreq === 'at_maturity' ? termMonths : i * pStep;

                schedule.push({
                    number: i,
                    due_date: advanceDate(startDate, m),
                    principal_due: pDue,
                    interest_due: iDue,
                    total_due: Math.round((pDue + iDue) * 100) / 100,
                    remaining_principal: remaining,
                });
            }
        } else {
            let accP = 0;
            let accI = 0;
            let remaining = principal;

            const pMap = {};
            for (let p = 1; p <= pPeriods; p++) {
                const m = principalFreq === 'at_maturity' ? termMonths : p * pStep;
                const pDue = (p === pPeriods) ? Math.round((principal - accP) * 100) / 100 : roundedP;
                accP += pDue;
                pMap[m] = pDue;
            }

            const iMap = {};
            for (let it = 1; it <= iPeriods; it++) {
                const m = interestFreq === 'at_maturity' ? termMonths : it * iStep;
                const iDue = (it === iPeriods) ? Math.round((totalInterest - accI) * 100) / 100 : roundedI;
                accI += iDue;
                iMap[m] = iDue;
            }

            for (let m = 1; m <= termMonths; m++) {
                const pDue = pMap[m] || 0;
                const iDue = iMap[m] || 0;
                if (pDue <= 0 && iDue <= 0) continue;

                remaining = Math.max(0, Math.round((remaining - pDue) * 100) / 100);

                schedule.push({
                    number: schedule.length + 1,
                    due_date: advanceDate(startDate, m),
                    principal_due: pDue,
                    interest_due: iDue,
                    total_due: Math.round((pDue + iDue) * 100) / 100,
                    remaining_principal: remaining,
                });
            }
        }
    }

    const totalP = schedule.reduce((sum, r) => sum + r.principal_due, 0);
    const totalI = schedule.reduce((sum, r) => sum + r.interest_due, 0);
    const totalPay = totalP + totalI;
    const estMonthly = termMonths > 0 ? totalPay / termMonths : 0;

    return {
        summary: {
            principal_amount: totalP,
            total_interest: totalI,
            total_payment: totalPay,
            estimated_monthly: estMonthly,
            term_months: termMonths,
            interest_rate: rateAnnual,
            method,
        },
        schedule,
    };
});

function openPdf() {
    const params = new URLSearchParams({
        principal_amount: form.principal_amount,
        term_months: form.term_months,
        interest_rate: form.interest_rate,
        installment_method: form.installment_method,
        principal_frequency: form.principal_frequency,
        interest_frequency: form.interest_frequency,
        rounding_step: form.rounding_step,
        start_date: form.start_date,
        borrower_name: form.borrower_name || 'Calon Peminjam',
        download: '1',
    });

    window.open(`/lending/simulation/pdf?${params.toString()}`, '_blank');
}
</script>

<template>
    <Head title="Simulasi Pinjaman" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <!-- Header -->
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary sm:text-3xl">Simulasi Pinjaman</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Kalkulator simulasi perhitungan skema angsuran pokok dan jasa pinjaman secara real-time.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <AppButton
                        icon="download"
                        variant="primary"
                        @click="openPdf"
                    >
                        Unduh PDF Simulasi
                    </AppButton>
                </div>
            </header>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
                <!-- Left Panel: Parameters Form -->
                <div class="lg:col-span-4 space-y-6">
                    <AppCard title="Parameter Pinjaman" icon="tune">
                        <div class="space-y-4">
                            <!-- Template Produk -->
                            <SmartSelect
                                v-model="form.selectedProduct"
                                label="Template Produk"
                                :options="productOptions"
                                @update:model-value="onProductChange"
                            />

                            <!-- Calon Peminjam -->
                            <AppInput
                                v-model="form.borrower_name"
                                label="Nama Calon Peminjam / Kelompok"
                                placeholder="Contoh: Kelompok Mawar 01"
                                icon="person"
                            />

                            <!-- Plafon Pinjaman -->
                            <AppCurrencyInput
                                v-model="form.principal_amount"
                                label="Plafon Pinjaman (Rp)"
                                icon="payments"
                                :step="500000"
                                required
                            />

                            <!-- Tenor / Jangka Waktu -->
                            <div>
                                <AppInput
                                    v-model="form.term_months"
                                    label="Jangka Waktu (Bulan)"
                                    type="number"
                                    min="1"
                                    max="120"
                                    icon="calendar_month"
                                    required
                                />
                                <div class="mt-1.5 flex flex-wrap gap-1.5">
                                    <button
                                        v-for="t in [6, 10, 12, 18, 24, 36]"
                                        :key="t"
                                        type="button"
                                        class="rounded-md px-2 py-0.5 text-xs font-semibold transition-colors"
                                        :class="form.term_months == t ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface hover:bg-surface-container-highest'"
                                        @click="form.term_months = t"
                                    >
                                        {{ t }} Bln
                                    </button>
                                </div>
                            </div>

                            <!-- Suku Bunga -->
                            <AppInput
                                v-model="form.interest_rate"
                                label="Suku Bunga / Jasa (% per tahun)"
                                type="number"
                                step="0.1"
                                min="0"
                                max="100"
                                icon="percent"
                                required
                            />

                            <!-- Sistem Bunga -->
                            <SmartSelect
                                v-model="form.installment_method"
                                label="Sistem Bunga"
                                :options="methodOptions"
                            />

                            <!-- Frekuensi Pokok & Jasa -->
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <SmartSelect
                                    v-model="form.principal_frequency"
                                    label="Frekuensi Pokok"
                                    :options="frequencyOptions"
                                />
                                <SmartSelect
                                    v-model="form.interest_frequency"
                                    label="Frekuensi Jasa"
                                    :options="frequencyOptions"
                                />
                            </div>

                            <!-- Pembulatan -->
                            <SmartSelect
                                v-model="form.rounding_step"
                                label="Metode Pembulatan"
                                :options="roundingOptions"
                            />

                            <!-- Tanggal Mulai -->
                            <AppDatePicker
                                v-model="form.start_date"
                                label="Tanggal Mulai / Pencairan"
                            />
                        </div>
                    </AppCard>
                </div>

                <!-- Right Panel: KPIs and Amortization Table -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- 4 Summary KPI Cards -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Plafon Pokok</span>
                                <span class="grid size-8 place-items-center rounded-lg bg-primary-container text-primary">
                                    <AppIcon name="account_balance_wallet" class="text-lg" />
                                </span>
                            </div>
                            <p class="mt-2 text-xl font-extrabold text-primary tabular-nums">
                                {{ money(simulationResult.summary.principal_amount) }}
                            </p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Plafon pinjaman awal</p>
                        </div>

                        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Total Jasa / Bunga</span>
                                <span class="grid size-8 place-items-center rounded-lg bg-secondary-container text-secondary">
                                    <AppIcon name="trending_up" class="text-lg" />
                                </span>
                            </div>
                            <p class="mt-2 text-xl font-extrabold text-secondary tabular-nums">
                                {{ money(simulationResult.summary.total_interest) }}
                            </p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ form.interest_rate }}% · {{ form.installment_method }}</p>
                        </div>

                        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Total Pengembalian</span>
                                <span class="grid size-8 place-items-center rounded-lg bg-tertiary-container text-tertiary">
                                    <AppIcon name="receipt_long" class="text-lg" />
                                </span>
                            </div>
                            <p class="mt-2 text-xl font-extrabold text-tertiary tabular-nums">
                                {{ money(simulationResult.summary.total_payment) }}
                            </p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Pokok + Total Jasa</p>
                        </div>

                        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 shadow-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-on-surface-variant">Est. Angsuran / Bln</span>
                                <span class="grid size-8 place-items-center rounded-lg bg-surface-container-high text-on-surface">
                                    <AppIcon name="calendar_today" class="text-lg" />
                                </span>
                            </div>
                            <p class="mt-2 text-xl font-extrabold text-on-surface tabular-nums">
                                {{ money(simulationResult.summary.estimated_monthly) }}
                            </p>
                            <p class="mt-0.5 text-xs text-on-surface-variant">Selama {{ form.term_months }} bulan</p>
                        </div>
                    </div>

                    <!-- Amortization Schedule Table -->
                    <AppCard title="Proyeksi Jadwal Angsuran" icon="table_chart" :padded="false">
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="border-b border-outline-variant bg-surface-container-low text-xs font-semibold text-on-surface-variant">
                                    <tr>
                                        <th class="px-4 py-3 text-center">Ke</th>
                                        <th class="px-4 py-3">Jatuh Tempo</th>
                                        <th class="px-4 py-3 text-right">Pokok (Rp)</th>
                                        <th class="px-4 py-3 text-right">Bunga / Jasa (Rp)</th>
                                        <th class="px-4 py-3 text-right">Total Angsuran (Rp)</th>
                                        <th class="px-4 py-3 text-right">Sisa Pokok (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-outline-variant">
                                    <tr
                                        v-for="row in simulationResult.schedule"
                                        :key="row.number"
                                        class="hover:bg-surface-container-low/50 transition-colors"
                                    >
                                        <td class="px-4 py-2.5 text-center font-bold text-on-surface-variant">
                                            {{ row.number }}
                                        </td>
                                        <td class="px-4 py-2.5 font-medium text-on-surface">
                                            {{ row.due_date }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-on-surface">
                                            {{ money(row.principal_due) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-on-surface">
                                            {{ money(row.interest_due) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums font-bold text-primary">
                                            {{ money(row.total_due) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right tabular-nums text-on-surface-variant">
                                            {{ money(row.remaining_principal) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-t-2 border-outline-variant bg-surface-container-low text-xs font-bold">
                                    <tr>
                                        <td colspan="2" class="px-4 py-3 text-center uppercase tracking-wider text-on-surface">
                                            Total
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-primary">
                                            {{ money(simulationResult.summary.principal_amount) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-secondary">
                                            {{ money(simulationResult.summary.total_interest) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-tertiary">
                                            {{ money(simulationResult.summary.total_payment) }}
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums text-on-surface-variant">
                                            Rp0
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </AppCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>