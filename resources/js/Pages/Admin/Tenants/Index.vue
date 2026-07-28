<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import SmartDataTable from '../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    tenants: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'row_id' },
    direction: { type: String, default: 'desc' },
});

const columns = [
    { key: 'name', label: 'Tenant', sortable: true },
    { key: 'district_code', label: 'Kecamatan' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'plan', label: 'Plan' },
    { key: 'memberships_count', label: 'Users' },
    { key: 'shard', label: 'Shard' },
];
</script>

<template>
    <Head title="Tenant" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Tenant</h1>
                    <p class="mt-1 text-on-surface-variant">Kelola tenant platform dan penempatannya.</p>
                </div>
                <Link href="/admin/tenants/create"><AppButton icon="add">Tambah Tenant</AppButton></Link>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="tenants.data"
                        :columns="columns"
                        :pagination="tenants"
                        url="/admin/tenants"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-label="Cari tenant"
                        search-placeholder="Nama, kode, atau kecamatan"
                        empty-title="Belum ada tenant"
                        empty-description="Daftarkan tenant pertama untuk mulai."
                    >
                        <template #cell-name="{ row }">
                            <Link :href="`/admin/tenants/${row.row_id}`" class="font-semibold text-primary">{{ row.name }}</Link>
                            <span class="block text-xs text-on-surface-variant">{{ row.code }}</span>
                        </template>
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : row.status === 'suspended' ? 'error' : 'neutral'">{{ row.status }}</AppBadge>
                        </template>
                        <template #cell-plan="{ row }">{{ row.plan?.name || '—' }}</template>
                        <template #cell-shard="{ row }">{{ row.shard?.code || '—' }}</template>
                        <template #actions="{ row }">
                            <Link :href="`/admin/tenants/${row.row_id}`">
                                <AppButton variant="ghost" size="compact" icon="visibility" aria-label="Detail tenant">Detail</AppButton>
                            </Link>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
