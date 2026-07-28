<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import SmartDataTable from '../../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    users: { type: Object, required: true },
    search: { type: String, default: '' },
    perPage: { type: Number, default: 15 },
    sort: { type: String, default: 'name' },
    direction: { type: String, default: 'asc' },
});

const columns = [
    { key: 'name', label: 'Nama', sortable: true },
    { key: 'username', label: 'Username' },
    { key: 'email', label: 'Email' },
    { key: 'status', label: 'Status' },
    { key: 'last_login_at', label: 'Login terakhir' },
];
</script>

<template>
    <Head :title="`Users · ${tenant.name}`" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <Link :href="`/admin/tenants/${tenant.row_id}`" class="text-sm font-semibold text-primary">← {{ tenant.name }}</Link>
                    <h1 class="mt-3 text-2xl font-bold text-primary">Pengguna Tenant</h1>
                    <p class="mt-1 text-on-surface-variant">{{ tenant.code }}</p>
                </div>
                <Link :href="`/admin/tenants/${tenant.row_id}/users/create`"><AppButton icon="person_add">Tambah User</AppButton></Link>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="users.data"
                        :columns="columns"
                        :pagination="users"
                        :url="`/admin/tenants/${tenant.row_id}/users`"
                        :search="search"
                        :per-page="perPage"
                        :sort="sort"
                        :direction="direction"
                        search-placeholder="Nama, username, email"
                        empty-title="Belum ada pengguna"
                        empty-description="Tambahkan user untuk tenant ini."
                    >
                        <template #cell-status="{ row }">
                            <AppBadge :tone="row.status === 'active' ? 'success' : 'neutral'">{{ row.status }}</AppBadge>
                        </template>
                        <template #actions="{ row }">
                            <Link :href="`/admin/tenants/${tenant.row_id}/users/${row.row_id}/edit`">
                                <AppButton variant="ghost" size="compact" icon="edit">Edit</AppButton>
                            </Link>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
