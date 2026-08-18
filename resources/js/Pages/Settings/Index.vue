<script setup>
import { useConfirm } from '../../composables/useConfirm';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../Components/AppBadge.vue';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppDatePicker from '../../Components/AppDatePicker.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';
import AppSwitch from '../../Components/AppSwitch.vue';
import AppTextarea from '../../Components/AppTextarea.vue';
import SmartSelect from '../../Components/SmartSelect.vue';
import AppRichEditor from '../../Components/AppRichEditor.vue';
import AppTabs from '../../Components/AppTabs.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    identity: { type: Object, required: true },
    products: { type: Array, required: true },
    logoUrl: { type: String, default: null },
    whatsapp: { type: Object, required: true },
    signatures: { type: Object, required: true },
});

const page = usePage();
const route = computed(() => page.url);
const flash = computed(() => page.props.flash?.success);

const tabs = [
    { key: 'identity', label: 'Identitas Lembaga', icon: 'badge' },
    { key: 'lending-system', label: 'Sistem Pinjaman', icon: 'tune' },
    { key: 'logo', label: 'Logo Lembaga', icon: 'image' },
    { key: 'whatsapp', label: 'WhatsApp Gateway', icon: 'chat' },
    { key: 'signatures', label: 'Tanda Tangan', icon: 'draw' },
];

const activeTab = ref(getInitialTab());

function getInitialTab() {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) return tab;
    return flash.value?.tab ?? 'identity';
}

function go(tab) {
    router.get('/settings', { tab }, { preserveState: true, preserveScroll: true });
}

watch(() => route.value, () => {
    const url = new URL(window.location.href);
    const tab = url.searchParams.get('tab');
    if (tab && tabs.some((t) => t.key === tab)) activeTab.value = tab;
});

// === Identity form ===
const identityForm = useForm({
    legal_name: props.identity.legal_name ?? '',
    short_name: props.identity.short_name ?? '',
    registration_number: props.identity.registration_number ?? '',
    tax_number: props.identity.tax_number ?? '',
    address: props.identity.address ?? '',
    phone: props.identity.phone ?? '',
    email: props.identity.email ?? '',
    website: props.identity.website ?? '',
    timezone: props.identity.timezone ?? 'Asia/Jakarta',
    operational_start_date: props.identity.operational_start_date ?? '',
});
function submitIdentity() {
    identityForm.put('/settings/identity', { preserveScroll: true });
}

// === Lending system form ===
const lendingForm = useForm({
    products: props.products.map((p) => ({ ...p })),
});
const roundingOptions = [
    { value: 'decimal_2', label: '2 Desimal' },
    { value: 'rupiah_bersih', label: 'Rupiah Bersih' },
    { value: 'ceil_100', label: 'Ke Atas (Rp 100)' },
    { value: 'floor_100', label: 'Ke Bawah (Rp 100)' },
];
function submitLending() {
    lendingForm.put('/settings/lending-system', { preserveScroll: true });
}

// === Logo ===
const logoForm = useForm({ logo: null });
const logoPreview = ref(props.logoUrl);
const logoDragOver = ref(false);
function onLogoChange(event) {
    const file = event.target.files?.[0] ?? null;
    setLogoFile(file);
}
function onLogoDrop(event) {
    event.preventDefault();
    logoDragOver.value = false;
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (file) setLogoFile(file);
}
function setLogoFile(file) {
    if (!file || !file.type.startsWith('image/')) return;
    logoForm.logo = file;
    logoPreview.value = URL.createObjectURL(file);
}
function submitLogo() {
    logoForm.post('/settings/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => logoForm.reset(),
    });
}
const { confirm: confirmAction } = useConfirm();

async function destroyLogo() {
    if (!await confirmAction({ title: 'Hapus Logo', message: 'Hapus logo organisasi?' })) return;
    router.delete('/settings/logo', { preserveScroll: true });
}

// === WhatsApp ===
const whatsappForm = useForm({
    template_billing: props.whatsapp.template_billing ?? '',
    template_installment: props.whatsapp.template_installment ?? '',
    is_enabled: props.whatsapp.is_enabled ?? false,
});
const testResult = ref(null);
const testLoading = ref(false);
const createResult = ref(null);
const createLoading = ref(false);
const qrCode = ref(props.whatsapp.connection?.qr ?? null);
const instanceStatus = ref(props.whatsapp.connection?.state || props.whatsapp.connection?.status || 'unknown');
let pollTimer = null;

async function createInstance() {
    createLoading.value = true;
    createResult.value = null;
    try {
        const response = await fetch('/settings/whatsapp/create', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const res = await response.json();
        createResult.value = res;
        if (res.qr) qrCode.value = res.qr;
        if (res.state) instanceStatus.value = res.state;
        startPolling();
    } catch (e) {
        createResult.value = { success: false, message: e.message };
    } finally {
        createLoading.value = false;
    }
}

async function deleteInstance() {
    if (!await confirmAction({ title: 'Hapus Session WhatsApp', message: 'Apakah Anda yakin ingin menghapus session WhatsApp ini?' })) return;
    try {
        const response = await fetch('/settings/whatsapp/delete', {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        const res = await response.json();
        qrCode.value = null;
        instanceStatus.value = 'missing';
        createResult.value = { success: true, message: res.message || 'Session dihapus.' };
        stopPolling();
    } catch (e) {
        createResult.value = { success: false, message: e.message };
    }
}

function startPolling() {
    stopPolling();
    pollTimer = setInterval(async () => {
        try {
            const response = await fetch('/settings/whatsapp/state', {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const res = await response.json();
            if (res.qr) qrCode.value = res.qr;
            if (res.state || res.status) instanceStatus.value = res.state || res.status;
            if (['open', 'connected'].includes(instanceStatus.value.toLowerCase())) {
                stopPolling();
                qrCode.value = null;
            }
        } catch {
            // ignore
        }
    }, 3000);
}

function stopPolling() {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}
const pairResult = ref(null);
const pairLoading = ref(false);
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

function submitWhatsapp() {
    whatsappForm.put('/settings/whatsapp', { preserveScroll: true });
}
async function testConnection() {
    testLoading.value = true;
    testResult.value = null;
    try {
        const response = await fetch('/settings/whatsapp/test', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
        });
        testResult.value = await response.json();
    } catch (e) {
        testResult.value = { success: false, status: 'client_error', message: e.message };
    } finally {
        testLoading.value = false;
    }
}
async function pairDevice() {
    pairLoading.value = true;
    pairResult.value = null;
    try {
        const response = await fetch('/settings/whatsapp/pair', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            credentials: 'same-origin',
            body: JSON.stringify({ pairing_phone: whatsappForm.pairing_phone }),
        });
        pairResult.value = await response.json();
    } catch (e) {
        pairResult.value = { success: false, message: e.message, pairing_code: null };
    } finally {
        pairLoading.value = false;
    }
}

const connectionTone = computed(() => {
    const state = (props.whatsapp.connection?.state || props.whatsapp.connection?.status || '').toLowerCase();
    if (['open', 'connected'].includes(state)) return 'success';
    if (['unconfigured', 'missing', 'close', 'closed'].includes(state)) return 'warning';
    return 'neutral';
});

// === Signatures ===
const signatureReportKey = ref(props.signatures.reportTypes?.[0]?.key ?? 'default');
const signatureDrafts = ref({ ...(props.signatures.templates ?? {}) });
const signatureForm = useForm({ templates: {} });
const signatureReportOptions = computed(() =>
    (props.signatures.reportTypes ?? []).map((t) => ({ value: t.key, label: t.label })),
);
const currentSignatureHtml = computed({
    get: () => signatureDrafts.value[signatureReportKey.value] ?? '',
    set: (val) => {
        signatureDrafts.value = {
            ...signatureDrafts.value,
            [signatureReportKey.value]: val ?? '',
        };
    },
});
function submitSignatures() {
    signatureForm.templates = { ...signatureDrafts.value };
    signatureForm.put('/settings/signatures', { preserveScroll: true });
}
function applySignatureStarter() {
    currentSignatureHtml.value = `<table style="width:100%"><tbody><tr><td style="width:33%;text-align:center"><p>Mengetahui,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td><td style="width:33%;text-align:center"><p>Dibuat oleh,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td><td style="width:33%;text-align:center"><p>Bendahara,</p><p><br><br><br></p><p><strong>( ........................ )</strong></p></td></tr></tbody></table>`;
}
</script>

<template>
    <Head title="Pengaturan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">Pengaturan</h1>
                <p class="mt-1 text-on-surface-variant">Konfigurasi lembaga, pinjaman, logo, WhatsApp, dan tanda tangan.</p>
            </header>

            <AppCard v-if="flash">
                <div class="flex items-center gap-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-secondary-container text-secondary">✓</div>
                    <p class="font-bold text-primary">{{ flash.message }}</p>
                </div>
            </AppCard>

            <div class="grid gap-6 lg:grid-cols-[14rem_1fr]">
                <div class="rounded-xl bg-surface-container-low p-2 lg:sticky lg:top-20 lg:self-start">
                    <AppTabs
                        v-model="activeTab"
                        :items="tabs"
                        variant="pill"
                        aria-label="Tab pengaturan"
                        @update:model-value="go($event)"
                    />
                </div>

                <div class="space-y-6">
                    <AppCard v-show="activeTab === 'identity'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Identitas Lembaga</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Profil hukum dan kontak lembaga. Data ini muncul pada laporan dan dokumen resmi.</p>
                        <form class="space-y-5" @submit.prevent="submitIdentity">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput v-model="identityForm.legal_name" label="Nama Legal" required :error="identityForm.errors.legal_name" />
                                <AppInput v-model="identityForm.short_name" label="Nama Pendek" :error="identityForm.errors.short_name" />
                                <AppInput v-model="identityForm.registration_number" label="Nomor Badan Hukum" :error="identityForm.errors.registration_number" />
                                <AppInput v-model="identityForm.tax_number" label="NPWP" :error="identityForm.errors.tax_number" />
                                <AppInput v-model="identityForm.phone" label="Telepon" :error="identityForm.errors.phone" />
                                <AppInput v-model="identityForm.email" label="Email" type="email" :error="identityForm.errors.email" />
                                <AppInput v-model="identityForm.website" label="Website" type="url" :error="identityForm.errors.website" class="sm:col-span-2" />
                                <AppTextarea v-model="identityForm.address" label="Alamat" :rows="3" :error="identityForm.errors.address" class="sm:col-span-2" />
                                <AppInput v-model="identityForm.timezone" label="Zona Waktu" required :error="identityForm.errors.timezone" />
                                <AppDatePicker v-model="identityForm.operational_start_date" label="Tanggal Operasional Mulai" :error="identityForm.errors.operational_start_date" />
                            </div>
                            <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                                <AppButton type="submit" :loading="identityForm.processing" :disabled="identityForm.processing" icon="save">Simpan Identitas</AppButton>
                            </div>
                        </form>
                    </AppCard>

                    <AppCard v-show="activeTab === 'lending-system'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Sistem Pinjaman</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Default jasa, jangka, dan metode pembulatan angsuran per produk pinjaman.</p>
                        <div v-if="!lendingForm.products.length" class="rounded-lg border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">Belum ada produk pinjaman.</div>
                        <div v-else class="space-y-4">
                            <div v-for="(product, idx) in lendingForm.products" :key="product.row_id" class="grid gap-4 rounded-lg border border-outline-variant p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <p class="text-xs font-bold uppercase tracking-wider text-on-surface-variant">Produk</p>
                                    <p class="mt-1 font-bold text-primary">{{ product.code }}</p>
                                    <p class="text-sm text-on-surface-variant">{{ product.name }}</p>
                                </div>
                                <AppInput v-model.number="lendingForm.products[idx].default_interest_rate" label="Default Jasa (%)" type="number" step="0.01" :min="0" :max="100" :error="lendingForm.errors[`products.${idx}.default_interest_rate`]" />
                                <AppInput v-model.number="lendingForm.products[idx].default_term_months" label="Jangka (bulan)" type="number" :min="1" :max="240" :error="lendingForm.errors[`products.${idx}.default_term_months`]" />
                                <SmartSelect v-model="lendingForm.products[idx].rounding_method" :options="roundingOptions" label="Pembulatan" />
                            </div>
                        </div>
                        <div class="mt-5 flex justify-end gap-2 border-t border-outline-variant pt-4">
                            <AppButton type="button" :loading="lendingForm.processing" :disabled="lendingForm.processing || !lendingForm.products.length" icon="save" @click="submitLending">Simpan Sistem Pinjaman</AppButton>
                        </div>
                    </AppCard>

                    <AppCard v-show="activeTab === 'logo'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Logo Lembaga</h2>
                        <p class="mb-5 text-sm text-on-surface-variant">Logo akan tampil di sidebar aplikasi. Format: PNG, JPG, atau WebP. Maks 2 MB.</p>
                        <div class="flex flex-col items-center gap-5">
                            <div class="grid size-40 place-items-center overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest">
                                <img v-if="logoPreview" :src="logoPreview" alt="Logo" class="size-full object-contain" />
                                <AppIcon v-else name="image" class="text-5xl text-on-surface-variant" />
                            </div>
                            <form class="w-full space-y-3" @submit.prevent="submitLogo">
                                <label
                                    class="block cursor-pointer rounded-lg border-2 border-dashed border-outline-variant bg-surface-container-lowest p-6 text-center transition-colors hover:border-primary hover:bg-surface-container-low"
                                    :class="logoDragOver ? 'border-primary bg-primary-container/20' : ''"
                                    @dragover.prevent="logoDragOver = true"
                                    @dragleave="logoDragOver = false"
                                    @drop="onLogoDrop"
                                >
                                    <input type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onLogoChange" />
                                    <AppIcon name="upload" class="text-2xl text-on-surface-variant" />
                                    <p class="mt-2 text-sm font-bold text-primary">Tarik gambar ke sini atau klik untuk pilih</p>
                                    <p class="mt-1 text-xs text-on-surface-variant">PNG / JPG / WebP · Maks 2 MB</p>
                                </label>
                                <p v-if="logoForm.errors.logo" class="text-sm text-error">{{ logoForm.errors.logo }}</p>
                                <div class="flex justify-end gap-2 border-t border-outline-variant pt-4">
                                    <AppButton v-if="props.logoUrl" type="button" variant="danger" icon="delete" :loading="logoForm.processing" @click="destroyLogo">Hapus Logo</AppButton>
                                    <AppButton type="submit" :loading="logoForm.processing" :disabled="!logoForm.logo || logoForm.processing" icon="upload">Unggah Logo</AppButton>
                                </div>
                            </form>
                        </div>
                    </AppCard>

                    <AppCard v-show="activeTab === 'whatsapp'" bordered>
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <h2 class="text-lg font-bold text-primary">WhatsApp Gateway</h2>
                                <p class="mt-1 text-sm text-on-surface-variant">
                                    Pair nomor HP lewat Evolution API. Server: env global · instance
                                    <span class="font-semibold text-primary">{{ props.whatsapp.instance }}</span>
                                </p>
                            </div>
                            <AppBadge :tone="connectionTone">
                                {{ props.whatsapp.connection?.state || props.whatsapp.connection?.status || 'unknown' }}
                            </AppBadge>
                        </div>

                        <!-- Panel Buat Instance & QR Scan -->
                        <div class="mb-6 rounded-xl border border-outline-variant bg-surface-container-low p-4 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="font-bold text-primary">Aktifasi & Scan QR</p>
                                    <p class="text-xs text-on-surface-variant">
                                        Klik "Buat Instance" untuk mendapatkan QR Code scan WhatsApp Business.
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <AppButton
                                        type="button"
                                        variant="secondary"
                                        icon="qr_code_scanner"
                                        :loading="createLoading"
                                        :disabled="createLoading || !props.whatsapp.configured"
                                        @click="createInstance"
                                    >
                                        Buat Instance
                                    </AppButton>
                                    <AppButton
                                        type="button"
                                        variant="danger"
                                        icon="delete"
                                        :disabled="!props.whatsapp.configured"
                                        @click="deleteInstance"
                                    >
                                        Hapus Instance
                                    </AppButton>
                                </div>
                            </div>

                            <div v-if="qrCode" class="mt-4 flex flex-col items-center justify-center p-4 bg-surface rounded-lg border border-outline-variant">
                                <p class="mb-2 text-xs font-bold text-on-surface-variant">Scan QR Code ini menggunakan WhatsApp di HP Anda:</p>
                                <img :src="qrCode" alt="QR Code WhatsApp" class="size-64 object-contain" />
                                <p class="mt-2 text-xs text-on-surface-variant animate-pulse">Menunggu scan QR... (auto-refresh status tiap 3 detik)</p>
                            </div>

                            <div v-if="createResult" class="mt-2 text-xs" :class="createResult.success ? 'text-primary font-semibold' : 'text-error font-semibold'">
                                {{ createResult.message }}
                            </div>
                        </div>

                        <form class="space-y-5" @submit.prevent="submitWhatsapp">

                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppTextarea
                                    v-model="whatsappForm.template_billing"
                                    label="Pesan Tagihan"
                                    :rows="5"
                                    placeholder="Yth. Bapak/Ibu {nama}, tagihan angsuran ke-{angsuran_ke} sebesar Rp {total} jatuh tempo {tanggal}."
                                    :error="whatsappForm.errors.template_billing"
                                    hint="Placeholder: {nama}, {angsuran_ke}, {total}, {tanggal}, {pinjaman}, {produk}"
                                />
                                <AppTextarea
                                    v-model="whatsappForm.template_installment"
                                    label="Pesan Angsuran"
                                    :rows="5"
                                    placeholder="Terima kasih, pembayaran angsuran ke-{angsuran_ke} a/n {penyetor} sebesar Rp {total} telah diterima pada {tanggal}."
                                    :error="whatsappForm.errors.template_installment"
                                    hint="Placeholder: {nama}, {penyetor}, {angsuran_ke}, {total}, {pokok}, {jasa}, {denda}, {tanggal}, {pinjaman}"
                                />
                            </div>

                            <div class="flex items-center justify-between rounded-lg border border-outline-variant bg-surface-container-low px-4 py-3">
                                <div>
                                    <p class="text-sm font-bold text-primary">Aktifkan Gateway</p>
                                    <p class="text-xs text-on-surface-variant">Jika nonaktif, pengiriman WhatsApp akan diblokir.</p>
                                </div>
                                <AppSwitch v-model="whatsappForm.is_enabled" />
                            </div>

                            <div class="flex flex-wrap justify-between gap-2 border-t border-outline-variant pt-4">
                                <AppButton type="button" variant="secondary" icon="wifi" :loading="testLoading" :disabled="testLoading || !props.whatsapp.configured" @click="testConnection">
                                    Cek Status
                                </AppButton>
                                <AppButton type="submit" :loading="whatsappForm.processing" :disabled="whatsappForm.processing" icon="save">
                                    Simpan Pengaturan
                                </AppButton>
                            </div>
                        </form>

                        <div
                            v-if="testResult"
                            class="mt-4 flex items-start gap-3 rounded-lg border p-4"
                            :class="testResult.success ? 'border-secondary/30 bg-secondary-container/40' : 'border-error/30 bg-error-container/40'"
                        >
                            <div :class="['grid size-10 shrink-0 place-items-center rounded-full', testResult.success ? 'bg-secondary text-on-secondary' : 'bg-error text-on-error']">
                                {{ testResult.success ? '✓' : '!' }}
                            </div>
                            <div>
                                <p class="font-bold" :class="testResult.success ? 'text-secondary' : 'text-error'">
                                    {{ testResult.success ? 'Status OK' : 'Status Gagal' }}
                                </p>
                                <p class="mt-1 text-sm text-on-surface-variant">{{ testResult.message }}</p>
                                <p v-if="testResult.state || testResult.instance" class="mt-1 text-xs text-on-surface-variant">
                                    Instance: {{ testResult.instance || props.whatsapp.instance }} · State: {{ testResult.state || testResult.status }}
                                </p>
                            </div>
                        </div>
                    </AppCard>

                    <AppCard v-show="activeTab === 'signatures'" bordered>
                        <h2 class="mb-1 text-lg font-bold text-primary">Tanda Tangan</h2>
                        <p class="mb-4 text-sm text-on-surface-variant">
                            Blok penandatangan per jenis laporan. Disimpan per lembaga.
                        </p>
                        <div class="mb-5 flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-low p-4">
                            <AppIcon name="info" class="mt-0.5 text-primary" />
                            <p class="text-sm text-on-surface-variant">
                                Template tanda tangan akan dipakai otomatis setelah fitur
                                <span class="font-semibold text-primary">laporan</span> ditambahkan.
                                Saat ini hanya penyimpanan konfigurasi.
                            </p>
                        </div>
                        <form class="space-y-5" @submit.prevent="submitSignatures">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                                <div class="min-w-0 flex-1">
                                    <SmartSelect
                                        v-model="signatureReportKey"
                                        label="Jenis Laporan"
                                        :options="signatureReportOptions"
                                        required
                                    />
                                </div>
                                <AppButton
                                    type="button"
                                    variant="secondary"
                                    size="large"
                                    class="h-14 shrink-0"
                                    icon="table"
                                    @click="applySignatureStarter"
                                >
                                    Isi Template 1×3
                                </AppButton>
                            </div>
                            <AppRichEditor
                                :key="signatureReportKey"
                                v-model="currentSignatureHtml"
                                placeholder="Sisipkan tabel penandatangan (ikon tabel di toolbar)…"
                            />
                            <p v-if="signatureForm.errors.templates" class="text-sm text-error">
                                {{ signatureForm.errors.templates }}
                            </p>
                            <div class="flex justify-end border-t border-outline-variant pt-4">
                                <AppButton
                                    type="submit"
                                    icon="save"
                                    :loading="signatureForm.processing"
                                    :disabled="signatureForm.processing"
                                >
                                    Simpan Tanda Tangan
                                </AppButton>
                            </div>
                        </form>
                    </AppCard>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
