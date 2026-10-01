<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import { useConfirm } from '../../composables/useConfirm';
import RegencyLayout from '../../Layouts/RegencyLayout.vue';

const props = defineProps({
    regency_code: { type: String, required: true },
    regency_name: { type: String, required: true },
    logo_url: { type: String, default: null },
    official_name: { type: String, default: '' },
    address: { type: String, default: '' },
});

const { confirm: confirmAction } = useConfirm();

const fileInput = ref(null);
const selectedFileName = ref('');

const logoForm = useForm({ logo: null });
const identityForm = useForm({
    official_name: props.official_name || '',
    address: props.address || '',
});

function chooseFile() {
    fileInput.value?.click();
}

function onFileChange(event) {
    const file = event.target.files?.[0] ?? null;
    logoForm.logo = file;
    selectedFileName.value = file?.name ?? '';
}

function uploadLogo() {
    if (!logoForm.logo) return;
    logoForm.post('/regency/settings/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset();
            selectedFileName.value = '';
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}

async function removeLogo() {
    if (!await confirmAction({ title: 'Hapus Logo', message: 'Hapus logo kabupaten?', confirmLabel: 'Hapus', variant: 'danger' })) return;
    router.delete('/regency/settings/logo', { preserveScroll: true });
}

function saveIdentity() {
    identityForm.put('/regency/settings/identity', { preserveScroll: true });
}
</script>

<template>
    <Head title="Pengaturan Kabupaten" />
    <RegencyLayout>
        <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-primary">Pengaturan Kabupaten</h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    Kelola logo resmi dan identitas instansi pembina untuk kop seluruh laporan keuangan konsolidasi.
                </p>
            </div>

            <!-- Card 1: Logo -->
            <AppCard bordered>
                <template #header>
                    <div class="flex items-center gap-3">
                        <AppIcon name="image" tone="primary" :container-size="10" />
                        <div>
                            <h2 class="text-lg font-bold text-primary">Logo Pemerintah Kabupaten</h2>
                            <p class="text-sm text-on-surface-variant">Logo resmi kop laporan tingkat kabupaten.</p>
                        </div>
                    </div>
                    <AppBadge :tone="props.logo_url ? 'success' : 'warning'">
                        {{ props.logo_url ? 'Tersedia' : 'Belum ada' }}
                    </AppBadge>
                </template>

                <div class="flex flex-col gap-6 lg:flex-row">
                    <!-- Preview -->
                    <div class="flex shrink-0 flex-col items-center justify-center gap-3">
                        <div class="grid size-40 place-items-center overflow-hidden rounded-xl border border-outline-variant bg-surface-container-low">
                            <img v-if="props.logo_url" :src="props.logo_url" :alt="props.regency_name" class="max-h-32 max-w-32 object-contain" />
                            <div v-else class="flex flex-col items-center gap-2 text-outline">
                                <AppIcon name="account_balance" class="text-4xl" />
                                <span class="text-xs font-semibold">Belum ada logo</span>
                            </div>
                        </div>
                        <AppButton v-if="props.logo_url" variant="danger" size="compact" icon="delete" @click="removeLogo">
                            Hapus Logo
                        </AppButton>
                    </div>

                    <!-- Upload form -->
                    <div class="flex-1 space-y-5">
                        <div>
                            <p class="mb-2 text-sm font-bold uppercase tracking-wider text-primary">Unggah Logo Baru</p>
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <input
                                    ref="fileInput"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="hidden"
                                    @change="onFileChange"
                                />
                                <AppButton variant="secondary" icon="upload_file" @click="chooseFile">
                                    Pilih Berkas
                                </AppButton>
                                <span class="text-sm text-on-surface-variant">
                                    {{ selectedFileName || 'Belum ada berkas dipilih' }}
                                </span>
                            </div>
                            <p v-if="logoForm.errors.logo" class="mt-1 text-sm text-error">{{ logoForm.errors.logo }}</p>
                        </div>

                        <div class="flex justify-end">
                            <AppButton icon="cloud_upload" :loading="logoForm.processing" :disabled="!logoForm.logo" @click="uploadLogo">
                                Unggah Logo
                            </AppButton>
                        </div>

                        <div class="space-y-2 rounded-xl bg-surface-container-low p-4 text-sm text-on-surface-variant">
                            <div class="flex items-start gap-2">
                                <AppIcon name="info" class="mt-0.5 text-lg text-primary" />
                                <p>Format yang didukung: PNG, JPG, JPEG, WebP. Ukuran maksimal 2 MB.</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <AppIcon name="info" class="mt-0.5 text-lg text-primary" />
                                <p>
                                    Logo ini otomatis disematkan pada kop seluruh laporan keuangan konsolidasi tingkat kabupaten
                                    (Neraca, Laba Rugi, Buku Besar, Arus Kas, dan CALK PDF).
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </AppCard>

            <!-- Card 2: Identity -->
            <AppCard bordered>
                <template #header>
                    <div class="flex items-center gap-3">
                        <AppIcon name="corporate_fare" tone="primary" :container-size="10" />
                        <div>
                            <h2 class="text-lg font-bold text-primary">Identitas Instansi Pembina / Dinas PMD</h2>
                            <p class="text-sm text-on-surface-variant">Identitas instansi yang tampil pada kop laporan.</p>
                        </div>
                    </div>
                </template>

                <form class="space-y-5" @submit.prevent="saveIdentity">
                    <AppInput
                        v-model="identityForm.official_name"
                        label="Nama Instansi / Dinas Pembina"
                        placeholder="Contoh: Dinas Pemberdayaan Masyarakat dan Desa"
                        :error="identityForm.errors.official_name"
                    />
                    <AppInput
                        v-model="identityForm.address"
                        label="Alamat Kantor / Kontak"
                        placeholder="Contoh: Jl. Pemuda No. 12, Kompleks Perkantoran Pemkab"
                        :error="identityForm.errors.address"
                    />

                    <div class="flex justify-end">
                        <AppButton type="submit" icon="save" :loading="identityForm.processing">
                            Simpan Identitas
                        </AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </RegencyLayout>
</template>
