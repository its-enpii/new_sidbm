<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../Components/AppBadge.vue';
import AppCard from '../../Components/AppCard.vue';
import AdminLayout from '../../Layouts/AdminLayout.vue';

defineProps({
    stats: { type: Object, required: true },
    recent_tenants: { type: Array, default: () => [] },
    open_invoices: { type: Array, default: () => [] },
});

function money(value, currency = 'IDR') {
    return new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(Number(value || 0));
}
</script>

<template>
    <Head title="Admin" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary">Dashboard Admin</h1>
                <p class="mt-1 text-on-surface-variant">Ringkasan tenant dan penagihan platform.</p>
            </header>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Tenant</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ stats.tenants }}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ stats.tenants_active }} aktif</p>
                </AppCard>
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Pengguna tenant</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ stats.users }}</p>
                </AppCard>
                <AppCard>
                    <p class="text-sm text-on-surface-variant">Invoice terbuka</p>
                    <p class="mt-2 text-3xl font-bold text-primary">{{ stats.invoices_open }}</p>
                    <p class="mt-1 text-sm text-on-surface-variant">{{ stats.invoices_overdue }} overdue · {{ stats.invoices_due_soon }} jatuh tempo ≤7 hari</p>
                </AppCard>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                        <h2 class="font-bold text-primary">Tenant terbaru</h2>
                        <Link href="/admin/tenants" class="text-sm font-semibold text-primary">Lihat semua</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="tenant in recent_tenants" :key="tenant.row_id" class="flex items-center justify-between px-6 py-4">
                            <div>
                                <Link :href="`/admin/tenants/${tenant.row_id}`" class="font-semibold text-primary">{{ tenant.name }}</Link>
                                <p class="text-sm text-on-surface-variant">{{ tenant.code }}</p>
                            </div>
                            <AppBadge :tone="tenant.status === 'active' ? 'success' : 'neutral'">{{ tenant.status }}</AppBadge>
                        </li>
                        <li v-if="!recent_tenants.length" class="px-6 py-8 text-center text-on-surface-variant">Belum ada tenant.</li>
                    </ul>
                </AppCard>

                <AppCard :padded="false">
                    <div class="flex items-center justify-between border-b border-outline-variant px-6 py-4">
                        <h2 class="font-bold text-primary">Invoice terbuka</h2>
                        <Link href="/admin/invoices" class="text-sm font-semibold text-primary">Lihat semua</Link>
                    </div>
                    <ul class="divide-y divide-outline-variant">
                        <li v-for="invoice in open_invoices" :key="invoice.row_id" class="flex items-center justify-between gap-3 px-6 py-4">
                            <div>
                                <Link :href="`/admin/invoices/${invoice.row_id}`" class="font-semibold text-primary">{{ invoice.number }}</Link>
                                <p class="text-sm text-on-surface-variant">{{ invoice.tenant?.name || '—' }} · jatuh tempo {{ invoice.due_at || '—' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-primary">{{ money(invoice.amount, invoice.currency) }}</p>
                                <AppBadge :tone="invoice.status === 'overdue' ? 'error' : 'warning'">{{ invoice.status }}</AppBadge>
                            </div>
                        </li>
                        <li v-if="!open_invoices.length" class="px-6 py-8 text-center text-on-surface-variant">Tidak ada invoice terbuka.</li>
                    </ul>
                </AppCard>
            </div>
        </div>
    </AdminLayout>
</template>
