<script setup>
import { useConfirm } from '../../composables/useConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppCard from '../../Components/AppCard.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import AppTabs from '../../Components/AppTabs.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    profile: { type: Object, required: true },
    account: { type: Object, required: true },
    photoUrl: { type: String, default: null },
    educationOptions: { type: Array, required: true },
    google: { type: Object, default: () => ({ connected: false, email: null, avatar: null, linked_at: null }) },
    notificationSettings: { type: Object, default: () => ({ billing: true, announcements: true }) },
});

const page = usePage();
const flash = computed(() => page.props.flash?.success);

const tabs = [
    { key: 'personal', label: 'Data Pribadi', icon: 'badge' },
    { key: 'account', label: 'Akun', icon: 'manage_accounts' },
    { key: 'photo', label: 'Foto', icon: 'account_circle' },
];

const activeTab = ref(getInitialTab());

function getInitialTab() {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) return tab;
    return flash.value?.tab ?? 'personal';
}

function go(tab) {
    router.get('/profile', { tab }, { preserveState: true, preserveScroll: true });
}

watch(() => page.url, () => {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) activeTab.value = tab;
});

const today = (() => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
})();

const personalForm = useForm({
    nik: props.profile.nik ?? '',
    name: props.profile.name ?? '',
    initials: props.profile.initials ?? '',
    birth_place: props.profile.birth_place ?? '',
    birth_date: props.profile.birth_date ?? '',
    address: props.profile.address ?? '',
    phone: props.profile.phone ?? '',
    education: props.profile.education ?? '',
    appointed_at: props.profile.appointed_at ?? '',
    term_end_at: props.profile.term_end_at ?? '',
});

function submitPersonal() {
    personalForm.put('/profile', { preserveScroll: true });
}

const accountForm = useForm({
    username: props.account.username ?? '',
    password: '',
    password_confirmation: '',
});

function submitAccount() {
    accountForm.put('/profile/account', {
        preserveScroll: true,
        onSuccess: () => {
            accountForm.password = '';
            accountForm.password_confirmation = '';
        },
    });
}

const photoForm = useForm({ photo: null });
const photoPreview = ref(props.photoUrl);
const photoDragOver = ref(false);
const imageError = ref(false);

watch(() => props.photoUrl, (newUrl) => {
    photoPreview.value = newUrl;
    imageError.value = false;
});

function onPhotoChange(event) {
    const file = event.target.files?.[0] ?? null;
    setPhotoFile(file);
}

function onPhotoDrop(event) {
    event.preventDefault();
    photoDragOver.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) setPhotoFile(file);
}

function setPhotoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    photoForm.photo = file;
    photoPreview.value = URL.createObjectURL(file);
    imageError.value = false;
}

function submitPhoto() {
    photoForm.post('/profile/photo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            photoForm.reset();
            imageError.value = false;
        },
    });
}

const notificationForm = useForm({
    billing: props.notificationSettings.billing,
    announcements: props.notificationSettings.announcements,
});

function submitNotificationSettings() {
    notificationForm.put('/profile/notifications', { preserveScroll: true });
}

const googleBenefits = [
    {
        icon: 'rocket_launch',
        title: 'Login Satu Klik',
        desc: 'Masuk instan dengan opsi "Lanjutkan dengan Google" tanpa repot mengetik kata sandi.',
    },
    {
        icon: 'mark_email_read',
        title: 'Tagihan via Email',
        desc: 'Rincian tagihan langganan dan pelatihan dikirimkan otomatis langsung ke kotak masuk email Anda.',
    },
    {
        icon: 'campaign',
        title: 'Kabar Fitur Baru',
        desc: 'Dapatkan pemberitahuan pembaruan sistem dan rilis fitur baru secara tepat waktu.',
    },
];

const { confirm: confirmAction } = useConfirm();

async function destroyPhoto() {
    if (!await confirmAction({ title: 'Hapus Foto', message: 'Hapus foto profil?' })) return;
    router.delete('/profile/photo', {
        preserveScroll: true,
        onSuccess: () => {
            photoPreview.value = null;
            imageError.value = false;
        },
    });
}

const unlinkForm = useForm({});

async function confirmUnlinkGoogle() {
    if (!await confirmAction({
        title: 'Putuskan Hubungan Google',
        message: 'Akun Google akan dilepas dari akun SIDBM Next Anda. Login cepat dan pengiriman tagihan via email dinonaktifkan hingga Anda menghubungkan ulang akun Google.',
        confirmLabel: 'Putuskan',
        variant: 'danger',
        icon: 'link_off',
    })) return;

    unlinkForm.delete('/profile/google', { preserveScroll: true });
}
</script>

<template>
    <Head title="Profil" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">Profil Pengguna</h1>
                <p class="mt-1 text-on-surface-variant">Data pribadi, akun login, dan foto profil Anda.</p>
            </header>

            <AppCard v-if="flash">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary-container text-secondary">✓</div>
                    <p class="font-bold text-primary">{{ flash.message }}</p>
                </div>
            </AppCard>

            <div class="grid gap-6 lg:grid-cols-[12rem_1fr]">
                <div class="rounded-xl bg-surface-container-low p-2 lg:sticky lg:top-20 lg:self-start">
                    <AppTabs
                        v-model="activeTab"
                        :items="tabs"
                        vertical
                        variant="pills"
                        class="w-full"
                        @update:model-value="go"
                    />
                </div>

                <div class="space-y-6">
                    <AppCard v-show="activeTab === 'personal'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Data Pribadi</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Informasi identitas Anda.</p>
                        <form class="space-y-4" @submit.prevent="submitPersonal">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="personalForm.nik"
                                    label="NIK"
                                    icon="badge"
                                    maxlength="16"
                                    hint="16 digit sesuai KTP"
                                    :error="personalForm.errors.nik"
                                />
                                <AppInput
                                    v-model="personalForm.name"
                                    label="Nama Lengkap"
                                    icon="person"
                                    required
                                    :error="personalForm.errors.name"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="personalForm.initials"
                                    label="Inisial"
                                    icon="short_text"
                                    maxlength="10"
                                    hint="Contoh: AB, JDO"
                                    :error="personalForm.errors.initials"
                                />
                                <AppInput
                                    v-model="personalForm.phone"
                                    label="Nomor HP (WhatsApp)"
                                    icon="phone"
                                    type="tel"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    required
                                    :error="personalForm.errors.phone"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="personalForm.birth_place"
                                    label="Tempat Lahir"
                                    icon="location_on"
                                    :error="personalForm.errors.birth_place"
                                />
                                <AppDatePicker
                                    v-model="personalForm.birth_date"
                                    label="Tanggal Lahir"
                                    :max="today"
                                    :error="personalForm.errors.birth_date"
                                />
                            </div>
                            <AppTextarea
                                v-model="personalForm.address"
                                label="Alamat Tinggal"
                                icon="home"
                                rows="3"
                                :error="personalForm.errors.address"
                            />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <SmartSelect
                                    v-model="personalForm.education"
                                    label="Pendidikan Terakhir"
                                    :options="educationOptions"
                                    icon="school"
                                    clearable
                                    :error="personalForm.errors.education"
                                />
                                <AppDatePicker
                                    v-model="personalForm.appointed_at"
                                    label="Mulai Menjabat"
                                    :error="personalForm.errors.appointed_at"
                                />
                                <AppDatePicker
                                    v-model="personalForm.term_end_at"
                                    label="Selesai Menjabat"
                                    :min="personalForm.appointed_at || undefined"
                                    :error="personalForm.errors.term_end_at"
                                />
                            </div>
                            <div class="flex justify-end border-t border-outline-variant pt-4">
                                <AppButton
                                    type="submit"
                                    icon="save"
                                    :loading="personalForm.processing"
                                    :disabled="personalForm.processing"
                                >
                                    Simpan Data Pribadi
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>

                    <AppCard v-show="activeTab === 'account'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Pengaturan Akun</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Ubah username atau password login Anda.</p>
                        <form class="space-y-4" @submit.prevent="submitAccount">
                            <AppInput
                                v-model="accountForm.username"
                                label="Username"
                                icon="alternate_email"
                                required
                                hint="Digunakan saat login ke sistem."
                                :error="accountForm.errors.username"
                            />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="accountForm.password"
                                    label="Password Baru"
                                    icon="lock"
                                    type="password"
                                    autocomplete="new-password"
                                    hint="Minimal 8 karakter. Kosongkan jika tidak diganti."
                                    :error="accountForm.errors.password"
                                />
                                <AppInput
                                    v-model="accountForm.password_confirmation"
                                    label="Konfirmasi Password"
                                    icon="lock_reset"
                                    type="password"
                                    autocomplete="new-password"
                                    :error="accountForm.errors.password_confirmation"
                                />
                            </div>
                            <div class="flex justify-end border-t border-outline-variant pt-4">
                                <AppButton
                                    type="submit"
                                    icon="save"
                                    :loading="accountForm.processing"
                                    :disabled="accountForm.processing"
                                >
                                    Simpan Akun
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>

                    <AppCard v-show="activeTab === 'account'" bordered class="space-y-5">
                        <template #header>
                            <div>
                                <h2 class="text-lg font-bold text-primary">Integrasi Akun Google</h2>
                                <p class="text-sm text-on-surface-variant">Hubungkan akun Google untuk login lebih cepat, pengiriman tagihan langganan/pelatihan melalui email, dan pemberitahuan fitur mendatang.</p>
                            </div>
                            <AppBadge :tone="props.google.connected ? 'success' : 'neutral'">
                                {{ props.google.connected ? 'Terhubung' : 'Belum Terhubung' }}
                            </AppBadge>
                        </template>

                        <div v-if="!props.google.connected" class="grid gap-3 sm:grid-cols-3">
                            <div
                                v-for="(benefit, index) in googleBenefits"
                                :key="index"
                                class="flex flex-col rounded-xl border border-outline-variant/60 bg-surface-container-low/50 p-4 transition-colors hover:bg-surface-container-low"
                            >
                                <div class="mb-3 grid size-10 place-items-center rounded-xl bg-primary-container/40 text-primary">
                                    <AppIcon :name="benefit.icon" class="text-xl" />
                                </div>
                                <p class="text-sm font-bold text-primary">{{ benefit.title }}</p>
                                <p class="mt-1 text-xs leading-relaxed text-on-surface-variant">{{ benefit.desc }}</p>
                            </div>
                        </div>

                        <div v-if="props.google.connected" class="space-y-4">
                            <div class="flex items-center gap-4 rounded-lg border border-outline-variant bg-surface-container-low p-4">
                                <img
                                    v-if="props.google.avatar"
                                    :src="props.google.avatar"
                                    alt="Foto akun Google"
                                    class="size-12 rounded-full border border-outline-variant object-cover"
                                />
                                <div v-else class="grid size-12 shrink-0 place-items-center rounded-full bg-primary-container text-on-primary-container">
                                    <AppIcon name="person" class="text-2xl" />
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-bold text-primary">{{ props.google.email }}</p>
                                    <p v-if="props.google.linked_at" class="text-xs text-on-surface-variant">
                                        Terhubung sejak {{ props.google.linked_at }}
                                    </p>
                                </div>
                            </div>

                            <div class="space-y-3 border-t border-outline-variant pt-4">
                                <p class="text-sm font-bold text-primary">Preferensi Notifikasi Email</p>
                                <form class="space-y-3" @submit.prevent="submitNotificationSettings">
                                    <AppSwitch
                                        v-model="notificationForm.billing"
                                        icon="receipt_long"
                                        label="Terima tagihan langganan &amp; pelatihan via email"
                                    />
                                    <AppSwitch
                                        v-model="notificationForm.announcements"
                                        icon="campaign"
                                        label="Terima pengumuman pembaruan fitur via email"
                                    />
                                    <div class="flex justify-end">
                                        <AppButton
                                            type="submit"
                                            icon="save"
                                            :loading="notificationForm.processing"
                                            :disabled="notificationForm.processing"
                                        >
                                            Simpan Preferensi
                                        </AppButton>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="flex justify-end border-t border-outline-variant pt-4">
                            <a
                                v-if="!props.google.connected"
                                href="/auth/google/redirect?action=link"
                                class="inline-flex"
                            >
                                <AppButton type="button" variant="secondary" size="base" class="font-bold shadow-xs transition-all hover:shadow-md">
                                    <template #icon>
                                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
                                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                        </svg>
                                    </template>
                                    Hubungkan Akun Google
                                </AppButton>
                            </a>
                            <AppButton
                                v-else
                                type="button"
                                variant="danger"
                                icon="link_off"
                                :loading="unlinkForm.processing"
                                @click="confirmUnlinkGoogle"
                            >
                                Putuskan Hubungan Google
                            </AppButton>
                        </div>
                    </AppCard>

                    <AppCard v-show="activeTab === 'photo'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Foto Profil</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">PNG, JPG, atau WebP. Maks 2 MB.</p>
                        <div class="flex flex-col items-center gap-5">
                            <div class="grid size-40 place-items-center overflow-hidden rounded-full border border-outline-variant bg-surface-container-lowest shadow-inner">
                                <img
                                    v-if="photoPreview && !imageError"
                                    :src="photoPreview"
                                    alt="Foto profil"
                                    class="size-full object-cover"
                                    @error="imageError = true"
                                />
                                <AppIcon v-else name="account_circle" class="text-6xl text-on-surface-variant" />
                            </div>
                            <form class="w-full space-y-3" @submit.prevent="submitPhoto">
                                <label
                                    class="block cursor-pointer rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-lowest p-6 text-center transition-colors hover:border-primary hover:bg-surface-container-low"
                                    :class="photoDragOver ? 'border-primary bg-primary-container/20' : ''"
                                    @dragover.prevent="photoDragOver = true"
                                    @dragleave="photoDragOver = false"
                                    @drop="onPhotoDrop"
                                >
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onPhotoChange" />
                                    <AppIcon name="upload" class="text-2xl text-on-surface-variant" />
                                    <p class="mt-2 text-sm font-bold text-primary">Tarik foto ke sini atau klik untuk pilih</p>
                                    <p class="mt-1 text-xs text-on-surface-variant">PNG / JPG / WebP · Maks 2 MB</p>
                                </label>
                                <p v-if="photoForm.errors.photo" class="text-sm text-error">{{ photoForm.errors.photo }}</p>
                                <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                                    <AppButton
                                        v-if="props.photoUrl || photoPreview"
                                        type="button"
                                        variant="danger"
                                        icon="delete"
                                        :loading="photoForm.processing"
                                        @click="destroyPhoto"
                                    >
                                        Hapus Foto
                                    </AppButton>
                                    <AppButton
                                        type="submit"
                                        icon="upload"
                                        :loading="photoForm.processing"
                                        :disabled="!photoForm.photo || photoForm.processing"
                                    >
                                        Unggah Foto
                                    </AppButton>
                                </div>
                            </form>
                        </div>
                    </AppCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
