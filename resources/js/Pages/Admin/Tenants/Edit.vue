<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, watch, onMounted } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
});

const initialProvince = props.tenant.district_code ? props.tenant.district_code.substring(0, 2) : '';
const initialRegency = props.tenant.district_code ? props.tenant.district_code.substring(0, 4) : '';

const form = useForm({
    name: props.tenant.name,
    status: props.tenant.status,
    timezone: props.tenant.timezone || 'Asia/Jakarta',
    province_code: initialProvince,
    regency_code: initialRegency,
    district_code: props.tenant.district_code || '',
});

const provinces = ref([]);
const regencies = ref([]);
const districts = ref([]);
const loading = ref(false);

const statusOptions = [
    { value: 'active', label: 'Aktif' },
    { value: 'suspended', label: 'Ditangguhkan' },
    { value: 'provisioning', label: 'Provisioning' },
    { value: 'provisioning_failed', label: 'Provisioning gagal' },
];

async function load(url, target) {
    loading.value = true;
    try {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || 'Data wilayah gagal dimuat.');
        target.value = payload.data || [];
    } catch (error) {
        target.value = [];
        console.error(error);
    } finally {
        loading.value = false;
    }
}

watch(() => form.province_code, async (value) => {
    regencies.value = [];
    districts.value = [];
    if (value) await load(`/admin/regional/regencies/${value}`, regencies);
});

watch(() => form.regency_code, async (value) => {
    districts.value = [];
    if (value) await load(`/admin/regional/districts/${value}`, districts);
});

onMounted(async () => {
    await load('/admin/regional/provinces', provinces);
    if (initialProvince) {
        await load(`/admin/regional/regencies/${initialProvince}`, regencies);
    }
    if (initialRegency) {
        await load(`/admin/regional/districts/${initialRegency}`, districts);
    }
});

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
                <p class="mt-1 text-on-surface-variant">{{ tenant.code }} · {{ tenant.name }}</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <AppInput v-model="form.name" label="Nama Tenant" required :error="form.errors.name" />
                    <SmartSelect v-model="form.status" label="Status" :options="statusOptions" required :error="form.errors.status" />
                    <AppInput v-model="form.timezone" label="Zona waktu" :error="form.errors.timezone" />

                    <div class="border-t border-outline-variant pt-5">
                        <h2 class="font-semibold text-primary">Wilayah Kecamatan (district_code)</h2>
                        <p class="text-xs text-on-surface-variant mb-4">Pilih kecamatan untuk mengasosiasikan tenant dengan kode wilayah resmi Indonesia.</p>
                        <div class="grid gap-4 xl:grid-cols-3">
                            <SmartSelect v-model="form.province_code" label="Provinsi" :options="provinces.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih provinsi" :error="form.errors.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.regency_code" label="Kabupaten/Kota" :options="regencies.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih kabupaten/kota" :error="form.errors.regency_code" :disabled="!form.province_code" :loading="loading" searchable />
                            <SmartSelect v-model="form.district_code" label="Kecamatan" :options="districts.map((item) => ({ value: item.code, label: item.name }))" placeholder="Pilih kecamatan" :error="form.errors.district_code" :disabled="!form.regency_code" :loading="loading" searchable />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <Link :href="`/admin/tenants/${tenant.row_id}`"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan Perubahan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>