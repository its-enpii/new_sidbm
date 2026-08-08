<script setup>
import { useConfirm } from '../../../composables/useConfirm';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
});

const purposeLabels = {
    subscription: 'Langganan / perpanjangan',
    setup: 'Biaya setup / onboarding',
    support: 'Dukungan / maintenance',
    training: 'Pelatihan',
    custom_dev: 'Pengembangan custom',
    other: 'Lainnya',
};

const manualForm = useForm({
    amount: props.invoice.remaining,
    paid_at: new Date().toISOString().slice(0, 10),
    reference: '',
    notes: '',
});

const voidForm = useForm({});
const tripayForm = useForm({});

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function tone(status) {
    if (status === 'paid') return 'success';
    if (status === 'overdue' || status === 'void' || status === 'failed') return 'error';
    if (status === 'partially_paid' || status === 'pending') return 'warning';
    return 'neutral';
}

function recordManual() {
    manualForm.post(`/admin/invoices/${props.invoice.row_id}/payments/manual`, {
        preserveScroll: true,
        onSuccess: () => manualForm.reset('reference', 'notes'),
    });
}

function initiateTripay() {
    tripayForm.post(`/admin/invoices/${props.invoice.row_id}/payments/tripay`, { preserveScroll: true });
}

const { confirm: confirmAction } = useConfirm();

async function voidInvoice() {
    if (!await confirmAction({ title: 'Batalkan Invoice', message: 'Batalkan invoice ini? Tindakan ini tidak dapat dibatalkan.' })) return;
    voidForm.post(`/admin/invoices/${props.invoice.row_id}/void`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="invoice.number" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <Link href="/admin/invoices" class="text-sm font-semibold text-primary">← Daftar invoice</Link>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ invoice.number }}</h1>
                        <AppBadge :tone="tone(invoice.status)">{{ invoice.status }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        <Link v-if="invoice.tenant" :href="`/admin/tenants/${invoice.tenant.row_id}`" class="font-semibold text-primary">{{ invoice.tenant.name }}</Link>
                        <span v-else>—</span>
                        · jatuh tempo {{ invoice.due_at || '—' }}
                    </p>
                </div>
                <AppButton
                    v-if="invoice.is_open && invoice.status !== 'partially_paid'"
                    variant="danger"
                    :loading="voidForm.processing"
                    @click="voidInvoice"
                >Void</AppButton>
            </header>

            <div class="grid gap-6 lg:grid-cols-3">
                <AppCard class="lg:col-span-2">
                    <h2 class="font-bold text-primary">Ringkasan</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-sm text-on-surface-variant">Nominal</dt><dd class="text-xl font-bold text-primary">{{ money(invoice.amount, invoice.currency) }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Dibayar</dt><dd class="text-xl font-bold text-primary">{{ money(invoice.amount_paid, invoice.currency) }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Sisa</dt><dd class="text-xl font-bold text-primary">{{ money(invoice.remaining, invoice.currency) }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Keperluan</dt><dd class="font-semibold text-primary">{{ purposeLabels[invoice.purpose] || invoice.purpose || '—' }}</dd></div>
                        <div><dt class="text-sm text-on-surface-variant">Diterbitkan</dt><dd class="font-semibold text-primary">{{ invoice.issued_at || '—' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-sm text-on-surface-variant">Deskripsi</dt><dd class="font-semibold text-primary">{{ invoice.description || '—' }}</dd></div>
                        <div v-if="invoice.subscription" class="sm:col-span-2"><dt class="text-sm text-on-surface-variant">Langganan terkait</dt><dd class="font-semibold text-primary">{{ invoice.subscription.plan?.name || invoice.subscription.row_id }} ({{ invoice.subscription.status }})</dd></div>
                    </dl>
                </AppCard>

                <div class="space-y-6">
                    <AppCard v-if="invoice.is_open">
                        <h2 class="font-bold text-primary">Bayar manual</h2>
                        <form class="mt-4 space-y-3" @submit.prevent="recordManual">
                            <AppInput v-model="manualForm.amount" label="Nominal" type="number" step="0.01" min="0.01" required :error="manualForm.errors.amount" />
                            <AppInput v-model="manualForm.paid_at" label="Tanggal bayar" type="date" :error="manualForm.errors.paid_at" />
                            <AppInput v-model="manualForm.reference" label="Referensi" :error="manualForm.errors.reference" />
                            <AppTextarea v-model="manualForm.notes" label="Catatan" :error="manualForm.errors.notes" />
                            <AppButton type="submit" class="w-full" :loading="manualForm.processing" icon="payments">Catat pembayaran</AppButton>
                        </form>
                    </AppCard>

                    <AppCard v-if="invoice.is_open">
                        <h2 class="font-bold text-primary">Tripay</h2>
                        <p class="mt-2 text-sm text-on-surface-variant">Buat closed payment untuk sisa tagihan. Bagikan link checkout ke tenant.</p>
                        <AppButton class="mt-4 w-full" variant="secondary" :loading="tripayForm.processing" icon="link" @click="initiateTripay">Buat link Tripay</AppButton>
                    </AppCard>
                </div>
            </div>

            <AppCard :padded="false">
                <div class="border-b border-outline-variant px-6 py-4">
                    <h2 class="font-bold text-primary">Riwayat pembayaran</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead class="bg-surface-container-low text-sm">
                            <tr>
                                <th class="px-6 py-3">Metode</th>
                                <th class="px-6 py-3">Status</th>
                                <th class="px-6 py-3">Nominal</th>
                                <th class="px-6 py-3">Referensi</th>
                                <th class="px-6 py-3">Waktu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in payments" :key="payment.row_id" class="border-t border-outline-variant">
                                <td class="px-6 py-3 font-semibold text-primary">{{ payment.method }}</td>
                                <td class="px-6 py-3"><AppBadge :tone="tone(payment.status)">{{ payment.status }}</AppBadge></td>
                                <td class="px-6 py-3">{{ money(payment.amount, invoice.currency) }}</td>
                                <td class="px-6 py-3">
                                    <p>{{ payment.reference || payment.tripay_reference || '—' }}</p>
                                    <a v-if="payment.tripay_checkout_url" :href="payment.tripay_checkout_url" target="_blank" rel="noopener" class="text-sm font-semibold text-primary">Buka checkout</a>
                                </td>
                                <td class="px-6 py-3 text-on-surface-variant">{{ payment.paid_at || '—' }}</td>
                            </tr>
                            <tr v-if="!payments.length">
                                <td colspan="5" class="px-6 py-8 text-center text-on-surface-variant">Belum ada pembayaran.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
