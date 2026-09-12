<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppIcon from '../../../../Components/AppIcon.vue';
import SmartDataTable from '../../../../Components/SmartDataTable.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useConfirm } from '../../../../composables/useConfirm';

const props = defineProps({
    tenant: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    search: { type: String, default: '' },
});

const { confirm: confirmAction } = useConfirm();

const columns = [
    { key: 'name', label: 'Nama Role' },
    { key: 'code', label: 'Kode' },
    { key: 'type', label: 'Tipe' },
    { key: 'user_count', label: 'Pengguna', class: 'text-center' },
    { key: 'permissions_count', label: 'Hak Akses Aktif', class: 'text-center' },
];

const pagination = {
    current_page: 1,
    last_page: 1,
    from: props.roles.length ? 1 : null,
    to: props.roles.length,
    total: props.roles.length,
};

function destroyRole(role) {
    router.delete(`/admin/tenants/${props.tenant.row_id}/roles/${role.row_id}`, { preserveScroll: true });
}

async function confirmDelete(role) {
    if (!await confirmAction({
        title: 'Hapus Role Tenant',
        message: `Hapus role kustom "${role.name}" pada tenant ${props.tenant.name}? Tindakan ini tidak dapat dibatalkan.`,
        confirmLabel: 'Ya, Hapus',
        variant: 'danger',
    })) return;

    destroyRole(role);
}
</script>

<template>
    <Head :title="`Role Tenant · ${tenant.name}`" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <Link :href="`/admin/tenants/${tenant.row_id}`" class="text-sm font-semibold text-primary">← {{ tenant.name }}</Link>
                    <h1 class="mt-3 text-2xl font-bold text-primary">Role &amp; Hak Akses Tenant</h1>
                    <p class="mt-1 text-on-surface-variant">{{ tenant.code }} · kelola peran kustom langsung dari panel platform tanpa impersonasi.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link :href="`/admin/tenants/${tenant.row_id}/users`">
                        <AppButton variant="secondary" icon="group">Pengguna Tenant</AppButton>
                    </Link>
                    <Link :href="`/admin/tenants/${tenant.row_id}/roles/create`">
                        <AppButton icon="add_moderator">Tambah Role Kustom</AppButton>
                    </Link>
                </div>
            </header>

            <AppCard :padded="false">
                <div class="p-6">
                    <SmartDataTable
                        :rows="roles"
                        :columns="columns"
                        :pagination="pagination"
                        :url="`/admin/tenants/${tenant.row_id}/roles`"
                        :search="search"
                        search-placeholder="Cari role..."
                        empty-title="Belum ada role"
                        empty-description="Role sistem dibuat otomatis; tambahkan role kustom untuk menyesuaikan hak akses staf tenant."
                    >
                        <template #cell-name="{ row }">
                            <div class="flex items-center gap-2">
                                <AppIcon v-if="row.is_locked" name="lock" class="text-sm text-primary" />
                                <span class="font-bold text-primary">{{ row.name }}</span>
                            </div>
                            <p v-if="row.description" class="mt-0.5 text-xs text-on-surface-variant">{{ row.description }}</p>
                        </template>
                        <template #cell-code="{ row }">
                            <code class="rounded bg-surface-container px-2 py-0.5 text-xs font-mono">{{ row.code }}</code>
                        </template>
                        <template #cell-type="{ row }">
                            <AppBadge v-if="row.is_locked" tone="primary">Terkunci (Admin)</AppBadge>
                            <AppBadge v-else-if="row.is_system" tone="neutral">Bawaan Sistem</AppBadge>
                            <AppBadge v-else tone="info-soft">Kustom</AppBadge>
                        </template>
                        <template #cell-user_count="{ row }">
                            <span class="inline-flex size-7 items-center justify-center rounded-full bg-surface-container text-xs font-semibold">{{ row.user_count }}</span>
                        </template>
                        <template #cell-permissions_count="{ row }">
                            <span v-if="row.is_locked" class="text-xs font-semibold text-primary">Semua Akses (*)</span>
                            <span v-else class="text-xs font-medium text-on-surface-variant">{{ row.permissions_count }} izin</span>
                        </template>
                        <template #actions="{ row }">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="`/admin/tenants/${tenant.row_id}/roles/${row.row_id}/edit`">
                                    <AppButton variant="ghost" size="compact" icon="edit" :aria-label="`Kelola hak akses ${row.name}`" tooltip="Lihat / Edit Hak Akses" />
                                </Link>
                                <AppButton
                                    v-if="!row.is_system && !row.is_locked"
                                    variant="ghost"
                                    size="compact"
                                    icon="delete"
                                    tone="error"
                                    tooltip="Hapus Role"
                                    :disabled="row.user_count > 0"
                                    :aria-label="`Hapus role ${row.name}`"
                                    @click="confirmDelete(row)"
                                />
                            </div>
                        </template>
                    </SmartDataTable>
                </div>
            </AppCard>
        </div>
    </AdminLayout>
</template>
