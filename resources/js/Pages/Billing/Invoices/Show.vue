<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    invoice: { type: Object, required: true },
    payments: { type: Array, default: () => [] },
});

const payForm = useForm({});
const pendingCheckout = computed(() =>
    props.payments.find((p) => p.method === 'tripay' && p.status === 'pending' && p.tripay_checkout_url),
);

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}

function tone(status) {
    if (status === 'paid') return 'success';
    if (status === 'overdue' || status === 'void' || status === 'failed' || status === 'expired' || status === 'cancelled') return 'error';
    if (status === 'partially_paid' || status === 'pending') return 'warning';
    return 'neutral';
}

function statusLabel(status) {
    return ({
        issued: 'Belum dibayar',
        partially_paid: 'Sebagian',
        paid: 'Lunas',
        overdue: 'Terlambat',
        void: 'Dibatalkan',
        draft: 'Draft',
        pending: 'Menunggu',
        failed: 'Gagal',
        expired: 'Kedaluwarsa',
        cancelled: 'Dibatalkan',
    })[status] || status;
}

function methodLabel(method) {
    return ({ manual: 'Transfer', tripay: 'Online' })[method] || 'Lainnya';
}

function pay() {
    payForm.post(`/billing/invoices/${props.invoice.row_id}/pay`);
}
</script>

<template>
    <Head :title="invoice.number" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 lg:flex-row lg:items-start">
                <div>
                    <Link href="/billing/invoices" class="text-sm font-semibold text-primary">← Daftar tagihan</Link>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-bold text-primary">{{ invoice.number }}</h1>
                        <AppBadge :tone="tone(invoice.status)">{{ statusLabel(invoice.status) }}</AppBadge>
                    </div>
                    <p class="mt-1 text-on-surface-variant">
                        Jatuh tempo {{ invoice.due_at || '—' }}
                        <span v-if="invoice.subscription"> · {{ invoice.subscription.plan?.name || 'Langganan' }}</span>
                    </p>
                </div>
                <div v-if="invoice.is_open" class="flex flex-wrap gap-2">
                    <a
                        v-if="pendingCheckout"
                        :href="pendingCheckout.tripay_checkout_url"
                        target="_blank"
                        rel="noopener"
                    >
                        <AppButton variant="secondary" icon="open_in_new">Lanjutkan pembayaran</AppButton>
                    </a>
                    <AppButton icon="payments" :loading="payForm.processing" @click="pay">
                        Bayar sekarang
                    </AppButton>
                </div>
            </header>

            <div class="grid gap-6 lg:grid-cols-3">
                <AppCard class="lg:col-span-2">
                    <h2 class="font-bold text-primary">Ringkasan</h2>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm text-on-surface-variant">Nominal</dt>
                            <dd class="text-xl font-bold text-primary">{{ money(invoice.amount, invoice.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-on-surface-variant">Dibayar</dt>
                            <dd class="text-xl font-bold text-primary">{{ money(invoice.amount_paid, invoice.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-on-surface-variant">Sisa</dt>
                            <dd class="text-xl font-bold text-primary">{{ money(invoice.remaining, invoice.currency) }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-on-surface-variant">Diterbitkan</dt>
                            <dd class="font-semibold text-primary">{{ invoice.issued_at || '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm text-on-surface-variant">Deskripsi</dt>
                            <dd class="font-semibold text-primary">{{ invoice.description || '—' }}</dd>
                        </div>
                        <div v-if="invoice.notes" class="sm:col-span-2">
                            <dt class="text-sm text-on-surface-variant">Catatan</dt>
                            <dd class="text-on-surface">{{ invoice.notes }}</dd>
                        </div>
                    </dl>
                </AppCard>

                <AppCard v-if="invoice.is_open">
                    <h2 class="font-bold text-primary">Pembayaran</h2>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        Bayar sisa tagihan secara online. Status diperbarui setelah pembayaran berhasil.
                    </p>
                    <AppButton class="mt-4 w-full" icon="payments" :loading="payForm.processing" @click="pay">
                        Bayar {{ money(invoice.remaining, invoice.currency) }}
                    </AppButton>
                    <a
                        v-if="pendingCheckout"
                        :href="pendingCheckout.tripay_checkout_url"
                        target="_blank"
                        rel="noopener"
                        class="mt-3 block text-center text-sm font-semibold text-primary"
                    >
                        Lanjutkan pembayaran sebelumnya
                    </a>
                </AppCard>
                <AppCard v-else-if="invoice.status === 'paid'">
                    <h2 class="font-bold text-primary">Lunas</h2>
                    <p class="mt-2 text-sm text-on-surface-variant">
                        Tagihan ini sudah dibayar{{ invoice.paid_at ? ` pada ${invoice.paid_at}` : '' }}.
                    </p>
                </AppCard>
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
                                <td class="px-6 py-3 font-semibold text-primary">{{ methodLabel(payment.method) }}</td>
                                <td class="px-6 py-3"><AppBadge :tone="tone(payment.status)">{{ statusLabel(payment.status) }}</AppBadge></td>
                                <td class="px-6 py-3">{{ money(payment.amount, invoice.currency) }}</td>
                                <td class="px-6 py-3">
                                    <p>{{ payment.reference || payment.tripay_reference || '—' }}</p>
                                    <a
                                        v-if="payment.tripay_checkout_url && payment.status === 'pending'"
                                        :href="payment.tripay_checkout_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="text-sm font-semibold text-primary"
                                    >Lanjutkan bayar</a>
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
    </AuthenticatedLayout>
</template>
