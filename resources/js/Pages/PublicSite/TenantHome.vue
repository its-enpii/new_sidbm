<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import AppIcon from '@/Components/AppIcon.vue';
import AppInput from '@/Components/AppInput.vue';
import AppTextarea from '@/Components/AppTextarea.vue';

const props = defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, default: () => ({}) },
    settings: { type: Object, default: () => ({}) },
    recent_posts: { type: Array, default: () => [] },
    is_preview: { type: Boolean, default: false },
});

const page = usePage();

const SECTION_DEFAULTS = {
    hero: { enabled: true, title: null, cta_label: null, cta_target: null },
    about: { enabled: true, title: 'Tentang Kami', vision: null, mission: [], values: [], year_founded: null },
    posts: { enabled: true, title: 'Kabar & Pengumuman', subtitle: null },
    officers: { enabled: true, title: 'Struktur Organisasi', subtitle: null },
    contact: { enabled: true, title: 'Kontak Kami', office_hours: null, contact_form_enabled: true },
};

function section(key) {
    const stored = props.settings?.sections_config?.[key];
    return { ...SECTION_DEFAULTS[key], ...(stored && typeof stored === 'object' ? stored : {}) };
}

const hero = computed(() => section('hero'));
const about = computed(() => section('about'));
const posts = computed(() => section('posts'));
const officers = computed(() => section('officers'));
const contact = computed(() => section('contact'));

const template = computed(() => props.settings?.template || 'classic');

const officerList = computed(() => (Array.isArray(props.settings?.officers_data) ? props.settings.officers_data : []));
const latestPosts = computed(() => (posts.value.enabled ? props.recent_posts.slice(0, 6) : []));

// One-page anchor nav: only sections that are both enabled and filled show up.
const navItems = computed(() => [
    { id: 'hero', label: 'Beranda', icon: 'home', enabled: hero.value.enabled },
    { id: 'about', label: 'Tentang', icon: 'info', enabled: about.value.enabled },
    { id: 'berita', label: 'Berita', icon: 'article', enabled: posts.value.enabled && latestPosts.value.length > 0 },
    { id: 'pengurus', label: 'Pengurus', icon: 'groups', enabled: officers.value.enabled && officerList.value.length > 0 },
    { id: 'kontak', label: 'Kontak', icon: 'call', enabled: contact.value.enabled },
].filter((item) => item.enabled));
const social = computed(() => props.settings?.social ?? {});
const hasSocial = computed(() => Boolean(social.value.facebook || social.value.instagram || social.value.youtube));

const orgName = computed(() => props.organization?.name ?? 'Lembaga');
const orgLegalName = computed(() => props.organization?.legal_name || orgName.value);
const orgLocation = computed(() => [props.organization?.district_name, props.organization?.regency_name].filter(Boolean).join(', '));
const heroTitle = computed(() => hero.value.title?.trim() || orgLegalName.value);
const heroTagline = computed(() => props.settings?.hero_tagline || 'Situs Resmi');
const heroDescription = computed(() => props.settings?.hero_description
    || props.settings?.about_short
    || `Portal informasi resmi ${orgName.value} — pengelolaan dana bergulir masyarakat yang transparan dan akuntabel.`);
const heroCtaLabel = computed(() => hero.value.cta_label?.trim() || 'Masuk Sistem');
const heroCtaTarget = computed(() => hero.value.cta_target?.trim() || '/login');
const heroImage = computed(() => props.settings?.hero_image_url || null);

const aboutSummary = computed(() => props.settings?.about_short || null);
const missionItems = computed(() => (Array.isArray(about.value.mission) ? about.value.mission.filter(Boolean) : []));
const valueItems = computed(() => (Array.isArray(about.value.values) ? about.value.values.filter(Boolean) : []));
const foundedYear = computed(() => about.value.year_founded || props.organization?.operational_start_year || null);

const address = computed(() => props.settings?.contact_address || props.organization?.address || null);
const phone = computed(() => props.settings?.contact_phone || props.organization?.phone || null);
const email = computed(() => props.settings?.contact_email || props.organization?.email || null);
const contactFormEnabled = computed(() => contact.value.enabled && contact.value.contact_form_enabled !== false);

const canonicalUrl = computed(() => `${page.props.meta.canonical_base}/`);
const currentYear = computed(() => new Date().getFullYear());

// ——— Template-aware styling: static token classes selected per template ———
const styles = computed(() => {
    if (template.value === 'modern') {
        return {
            shell: 'bg-surface text-on-surface',
            section: 'mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8',
            rule: '',
            card: 'rounded-3xl border border-outline-variant/50 bg-surface-container-lowest p-6 shadow-lg transition hover:-translate-y-0.5 hover:shadow-xl sm:p-7',
            cardSoft: 'rounded-3xl border border-outline-variant/50 bg-surface-container-low p-5 sm:p-6',
            chip: 'inline-flex items-center gap-2 rounded-full bg-secondary-container px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-secondary',
            heading: 'text-3xl font-extrabold tracking-tight text-primary sm:text-4xl',
            subheading: 'mt-3 max-w-2xl text-base leading-relaxed text-on-surface-variant',
            media: 'overflow-hidden rounded-3xl bg-surface-container',
            grid: 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3',
            listBullet: 'bg-secondary-container text-secondary',
            footer: 'border-t border-outline-variant/50 bg-surface-container-low py-10',
        };
    }

    if (template.value === 'minimal') {
        return {
            shell: 'bg-surface-container-lowest text-on-surface',
            section: 'mx-auto max-w-5xl px-4 py-16 sm:px-6 lg:px-8',
            rule: 'border-t border-outline-variant',
            card: 'rounded-none border border-outline-variant bg-surface-container-lowest p-6 sm:p-8',
            cardSoft: 'rounded-none border border-outline-variant bg-surface-container-low p-5',
            chip: 'inline-flex items-center gap-2 border-b border-primary pb-1 text-[11px] font-bold uppercase tracking-[0.28em] text-primary',
            heading: 'text-2xl font-semibold uppercase tracking-[0.16em] text-on-surface sm:text-3xl',
            subheading: 'mt-3 max-w-2xl text-sm leading-loose text-on-surface-variant',
            media: 'overflow-hidden rounded-none bg-surface-container',
            grid: 'grid gap-px border border-outline-variant bg-outline-variant sm:grid-cols-2 lg:grid-cols-3',
            listBullet: 'bg-surface-container-high text-on-surface',
            footer: 'border-t border-outline-variant bg-surface-container-lowest py-10',
        };
    }

    return {
        shell: 'bg-surface text-on-surface',
        section: 'mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8',
        rule: 'border-b border-outline-variant/60',
        card: 'rounded-2xl border border-outline-variant/60 bg-surface-container-lowest p-6 shadow-md sm:p-7',
        cardSoft: 'rounded-2xl border border-outline-variant/60 bg-surface-container-low p-5',
        chip: 'inline-flex items-center gap-2 rounded-full bg-primary-container px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-on-primary-container',
        heading: 'text-3xl font-extrabold tracking-tight text-primary sm:text-4xl',
        subheading: 'mt-3 max-w-2xl text-base leading-relaxed text-on-surface-variant',
        media: 'overflow-hidden rounded-2xl bg-surface-container',
        grid: 'grid gap-6 sm:grid-cols-2 lg:grid-cols-3',
        listBullet: 'bg-primary-container text-on-primary-container',
        footer: 'border-t border-outline-variant/60 bg-surface-container-lowest py-8',
    };
});

const heroClasses = computed(() => {
    if (template.value === 'modern') {
        return 'bg-surface-container-low';
    }
    if (template.value === 'minimal') {
        return 'bg-surface-container-lowest';
    }
    return 'bg-gradient-to-b from-primary-container via-primary-deep to-primary-deep text-on-primary';
});

const messageForm = useForm({
    name: '',
    email: '',
    phone: '',
    subject: '',
    message: '',
    website: '',
});

const flashSuccess = computed(() => page.props.flash?.success ?? null);

// Section-scoped confirmation: the shared flash banner can also be set by the
// standalone contact page, so keep a local flag next to this form too.
const messageSent = ref(false);

function submitMessage() {
    messageSent.value = false;
    messageForm.post('/kontak', {
        preserveScroll: true,
        preserveState: false,
        onSuccess: () => {
            messageForm.reset('name', 'email', 'phone', 'subject', 'message', 'website');
            messageSent.value = true;
        },
    });
}

function scrollToSection(id) {
    const target = document.getElementById(id);
    if (!target) {
        return;
    }
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.history.replaceState(null, '', `#${id}`);
}

function formatDate(value) {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString('id-ID', { dateStyle: 'long' });
}

function initials(officer) {
    return String(officer.name ?? '')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word.charAt(0).toUpperCase())
        .join('');
}
</script>

<template>
    <Head :title="`${orgName} — Sistem Informasi Dana Bergulir Masyarakat`">
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <meta head-key="description" name="description" :content="heroDescription" />
        <meta head-key="og:title" property="og:title" :content="`${orgName} — Situs Resmi`" />
        <meta head-key="og:description" property="og:description" :content="heroDescription" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta v-if="organization.logo_url" head-key="og:image" property="og:image" :content="organization.logo_url" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="`${orgName} — Situs Resmi`" />
        <meta head-key="twitter:description" name="twitter:description" :content="heroDescription" />
    </Head>

    <div class="flex min-h-screen flex-col font-sans antialiased" :class="styles.shell">
        <!-- Preview banner (builder live-preview only) -->
        <div v-if="is_preview" class="border-b border-outline-variant bg-tertiary-fixed text-on-surface">
            <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-3 sm:flex-row sm:px-6 lg:px-8">
                <p class="flex items-center gap-2 text-sm font-semibold">
                    <AppIcon name="visibility" class="text-xl" />
                    Mode Preview — Ini adalah tampilan draf situs resmi Anda
                </p>
                <Link href="/website/settings">
                    <AppButton variant="secondary" size="compact" type="button" icon="edit_note">
                        Kembali ke Editor
                    </AppButton>
                </Link>
            </div>
        </div>

        <!-- Header / one-page navbar -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-container shadow-md">
                        <img
                            v-if="organization.logo_url"
                            :src="organization.logo_url"
                            :alt="`Logo ${orgName}`"
                            class="size-full object-contain"
                        >
                        <span v-else class="text-lg font-extrabold text-on-primary-container">{{ initials({ name: orgName }) }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ orgName }}</p>
                        <p v-if="orgLocation" class="truncate text-xs text-on-surface-variant">{{ orgLocation }}</p>
                    </div>
                </div>

                <nav class="order-3 flex w-full flex-wrap items-center gap-1.5 sm:order-2 sm:w-auto" aria-label="Navigasi halaman">
                    <AppButton
                        v-for="item in navItems"
                        :key="item.id"
                        variant="ghost"
                        size="compact"
                        type="button"
                        :icon="item.icon"
                        @click="scrollToSection(item.id)"
                    >
                        {{ item.label }}
                    </AppButton>
                    <Link href="/login" class="order-2 sm:order-3">
                        <AppButton size="compact" type="button" icon="login">Masuk Sistem</AppButton>
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <!-- 1 — Hero -->
            <section v-if="hero.enabled" id="hero" class="scroll-mt-28" :class="[heroClasses, styles.rule]">
                <!-- classic: centered formal gradient hero -->
                <div v-if="template === 'classic'" class="relative mx-auto flex max-w-7xl flex-col items-center gap-6 px-4 py-20 text-center sm:px-6 md:py-28 lg:px-8">
                    <span class="inline-flex items-center gap-2 rounded-full bg-on-primary/10 px-4 py-1.5 text-xs font-bold uppercase tracking-widest text-on-primary-container">
                        <AppIcon name="verified" />
                        {{ heroTagline }}
                    </span>
                    <h1 class="max-w-3xl text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl md:text-5xl">
                        {{ heroTitle }}
                    </h1>
                    <p class="max-w-2xl text-base leading-relaxed text-on-primary-container sm:text-lg">
                        {{ heroDescription }}
                    </p>
                    <img
                        v-if="heroImage"
                        :src="heroImage"
                        :alt="`Tampilan ${orgName}`"
                        class="mt-2 max-h-80 w-full max-w-2xl rounded-2xl object-cover shadow-lg"
                    >
                    <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                        <Link :href="heroCtaTarget">
                            <AppButton size="large" type="button" icon="apartment">{{ heroCtaLabel }}</AppButton>
                        </Link>
                        <AppButton
                            v-if="phone"
                            variant="secondary"
                            size="large"
                            type="button"
                            icon="call"
                            @click="scrollToSection('kontak')"
                        >
                            Hubungi Kami
                        </AppButton>
                    </div>
                </div>

                <!-- modern: split bento hero -->
                <div v-else-if="template === 'modern'" class="mx-auto grid max-w-7xl items-center gap-8 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-24 lg:px-8">
                    <div>
                        <span :class="styles.chip">
                            <AppIcon name="verified" />
                            {{ heroTagline }}
                        </span>
                        <h1 class="mt-4 text-3xl font-extrabold leading-tight tracking-tight text-primary sm:text-4xl md:text-5xl">
                            {{ heroTitle }}
                        </h1>
                        <p class="mt-4 max-w-xl text-base leading-relaxed text-on-surface-variant">{{ heroDescription }}</p>
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <Link :href="heroCtaTarget">
                                <AppButton size="large" icon="apartment">{{ heroCtaLabel }}</AppButton>
                            </Link>
                            <AppButton v-if="contact.enabled" size="large" variant="secondary" icon="call" @click="scrollToSection('kontak')">
                                Hubungi Kami
                            </AppButton>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div :class="[styles.card, 'sm:col-span-2']">
                            <img
                                v-if="heroImage"
                                :src="heroImage"
                                :alt="`Tampilan ${orgName}`"
                                class="max-h-64 w-full rounded-2xl object-cover"
                            >
                            <div v-else class="grid h-48 place-items-center rounded-2xl bg-primary-container text-on-primary-container">
                                <AppIcon name="image" class="text-4xl" />
                            </div>
                            <p class="mt-4 text-sm font-semibold text-primary">{{ orgLegalName }}</p>
                            <p v-if="orgLocation" class="text-xs text-on-surface-variant">{{ orgLocation }}</p>
                        </div>
                        <div v-if="latestPosts.length" :class="styles.cardSoft">
                            <p class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Kabar Terbaru</p>
                            <p class="mt-2 line-clamp-2 text-sm font-semibold text-on-surface">{{ latestPosts[0].title }}</p>
                        </div>
                        <div v-if="officerList.length" :class="styles.cardSoft">
                            <p class="text-xs font-bold uppercase tracking-widest text-on-surface-variant">Pengurus</p>
                            <p class="mt-2 text-sm font-semibold text-on-surface">{{ officerList.length }} orang aktif</p>
                        </div>
                    </div>
                </div>

                <!-- minimal: editorial hero -->
                <div v-else class="mx-auto max-w-5xl px-4 py-20 sm:px-6 lg:px-8">
                    <p class="text-[11px] font-bold uppercase tracking-[0.28em] text-primary">{{ heroTagline }}</p>
                    <div class="mt-4 border-t border-outline-variant pt-6">
                        <h1 class="text-3xl font-semibold leading-tight tracking-tight text-on-surface sm:text-4xl md:text-5xl">
                            {{ heroTitle }}
                        </h1>
                    </div>
                    <p class="mt-5 max-w-2xl text-sm leading-loose text-on-surface-variant">{{ heroDescription }}</p>
                    <img
                        v-if="heroImage"
                        :src="heroImage"
                        :alt="`Tampilan ${orgName}`"
                        class="mt-8 aspect-video w-full bg-surface-container object-cover"
                    >
                    <div class="mt-8 flex flex-wrap items-center gap-3 border-t border-outline-variant pt-6">
                        <Link :href="heroCtaTarget">
                            <AppButton variant="secondary" size="default" icon="apartment">{{ heroCtaLabel }}</AppButton>
                        </Link>
                        <AppButton v-if="phone" variant="ghost" size="default" icon="call" @click="scrollToSection('kontak')">
                            Hubungi Kami
                        </AppButton>
                    </div>
                </div>
            </section>

            <!-- 2 — About -->
            <section v-if="about.enabled" id="about" class="scroll-mt-28" :class="[styles.section, styles.rule]">
                <span :class="styles.chip">
                    <AppIcon name="info" />
                    Profil Lembaga
                </span>
                <h2 class="mt-4" :class="styles.heading">{{ about.title }}</h2>
                <p v-if="aboutSummary" :class="styles.subheading">{{ aboutSummary }}</p>

                <div class="mt-8 grid gap-5 lg:grid-cols-3">
                    <div v-if="about.vision" :class="styles.card">
                        <div class="flex items-center gap-3">
                            <AppIcon name="visibility" tone="primary" :container-size="9" />
                            <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Visi</h3>
                        </div>
                        <p class="mt-3 text-sm leading-relaxed text-on-surface">{{ about.vision }}</p>
                    </div>

                    <div v-if="missionItems.length" :class="styles.card">
                        <div class="flex items-center gap-3">
                            <AppIcon name="flag" tone="primary" :container-size="9" />
                            <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Misi</h3>
                        </div>
                        <ol class="mt-3 space-y-2.5">
                            <li v-for="(item, index) in missionItems" :key="`mission-${index}`" class="flex gap-3 text-sm leading-relaxed text-on-surface">
                                <span class="grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold" :class="styles.listBullet">
                                    {{ index + 1 }}
                                </span>
                                {{ item }}
                            </li>
                        </ol>
                    </div>

                    <div v-if="valueItems.length || foundedYear" :class="styles.card">
                        <div class="flex items-center gap-3">
                            <AppIcon name="favorite" tone="primary" :container-size="9" />
                            <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">
                                {{ valueItems.length ? 'Nilai Utama' : 'Tahun Berdiri' }}
                            </h3>
                        </div>
                        <ul v-if="valueItems.length" class="mt-3 flex flex-wrap gap-2">
                            <li v-for="value in valueItems" :key="value">
                                <AppBadge tone="info-soft">{{ value }}</AppBadge>
                            </li>
                        </ul>
                        <p v-if="foundedYear" class="mt-3 text-sm text-on-surface">
                            Melayani masyarakat sejak {{ foundedYear }}.
                        </p>
                    </div>
                </div>
            </section>

            <!-- 3 — Posts -->
            <section v-if="posts.enabled" id="berita" class="scroll-mt-28" :class="[styles.section, styles.rule]">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <span :class="styles.chip">
                            <AppIcon name="newspaper" />
                            Kabar &amp; Pengumuman
                        </span>
                        <h2 class="mt-4" :class="styles.heading">{{ posts.title }}</h2>
                        <p v-if="posts.subtitle" :class="styles.subheading">{{ posts.subtitle }}</p>
                    </div>
                    <Link href="/berita">
                        <AppButton variant="secondary" type="button" icon="article">Lihat Semua Berita</AppButton>
                    </Link>
                </div>

                <div v-if="latestPosts.length" class="mt-8" :class="styles.grid">
                    <article
                        v-for="post in latestPosts"
                        :key="post.slug || post.id"
                        class="group flex flex-col"
                        :class="template === 'minimal' ? 'bg-surface-container-lowest p-6' : styles.card"
                    >
                        <Link :href="`/berita/${post.slug}`" :class="[styles.media, 'block']">
                            <img
                                v-if="post.cover_image_url"
                                :src="post.cover_image_url"
                                :alt="post.title"
                                class="aspect-video w-full object-cover transition duration-300 group-hover:scale-105"
                            >
                            <div v-else class="grid aspect-video w-full place-items-center text-on-surface-variant/50">
                                <AppIcon name="image" class="text-3xl" />
                            </div>
                        </Link>
                        <p v-if="post.published_at" class="mt-4 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                            <AppIcon name="schedule" class="text-base" />
                            {{ formatDate(post.published_at) }}
                        </p>
                        <h3 class="mt-2 text-base font-bold leading-snug text-primary">
                            <Link :href="`/berita/${post.slug}`">{{ post.title }}</Link>
                        </h3>
                        <p v-if="post.excerpt" class="mt-2 line-clamp-3 text-sm leading-relaxed text-on-surface-variant">
                            {{ post.excerpt }}
                        </p>
                    </article>
                </div>

                <p v-else class="mt-8 text-sm italic text-on-surface-variant">
                    Belum ada kabar atau pengumuman yang dipublikasikan.
                </p>
            </section>

            <!-- 4 — Officers -->
            <section
                v-if="officers.enabled && officerList.length"
                id="pengurus"
                class="scroll-mt-28"
                :class="[styles.section, styles.rule]"
            >
                <span :class="styles.chip">
                    <AppIcon name="groups" />
                    Struktur Organisasi
                </span>
                <h2 class="mt-4" :class="styles.heading">{{ officers.title }}</h2>
                <p v-if="officers.subtitle" :class="styles.subheading">{{ officers.subtitle }}</p>

                <div class="mt-8" :class="styles.grid">
                    <div
                        v-for="(officer, index) in officerList"
                        :key="`officer-${index}`"
                        class="flex flex-col items-center text-center"
                        :class="styles.card"
                    >
                        <div class="grid size-20 shrink-0 place-items-center overflow-hidden rounded-full bg-primary-container text-lg font-extrabold text-on-primary-container">
                            <img
                                v-if="officer.photo_url"
                                :src="officer.photo_url"
                                :alt="`Foto ${officer.name}`"
                                class="size-full object-cover"
                            >
                            <span v-else>{{ initials(officer) }}</span>
                        </div>
                        <h3 class="mt-4 text-sm font-bold text-primary">{{ officer.name }}</h3>
                        <p v-if="officer.position" class="mt-1 text-xs font-semibold uppercase tracking-wider text-on-surface-variant">
                            {{ officer.position }}
                        </p>
                        <ul class="mt-3 space-y-1 text-xs text-on-surface-variant">
                            <li v-if="officer.phone" class="flex items-center justify-center gap-1.5">
                                <AppIcon name="call" class="text-base" />
                                {{ officer.phone }}
                            </li>
                            <li v-if="officer.email" class="flex items-center justify-center gap-1.5">
                                <AppIcon name="mail" class="text-base" />
                                {{ officer.email }}
                            </li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- 5 — Contact -->
            <section v-if="contact.enabled" id="kontak" class="scroll-mt-28" :class="[styles.section, styles.rule]">
                <span :class="styles.chip">
                    <AppIcon name="call" />
                    Hubungi Kami
                </span>
                <h2 class="mt-4" :class="styles.heading">{{ contact.title }}</h2>
                <p :class="styles.subheading">
                    Sampaikan pertanyaan, laporan, atau permohonan informasi melalui kanal resmi di bawah ini.
                </p>

                <div class="mt-8 grid gap-5 lg:grid-cols-5">
                    <div class="space-y-4 lg:col-span-2">
                        <div v-if="address" :class="styles.cardSoft">
                            <div class="flex items-center gap-3">
                                <AppIcon name="place" tone="primary" :container-size="9" />
                                <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Alamat</h3>
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-on-surface">{{ address }}</p>
                        </div>

                        <div :class="styles.cardSoft">
                            <div class="flex items-center gap-3">
                                <AppIcon name="contact_phone" tone="primary" :container-size="9" />
                                <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Kontak</h3>
                            </div>
                            <ul class="mt-3 space-y-2 text-sm">
                                <li v-if="phone">
                                    <a :href="`tel:${phone}`" class="flex items-center gap-2 font-semibold text-primary hover:underline">
                                        <AppIcon name="call" class="text-base" />
                                        {{ phone }}
                                    </a>
                                </li>
                                <li v-if="email">
                                    <a :href="`mailto:${email}`" class="flex items-center gap-2 font-semibold text-primary hover:underline">
                                        <AppIcon name="mail" class="text-base" />
                                        {{ email }}
                                    </a>
                                </li>
                                <li v-if="organization.website" class="flex items-center gap-2 text-on-surface">
                                    <AppIcon name="language" class="text-base" />
                                    {{ organization.website }}
                                </li>
                                <li v-if="!phone && !email && !organization.website" class="italic text-on-surface-variant">
                                    Kanal kontak belum dipublikasikan.
                                </li>
                            </ul>
                        </div>

                        <div v-if="contact.office_hours" :class="styles.cardSoft">
                            <div class="flex items-center gap-3">
                                <AppIcon name="schedule" tone="primary" :container-size="9" />
                                <h3 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Jam Operasional</h3>
                            </div>
                            <p class="mt-2 text-sm text-on-surface">{{ contact.office_hours }}</p>
                        </div>
                    </div>

                    <div v-if="contactFormEnabled" :class="styles.card">
                        <div v-if="flashSuccess || messageSent" class="mb-4 rounded-xl bg-secondary-container px-4 py-3 text-sm font-medium text-secondary">
                            {{ flashSuccess || 'Pesan berhasil dikirim. Terima kasih!' }}
                        </div>

                        <form class="space-y-4" @submit.prevent="submitMessage">
                            <div class="absolute -left-[9999px] h-px w-px overflow-hidden" aria-hidden="true">
                                <AppInput
                                    v-model="messageForm.website"
                                    label="Website"
                                    hide-label
                                    autocomplete="off"
                                    tabindex="-1"
                                />
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="messageForm.name"
                                    label="Nama"
                                    icon="person"
                                    :error="messageForm.errors.name"
                                />
                                <AppInput
                                    v-model="messageForm.email"
                                    label="Email"
                                    icon="mail"
                                    type="email"
                                    :error="messageForm.errors.email"
                                />
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <AppInput
                                    v-model="messageForm.phone"
                                    label="Telepon"
                                    icon="call"
                                    :error="messageForm.errors.phone"
                                />
                                <AppInput
                                    v-model="messageForm.subject"
                                    label="Subjek"
                                    icon="label"
                                    :error="messageForm.errors.subject"
                                />
                            </div>
                            <AppTextarea
                                v-model="messageForm.message"
                                label="Pesan"
                                icon="edit_note"
                                :error="messageForm.errors.message"
                            />
                            <div class="flex justify-end pt-1">
                                <AppButton type="submit" icon="send" :loading="messageForm.processing">
                                    Kirim Pesan
                                </AppButton>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer :class="styles.footer">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm font-semibold text-on-surface">
                    © {{ currentYear }} {{ orgLegalName }}
                </p>
                <p v-if="settings.footer_note" class="mt-1.5 text-xs text-on-surface-variant">{{ settings.footer_note }}</p>
                <p v-else class="mt-1.5 text-xs text-on-surface-variant">
                    Dikelola dengan
                    <Link href="/" class="font-semibold text-primary hover:underline">SIDBM Next</Link>
                    — Sistem Informasi Dana Bergulir Masyarakat
                </p>
                <p class="mt-1.5 text-xs text-on-surface-variant">
                    <Link href="/privacy" class="font-semibold text-primary hover:underline">Kebijakan Privasi</Link>
                    <span class="px-1.5 text-outline">•</span>
                    <Link href="/terms" class="font-semibold text-primary hover:underline">Syarat Layanan</Link>
                </p>
                <div v-if="hasSocial" class="mt-4 flex justify-center gap-2">
                    <Link v-if="social.facebook" :href="social.facebook" target="_blank" rel="noopener" aria-label="Facebook">
                        <AppButton variant="secondary" size="compact" type="button" icon="public">Facebook</AppButton>
                    </Link>
                    <Link v-if="social.instagram" :href="social.instagram" target="_blank" rel="noopener" aria-label="Instagram">
                        <AppButton variant="secondary" size="compact" type="button" icon="photo_camera">Instagram</AppButton>
                    </Link>
                    <Link v-if="social.youtube" :href="social.youtube" target="_blank" rel="noopener" aria-label="YouTube">
                        <AppButton variant="secondary" size="compact" type="button" icon="smart_display">YouTube</AppButton>
                    </Link>
                </div>
            </div>
        </footer>
    </div>
</template>
