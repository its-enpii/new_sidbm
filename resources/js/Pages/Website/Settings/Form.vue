<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppBadge from '../../../Components/AppBadge.vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppEmptyState from '../../../Components/AppEmptyState.vue';
import AppFileUpload from '../../../Components/AppFileUpload.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import AppIconButton from '../../../Components/AppIconButton.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppSwitch from '../../../Components/AppSwitch.vue';
import AppTabs from '../../../Components/AppTabs.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';
import { useCan } from '../../../composables/useCan';

const props = defineProps({
    settings: { type: Object, required: true },
    heroImageUrl: { type: String, default: null },
    recentPosts: { type: Array, default: () => [] },
    siteStatus: { type: Object, default: () => ({ public_url: null, preview_url: null }) },
});

const { can } = useCan();
const activeStep = ref('template');

const steps = [
    { key: 'template', label: '1. Pilih Template', icon: 'dashboard' },
    { key: 'sections', label: '2. Kustomisasi Section', icon: 'view_quilt' },
    { key: 'publish', label: '3. Publikasi & Preview', icon: 'cloud_upload' },
];

const templates = [
    {
        key: 'classic',
        name: 'Classic BUMDes',
        icon: 'account_balance',
        description: 'Kartu identitas instansi dengan hero gradien formal. Cocok untuk situs resmi yang mengutamakan kesan birokratis dan rapi.',
        highlights: ['Hero gradien formal', 'Kartu standar instansi', 'Navigasi one-page'],
    },
    {
        key: 'modern',
        name: 'Modern Bento',
        icon: 'dashboard',
        description: 'Tata letak bento grid dengan kartu membulat dan aksen warna dinamis untuk lembaga yang ingin tampil kekinian.',
        highlights: ['Bento grid', 'Rounded-3xl', 'Aksen dinamis'],
    },
    {
        key: 'minimal',
        name: 'Editorial Minimal',
        icon: 'menu_book',
        description: 'Tipografi tajam di atas palet monokromatik bersih, dipisah garis pembatas elegan ala halaman editorial.',
        highlights: ['Tipografi tajam', 'Monokromatik bersih', 'Garis pembatas elegan'],
    },
];

const sectionMeta = [
    { key: 'hero', icon: 'campaign', title: 'Section Hero', hint: 'Kalimat pembuka, gambar utama, dan tombol ajakan di bagian paling atas situs.' },
    { key: 'about', icon: 'info', title: 'Section Tentang', hint: 'Profil singkat, visi, dan misi lembaga.' },
    { key: 'posts', icon: 'article', title: 'Section Berita', hint: 'Menampilkan hingga 6 kabar & pengumuman terbaru.' },
    { key: 'officers', icon: 'groups', title: 'Section Pengurus', hint: 'Struktur organisasi dan kontak masing-masing pengurus.' },
    { key: 'contact', icon: 'call', title: 'Section Kontak', hint: 'Alamat kantor, kanal komunikasi, dan formulir pesan pengunjung.' },
];

function defaultSections() {
    return {
        hero: { enabled: true, title: '', cta_label: '', cta_target: '' },
        about: { enabled: true, title: 'Tentang Kami', vision: '', mission: [], values: [], year_founded: '' },
        posts: { enabled: true, title: 'Kabar & Pengumuman', subtitle: '' },
        officers: { enabled: true, title: 'Struktur Organisasi', subtitle: '' },
        contact: { enabled: true, title: 'Kontak Kami', office_hours: '', contact_form_enabled: true },
    };
}

function toText(value) {
    return typeof value === 'string' ? value : '';
}

/**
 * Merge stored JSONB config over the builder defaults so every input always
 * binds to a string/boolean (never undefined) and unknown keys are dropped.
 */
function normalizeSections(stored) {
    const base = defaultSections();
    if (!stored || typeof stored !== 'object') {
        return base;
    }

    for (const key of Object.keys(base)) {
        const incoming = stored[key];
        if (!incoming || typeof incoming !== 'object') {
            continue;
        }
        for (const field of Object.keys(base[key])) {
            if (!Object.prototype.hasOwnProperty.call(incoming, field)) {
                continue;
            }
            const value = incoming[field];
            if (field === 'year_founded') {
                base[key][field] = value === null || value === undefined ? '' : value;
            } else if (typeof value === 'boolean') {
                base[key][field] = value;
            } else if (Array.isArray(value)) {
                base[key][field] = value.map(toText);
            } else if (value !== null && typeof value === 'object') {
                base[key][field] = toText(value);
            } else {
                base[key][field] = toText(value);
            }
        }
    }

    return base;
}

function officerRow(officer = {}) {
    return {
        name: toText(officer.name),
        position: toText(officer.position),
        email: toText(officer.email),
        phone: toText(officer.phone),
        social: toText(officer.social),
        photo_path: toText(officer.photo_path),
        photo_url: toText(officer.photo_url) || null,
        photo: null,
    };
}

const form = useForm({
    template: props.settings.template || 'classic',
    hero_tagline: toText(props.settings.hero_tagline),
    hero_description: toText(props.settings.hero_description),
    about_short: toText(props.settings.about_short),
    facebook_url: toText(props.settings.facebook_url),
    instagram_url: toText(props.settings.instagram_url),
    youtube_url: toText(props.settings.youtube_url),
    contact_phone: toText(props.settings.contact_phone),
    contact_email: toText(props.settings.contact_email),
    contact_address: toText(props.settings.contact_address),
    footer_note: toText(props.settings.footer_note),
    hero_image: null,
    remove_hero_image: false,
    is_published: props.settings.is_published ?? true,
    sections_config: normalizeSections(props.settings.sections_config),
    officers_data: (Array.isArray(props.settings.officers_data) ? props.settings.officers_data : []).map(officerRow),
    _method: 'PUT',
});

const templateName = computed(
    () => templates.find((template) => template.key === form.template)?.name ?? 'Classic BUMDes',
);

const previewUrl = computed(() => props.siteStatus?.preview_url || '/website/preview');
const publicUrl = computed(() => props.siteStatus?.public_url || '/');
const enabledSectionCount = computed(() =>
    sectionMeta.filter((section) => form.sections_config[section.key].enabled).length,
);
const visiblePosts = computed(() => props.recentPosts.slice(0, 6));

watch(() => form.hero_image, (file) => {
    if (file) {
        form.remove_hero_image = false;
    }
});

function chooseTemplate(key) {
    form.template = key;
}

function goStep(offset) {
    const index = steps.findIndex((step) => step.key === activeStep.value);
    const next = steps[index + offset];
    if (next) {
        activeStep.value = next.key;
    }
}

function openPreview() {
    window.open(previewUrl.value, '_blank');
}

function addMission() {
    form.sections_config.about.mission.push('');
}

function removeMission(index) {
    form.sections_config.about.mission.splice(index, 1);
}

function addOfficer() {
    form.officers_data.push(officerRow());
}

function removeOfficer(index) {
    form.officers_data.splice(index, 1);
}

// Object URLs are cached per File so re-renders never leak new blob entries.
const photoObjectUrls = new WeakMap();

function previewForObject(file) {
    if (!(file instanceof File)) {
        return null;
    }
    if (!photoObjectUrls.has(file)) {
        photoObjectUrls.set(file, URL.createObjectURL(file));
    }
    return photoObjectUrls.get(file);
}

function clearOfficerPhoto(index) {
    const row = form.officers_data[index];
    row.photo = null;
    row.photo_path = '';
    row.photo_url = null;
}

function formatDate(value) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' });
}

// Rows without a name (and without a fresh portrait) are dropped by the
// request's prepareForValidation; mirroring that here keeps the returned
// `officers_data.{index}.name` error keys aligned with the visible rows.
function isMeaningfulOfficer(row) {
    return row.name.trim() !== '' || row.photo instanceof File;
}

function submit() {
    form.transform((data) => ({
        ...data,
        officers_data: data.officers_data.filter(isMeaningfulOfficer),
    }));

    form.post(route('website.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.hero_image = null;
            form.remove_hero_image = false;
            form.officers_data.forEach((row) => {
                row.photo = null;
            });
        },
    });
}

function errorOf(key) {
    return form.errors[key] ?? null;
}
</script>

<template>
    <Head title="Penyusun Situs" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <header class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-primary">Penyusun Situs</h1>
                    <p class="mt-1 text-on-surface-variant">
                        Rancang halaman depan situs resmi lembaga dalam tiga langkah: pilih template,
                        kustomisasi section, lalu publikasikan.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <AppBadge :tone="form.is_published ? 'success' : 'warning'">
                        {{ form.is_published ? 'Situs Publik' : 'Situs Draf' }}
                    </AppBadge>
                    <AppBadge tone="info-soft">{{ enabledSectionCount }}/5 section aktif</AppBadge>
                </div>
            </header>

            <AppCard :padded="false">
                <div class="px-3 pt-2 sm:px-5">
                    <AppTabs :items="steps" v-model="activeStep" aria-label="Langkah penyusun situs" />
                </div>
            </AppCard>

            <form class="space-y-6" @submit.prevent="submit">
                <!-- STEP 1 — Template -->
                <div v-if="activeStep === 'template'" class="space-y-6">
                    <AppCard padded>
                        <h2 class="text-base font-bold text-primary">Pilih Template</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            Template menentukan gaya visual seluruh section pada halaman depan situs publik.
                        </p>
                        <p v-if="errorOf('template')" class="mt-2 text-sm text-error">{{ errorOf('template') }}</p>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <AppCard
                                v-for="template in templates"
                                :key="template.key"
                                padded
                                bordered
                                class="flex h-full flex-col"
                                :class="form.template === template.key
                                    ? 'border-primary bg-primary-container/10'
                                    : 'bg-surface-container-lowest'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <AppIcon :name="template.icon" tone="primary" :container-size="10" />
                                    <AppBadge v-if="form.template === template.key" tone="success">Terpilih</AppBadge>
                                    <AppBadge v-else tone="neutral">Cadangan</AppBadge>
                                </div>
                                <h3 class="mt-3 text-sm font-bold text-primary">{{ template.name }}</h3>
                                <p class="mt-1 text-xs leading-relaxed text-on-surface-variant">{{ template.description }}</p>
                                <ul class="mt-3 space-y-1.5">
                                    <li
                                        v-for="highlight in template.highlights"
                                        :key="highlight"
                                        class="flex items-center gap-2 text-xs text-on-surface-variant"
                                    >
                                        <AppIcon name="check_circle" class="text-base text-secondary" />
                                        {{ highlight }}
                                    </li>
                                </ul>
                                <div class="mt-auto pt-4">
                                    <AppButton
                                        class="w-full"
                                        size="compact"
                                        :variant="form.template === template.key ? 'primary' : 'secondary'"
                                        :icon="form.template === template.key ? 'check_circle' : 'add_circle'"
                                        @click="chooseTemplate(template.key)"
                                    >
                                        {{ form.template === template.key ? 'Template Aktif' : 'Pilih Template' }}
                                    </AppButton>
                                </div>
                            </AppCard>
                        </div>
                    </AppCard>

                </div>

                <!-- STEP 2 — Sections -->
                <div v-if="activeStep === 'sections'" class="space-y-6">
                    <!-- HERO -->
                    <AppCard padded>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="campaign" tone="primary" :container-size="9" />
                                <div>
                                    <h2 class="text-base font-bold text-primary">Section Hero</h2>
                                    <p class="text-sm text-on-surface-variant">{{ sectionMeta[0].hint }}</p>
                                </div>
                            </div>
                        </template>

                        <AppSwitch
                            v-model="form.sections_config.hero.enabled"
                            label="Tampilkan Section Hero"
                            description="Sembunyikan bila halaman depan tidak memerlukan banner pembuka."
                            icon="visibility"
                        />

                        <div v-if="form.sections_config.hero.enabled" class="mt-5 space-y-5">
                            <AppInput
                                v-model="form.sections_config.hero.title"
                                label="Judul Hero"
                                icon="title"
                                hint="Kosongkan untuk memakai nama lembaga."
                                :error="errorOf('sections_config.hero.title')"
                            />
                            <AppInput
                                v-model="form.hero_tagline"
                                label="Tagline"
                                icon="verified"
                                :error="errorOf('hero_tagline')"
                            />
                            <AppTextarea
                                v-model="form.hero_description"
                                label="Deskripsi"
                                icon="notes"
                                :error="errorOf('hero_description')"
                            />
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.sections_config.hero.cta_label"
                                    label="Label Tombol CTA"
                                    icon="label"
                                    placeholder="Contoh: Portal Pengelolaan Keuangan"
                                    :error="errorOf('sections_config.hero.cta_label')"
                                />
                                <AppInput
                                    v-model="form.sections_config.hero.cta_target"
                                    label="Tautan Tujuan CTA"
                                    icon="link"
                                    placeholder="/login atau #kontak"
                                    :error="errorOf('sections_config.hero.cta_target')"
                                />
                            </div>

                            <div class="space-y-3 border-t border-outline-variant pt-4">
                                <AppFileUpload
                                    v-model="form.hero_image"
                                    label="Gambar Hero"
                                    accept="image/png,image/jpeg,image/webp"
                                    hint="Opsional. PNG/JPG/WebP maksimal 2 MB."
                                    :error="errorOf('hero_image')"
                                />
                                <div v-if="heroImageUrl && !form.remove_hero_image" class="flex flex-wrap items-center gap-4">
                                    <img
                                        :src="heroImageUrl"
                                        alt="Gambar hero saat ini"
                                        class="h-24 w-40 rounded-xl border border-outline-variant object-cover"
                                    >
                                    <AppButton
                                        variant="secondary"
                                        size="compact"
                                        type="button"
                                        icon="delete"
                                        @click="form.remove_hero_image = true"
                                    >
                                        Hapus gambar
                                    </AppButton>
                                </div>
                                <p v-if="form.remove_hero_image" class="ml-1 text-sm text-tertiary">
                                    Gambar akan dihapus saat disimpan.
                                    <AppButton variant="ghost" size="compact" type="button" @click="form.remove_hero_image = false">
                                        Batalkan
                                    </AppButton>
                                </p>
                            </div>
                        </div>
                        <AppEmptyState
                            v-else
                            icon="visibility_off"
                            title="Section Hero dinonaktifkan"
                            description="Section ini tidak akan tampil di halaman depan situs publik."
                        />
                    </AppCard>

                    <!-- ABOUT -->
                    <AppCard padded>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="info" tone="primary" :container-size="9" />
                                <div>
                                    <h2 class="text-base font-bold text-primary">Section Tentang</h2>
                                    <p class="text-sm text-on-surface-variant">{{ sectionMeta[1].hint }}</p>
                                </div>
                            </div>
                        </template>

                        <AppSwitch
                            v-model="form.sections_config.about.enabled"
                            label="Tampilkan Section Tentang"
                            description="Bila dimatikan, profil singkat, visi, dan misi disembunyikan dari halaman depan."
                            icon="visibility"
                        />

                        <div v-if="form.sections_config.about.enabled" class="mt-5 space-y-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.sections_config.about.title"
                                    label="Judul Section"
                                    icon="title"
                                    :error="errorOf('sections_config.about.title')"
                                />
                                <AppInput
                                    v-model="form.sections_config.about.year_founded"
                                    label="Tahun Berdiri"
                                    icon="event"
                                    type="number"
                                    placeholder="Contoh: 2016"
                                    :error="errorOf('sections_config.about.year_founded')"
                                />
                            </div>
                            <AppTextarea
                                v-model="form.about_short"
                                label="Ringkasan Profil"
                                icon="article"
                                placeholder="Paragraf singkat tentang lembaga"
                                :error="errorOf('about_short')"
                            />
                            <AppTextarea
                                v-model="form.sections_config.about.vision"
                                label="Visi"
                                icon="visibility"
                                placeholder="Arah besar lembaga"
                                :error="errorOf('sections_config.about.vision')"
                            />

                            <div class="space-y-3 border-t border-outline-variant pt-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-primary">Misi</h3>
                                    <AppButton size="compact" variant="secondary" type="button" icon="add" @click="addMission">
                                        Tambah Misi
                                    </AppButton>
                                </div>
                                <p v-if="!form.sections_config.about.mission.length" class="ml-1 text-sm text-on-surface-variant">
                                    Belum ada misi. Tambahkan butir misi agar tampil sebagai daftar di halaman depan.
                                </p>
                                <div
                                    v-for="(_mission, index) in form.sections_config.about.mission"
                                    :key="`mission-${index}`"
                                    class="flex items-end gap-2"
                                >
                                    <div class="flex-1">
                                        <AppInput
                                            :id="`mission-${index}`"
                                            v-model="form.sections_config.about.mission[index]"
                                            :label="`Misi ${index + 1}`"
                                            icon="format_list_bulleted"
                                            placeholder="Contoh: Menyalurkan dana bergulir tepat sasaran"
                                            :error="errorOf(`sections_config.about.mission.${index}`)"
                                        />
                                    </div>
                                    <AppIconButton
                                        name="delete"
                                        tone="danger"
                                        tooltip="Hapus misi"
                                        aria-label="Hapus butir misi"
                                        @click="removeMission(index)"
                                    />
                                </div>
                            </div>
                        </div>
                        <AppEmptyState
                            v-else
                            icon="visibility_off"
                            title="Section Tentang dinonaktifkan"
                            description="Profil singkat, visi, dan misi tidak ditampilkan di halaman depan."
                        />
                    </AppCard>

                    <!-- POSTS -->
                    <AppCard padded>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="article" tone="primary" :container-size="9" />
                                <div>
                                    <h2 class="text-base font-bold text-primary">Section Berita</h2>
                                    <p class="text-sm text-on-surface-variant">{{ sectionMeta[2].hint }}</p>
                                </div>
                            </div>
                        </template>

                        <AppSwitch
                            v-model="form.sections_config.posts.enabled"
                            label="Tampilkan Section Berita"
                            description="Section ini otomatis mengisi dirinya dengan 6 berita berstatus terbit."
                            icon="visibility"
                        />

                        <div v-if="form.sections_config.posts.enabled" class="mt-5 space-y-5">
                            <AppInput
                                v-model="form.sections_config.posts.title"
                                label="Judul Section"
                                icon="title"
                                :error="errorOf('sections_config.posts.title')"
                            />
                            <AppTextarea
                                v-model="form.sections_config.posts.subtitle"
                                label="Subjudul"
                                icon="notes"
                                placeholder="Kalimat pendukung di bawah judul section"
                                :error="errorOf('sections_config.posts.subtitle')"
                            />

                            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-sm font-semibold text-primary">Berita terbit saat ini</p>
                                    <AppBadge :tone="visiblePosts.length ? 'success' : 'warning'">
                                        {{ visiblePosts.length }} dari 6 slot
                                    </AppBadge>
                                </div>
                                <ul v-if="visiblePosts.length" class="mt-3 space-y-2">
                                    <li
                                        v-for="post in visiblePosts"
                                        :key="post.slug || post.id"
                                        class="flex items-start justify-between gap-3 border-b border-outline-variant/60 pb-2 text-sm last:border-0 last:pb-0"
                                    >
                                        <span class="min-w-0 truncate text-on-surface">{{ post.title }}</span>
                                        <span class="shrink-0 text-xs text-on-surface-variant">{{ formatDate(post.published_at) }}</span>
                                    </li>
                                </ul>
                                <p v-else class="mt-3 text-sm text-on-surface-variant">
                                    Belum ada berita terbit. Tulis berita baru di menu Website &rsaquo; Berita agar section ini terisi.
                                </p>
                            </div>
                        </div>
                        <AppEmptyState
                            v-else
                            icon="visibility_off"
                            title="Section Berita dinonaktifkan"
                            description="Kabar dan pengumuman tidak ditampilkan di halaman depan."
                        />
                    </AppCard>

                    <!-- OFFICERS -->
                    <AppCard padded>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="groups" tone="primary" :container-size="9" />
                                <div>
                                    <h2 class="text-base font-bold text-primary">Section Pengurus</h2>
                                    <p class="text-sm text-on-surface-variant">{{ sectionMeta[3].hint }}</p>
                                </div>
                            </div>
                        </template>

                        <AppSwitch
                            v-model="form.sections_config.officers.enabled"
                            label="Tampilkan Section Pengurus"
                            description="Section tetap tersembunyi bila daftar pengurus masih kosong."
                            icon="visibility"
                        />

                        <div v-if="form.sections_config.officers.enabled" class="mt-5 space-y-5">
                            <AppInput
                                v-model="form.sections_config.officers.title"
                                label="Judul Section"
                                icon="title"
                                :error="errorOf('sections_config.officers.title')"
                            />
                            <AppTextarea
                                v-model="form.sections_config.officers.subtitle"
                                label="Subjudul"
                                icon="notes"
                                placeholder="Contoh: Susunan pengurus yang sah berdasarkan SK terbaru"
                                :error="errorOf('sections_config.officers.subtitle')"
                            />

                            <div class="space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-sm font-bold uppercase tracking-wider text-primary">Daftar Pengurus</h3>
                                    <AppBadge tone="info-soft">{{ form.officers_data.length }} baris</AppBadge>
                                </div>

                                <AppEmptyState
                                    v-if="!form.officers_data.length"
                                    icon="person_search"
                                    title="Belum ada pengurus"
                                    description="Tambahkan baris pengurus untuk menampilkan struktur organisasi di halaman depan."
                                />

                                <div
                                    v-for="(officer, index) in form.officers_data"
                                    :key="`officer-${index}`"
                                    class="rounded-xl border border-outline-variant bg-surface-container-low p-4"
                                >
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-bold text-primary">Pengurus {{ index + 1 }}</p>
                                        <AppIconButton
                                            name="delete"
                                            tone="danger"
                                            tooltip="Hapus baris pengurus"
                                            :aria-label="`Hapus pengurus ${index + 1}`"
                                            @click="removeOfficer(index)"
                                        />
                                    </div>

                                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                        <AppInput
                                            v-model="officer.name"
                                            label="Nama Lengkap"
                                            icon="person"
                                            :error="errorOf(`officers_data.${index}.name`)"
                                        />
                                        <AppInput
                                            v-model="officer.position"
                                            label="Jabatan"
                                            icon="badge"
                                            placeholder="Contoh: Direktur"
                                            :error="errorOf(`officers_data.${index}.position`)"
                                        />
                                        <AppInput
                                            v-model="officer.email"
                                            label="Email"
                                            icon="mail"
                                            type="email"
                                            :error="errorOf(`officers_data.${index}.email`)"
                                        />
                                        <AppInput
                                            v-model="officer.phone"
                                            label="Telepon"
                                            icon="call"
                                            placeholder="08xx-xxxx-xxxx"
                                            :error="errorOf(`officers_data.${index}.phone`)"
                                        />
                                    </div>

                                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                        <AppFileUpload
                                            v-model="officer.photo"
                                            label="Foto Pengurus"
                                            accept="image/png,image/jpeg,image/webp"
                                            hint="Opsional. Kotak 1:1 disarankan."
                                            :error="errorOf(`officers_data.${index}.photo`)"
                                        />
                                        <div class="flex items-center justify-center rounded-xl border border-outline-variant bg-surface-container-lowest p-3">
                                            <img
                                                v-if="officer.photo"
                                                :src="previewForObject(officer.photo)"
                                                :alt="`Pratinjau foto pengurus ${index + 1}`"
                                                class="size-24 rounded-lg object-cover"
                                            >
                                            <img
                                                v-else-if="officer.photo_url"
                                                :src="officer.photo_url"
                                                :alt="`Foto pengurus ${index + 1}`"
                                                class="size-24 rounded-lg object-cover"
                                            >
                                            <AppIcon v-else name="portrait" class="text-3xl text-outline" />
                                            <AppButton
                                                v-if="officer.photo || officer.photo_url"
                                                class="ml-3"
                                                variant="ghost"
                                                size="compact"
                                                type="button"
                                                icon="delete"
                                                @click="clearOfficerPhoto(index)"
                                            >
                                                Hapus foto
                                            </AppButton>
                                        </div>
                                    </div>
                                </div>

                                <AppButton variant="secondary" type="button" icon="add" @click="addOfficer">
                                    Tambah Pengurus
                                </AppButton>
                                <p class="ml-1 text-xs text-on-surface-variant">
                                    Baris tanpa nama akan diabaikan saat disimpan.
                                </p>
                            </div>
                        </div>
                        <AppEmptyState
                            v-else
                            icon="visibility_off"
                            title="Section Pengurus dinonaktifkan"
                            description="Struktur organisasi tidak ditampilkan di halaman depan."
                        />
                    </AppCard>

                    <!-- CONTACT -->
                    <AppCard padded>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <AppIcon name="call" tone="primary" :container-size="9" />
                                <div>
                                    <h2 class="text-base font-bold text-primary">Section Kontak</h2>
                                    <p class="text-sm text-on-surface-variant">{{ sectionMeta[4].hint }}</p>
                                </div>
                            </div>
                        </template>

                        <AppSwitch
                            v-model="form.sections_config.contact.enabled"
                            label="Tampilkan Section Kontak"
                            description="Alamat, kanal komunikasi, dan formulir pesan pengunjung."
                            icon="visibility"
                        />

                        <div v-if="form.sections_config.contact.enabled" class="mt-5 space-y-5">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.sections_config.contact.title"
                                    label="Judul Section"
                                    icon="title"
                                    :error="errorOf('sections_config.contact.title')"
                                />
                                <AppInput
                                    v-model="form.sections_config.contact.office_hours"
                                    label="Jam Operasional"
                                    icon="schedule"
                                    placeholder="Contoh: Senin–Jumat, 08.00–16.00 WIB"
                                    :error="errorOf('sections_config.contact.office_hours')"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="form.contact_phone"
                                    label="Telepon / WhatsApp"
                                    icon="call"
                                    placeholder="Contoh: 0812-3456-7890"
                                    :error="errorOf('contact_phone')"
                                />
                                <AppInput
                                    v-model="form.contact_email"
                                    label="Email Kontak"
                                    icon="mail"
                                    type="email"
                                    placeholder="contoh@desa.test"
                                    :error="errorOf('contact_email')"
                                />
                            </div>
                            <AppTextarea
                                v-model="form.contact_address"
                                label="Alamat Kantor"
                                icon="place"
                                placeholder="Alamat yang tampil di halaman kontak"
                                :error="errorOf('contact_address')"
                            />

                            <div class="grid gap-4 border-t border-outline-variant pt-4 sm:grid-cols-3">
                                <AppInput
                                    v-model="form.facebook_url"
                                    label="Facebook"
                                    icon="public"
                                    type="url"
                                    placeholder="https://facebook.com/nama-lembaga"
                                    :error="errorOf('facebook_url')"
                                />
                                <AppInput
                                    v-model="form.instagram_url"
                                    label="Instagram"
                                    icon="photo_camera"
                                    type="url"
                                    placeholder="https://instagram.com/nama-lembaga"
                                    :error="errorOf('instagram_url')"
                                />
                                <AppInput
                                    v-model="form.youtube_url"
                                    label="YouTube"
                                    icon="smart_display"
                                    type="url"
                                    placeholder="https://youtube.com/@nama-lembaga"
                                    :error="errorOf('youtube_url')"
                                />
                            </div>

                            <AppSwitch
                                v-model="form.sections_config.contact.contact_form_enabled"
                                label="Formulir Pesan Pengunjung"
                                description="Bila aktif, pengunjung dapat mengirim pesan dari section kontak ke kotak masuk Website."
                                icon="send"
                            />
                        </div>
                        <AppEmptyState
                            v-else
                            icon="visibility_off"
                            title="Section Kontak dinonaktifkan"
                            description="Informasi kontak tetap tersedia di halaman /kontak."
                        />
                    </AppCard>

                    <AppCard padded>
                        <h2 class="text-base font-bold text-primary">Catatan Footer</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            Teks singkat di bagian bawah situs, misalnya hak cipta atau tautan layanan.
                        </p>
                        <div class="mt-4">
                            <AppInput
                                v-model="form.footer_note"
                                label="Catatan Footer"
                                icon="copyright"
                                :error="errorOf('footer_note')"
                            />
                        </div>
                    </AppCard>
                </div>

                <!-- STEP 3 — Publication -->
                <div v-if="activeStep === 'publish'" class="space-y-6">
                    <AppCard padded>
                        <h2 class="text-base font-bold text-primary">Status Publikasi</h2>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            Situs draf tetap dapat dipratinjau dari penyusun, tetapi tidak ditampilkan kepada pengunjung.
                        </p>

                        <div class="mt-4 space-y-5">
                            <AppSwitch
                                v-model="form.is_published"
                                label="Publikasikan Situs"
                                description="Situs hanya dapat diakses pengunjung ketika status ini aktif."
                                icon="cloud_done"
                            />
                            <p v-if="errorOf('is_published')" class="ml-1 text-sm text-error">{{ errorOf('is_published') }}</p>

                            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <p class="text-sm font-bold uppercase tracking-wider text-primary">Ringkasan Konfigurasi</p>
                                <ul class="mt-3 space-y-2 text-sm">
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="text-on-surface-variant">Template</span>
                                        <AppBadge tone="primary">{{ templateName }}</AppBadge>
                                    </li>
                                    <li v-for="section in sectionMeta" :key="section.key" class="flex items-center justify-between gap-3">
                                        <span class="text-on-surface-variant">{{ section.title }}</span>
                                        <AppBadge :tone="form.sections_config[section.key].enabled ? 'success' : 'neutral'">
                                            {{ form.sections_config[section.key].enabled ? 'Aktif' : 'Nonaktif' }}
                                        </AppBadge>
                                    </li>
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="text-on-surface-variant">Pengurus terdaftar</span>
                                        <AppBadge tone="info-soft">{{ form.officers_data.length }} orang</AppBadge>
                                    </li>
                                    <li class="flex items-center justify-between gap-3">
                                        <span class="text-on-surface-variant">Status publikasi</span>
                                        <AppBadge :tone="form.is_published ? 'success' : 'warning'">
                                            {{ form.is_published ? 'Publik' : 'Draf' }}
                                        </AppBadge>
                                    </li>
                                </ul>
                            </div>

                            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4">
                                <p class="text-sm font-bold uppercase tracking-wider text-primary">Tautan Situs</p>
                                <p class="mt-2 truncate text-sm text-on-surface-variant">{{ publicUrl }}</p>
                            </div>
                        </div>
                    </AppCard>

                    <div class="flex flex-col gap-3 sm:flex-row sm:justify-end">
                        <AppButton variant="secondary" type="button" icon="open_in_new" @click="openPreview">
                            Buka Live Preview
                        </AppButton>
                        <AppButton
                            v-if="can('website.manage')"
                            type="submit"
                            variant="success"
                            icon="rocket_launch"
                            :loading="form.processing"
                        >
                            Simpan &amp; Publikasikan
                        </AppButton>
                    </div>
                </div>

                <!-- Wizard navigation -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <AppButton
                        v-if="activeStep !== 'template'"
                        variant="ghost"
                        type="button"
                        icon="arrow_back"
                        @click="goStep(-1)"
                    >
                        Sebelumnya
                    </AppButton>
                    <span v-else />
                    <div class="flex items-center gap-3">
                        <AppButton v-if="activeStep !== 'publish'" variant="secondary" type="button" icon="arrow_forward" @click="goStep(1)">
                            Lanjut
                        </AppButton>
                        <AppButton
                            v-if="activeStep === 'publish' && can('website.manage')"
                            type="submit"
                            icon="save"
                            :loading="form.processing"
                        >
                            Simpan Perubahan
                        </AppButton>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
