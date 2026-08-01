<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
});

const form = useForm({
    name: props.tenant.name,
    status: props.tenant.status,
    timezone: props.tenant.timezone || 'Asia/Jakarta',
});

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Ditangguhkan' },
    { value: 'provisioning', label: 'Provisioning' },
    { value: 'provisioning_failed', label: 'Provisioning gagal' },
];

function submit() {
    form.put(`/admin/tenants/${props.tenant.row_id}`);
}
</script>

<template>
    <Head title="Edit Tenant" />
    <AdminLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <Link :href="`/admin/tenants/${tenant.row_id}`" class="text-sm font-semibold text-primary">← Kembali</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Edit Tenant</h1>
                <p class="mt-1 text-on-surface-variant">{{ tenant.code }}</p>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <AppInput v-model="form.name" label="Nama" required :error="form.errors.name" />
                    <SmartSelect v-model="form.status" label="Status" :options="statusOptions" required :error="form.errors.status" />
                    <AppInput v-model="form.timezone" label="Zona waktu" :error="form.errors.timezone" />
                    <div class="flex justify-end gap-3">
                        <Link :href="`/admin/tenants/${tenant.row_id}`"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>
