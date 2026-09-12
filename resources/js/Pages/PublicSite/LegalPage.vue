<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '@/Components/AppBadge.vue';
import AppButton from '@/Components/AppButton.vue';
import AppCard from '@/Components/AppCard.vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    document: { type: Object, required: true },
    legalDocuments: { type: Array, default: () => [] },
    organization: { type: Object, default: null },
    tenant: { type: Object, default: null },
    settings: { type: Object, default: null },
});

const page = usePage();

const brandName = computed(() => props.organization?.name ?? page.props.appName ?? 'SIDBM Next');
const brandSubtitle = computed(() => {
    const locality = [props.organization?.district_name, props.organization?.regency_name].filter(Boolean).join(', ');

    return locality || 'BUMDesma & LKD Platform';
});
const logoUrl = computed(() => props.organization?.logo_url ?? null);
const canonicalUrl = computed(() => `${page.props.meta.canonical_base}${props.document.path}`);
const description = computed(() => props.document.description);
const pageTitle = computed(() => `${props.document.title} — ${brandName.value}`);
const isTenantHost = computed(() => props.organization !== null);

/** Table of contents entries, numbered the same way as the section cards. */
const tocItems = computed(() => props.document.sections.map((section, index) => ({
    id: section.id,
    title: section.title,
    icon: section.icon,
    number: index + 1,
})));

/** Cross links between the two legal documents. */
const sibling = computed(() => props.legalDocuments.find((item) => item.type !== props.document.type) ?? null);

function scrollToSection(id) {
    const target = document.getElementById(`sec-${id}`);
    if (!target) {
        return;
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    window.history.replaceState(null, '', `#${id}`);
}
</script>

<template>
    <Head :title="pageTitle">
        <link head-key="canonical" rel="canonical" :href="canonicalUrl" />
        <meta head-key="description" name="description" :content="description" />
        <meta head-key="og:title" property="og:title" :content="pageTitle" />
        <meta head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta head-key="og:url" property="og:url" :content="canonicalUrl" />
        <meta head-key="og:locale" property="og:locale" content="id_ID" />
        <meta v-if="logoUrl" head-key="og:image" property="og:image" :content="logoUrl" />
        <meta head-key="twitter:card" name="twitter:card" :content="logoUrl ? 'summary_large_image' : 'summary'" />
        <meta head-key="twitter:title" name="twitter:title" :content="pageTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="description" />
    </Head>

    <div class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <!-- Top bar: tenant identity when on a tenant domain, platform identity otherwise -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
                <Link href="/" class="flex min-w-0 items-center gap-3">
                    <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-container shadow-md">
                        <img v-if="logoUrl" :src="logoUrl" :alt="`Logo ${brandName}`" class="size-full object-contain">
                        <AppIcon v-else name="account_balance" class="text-2xl text-on-primary-container" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ brandName }}</p>
                        <p class="truncate text-xs text-on-surface-variant">{{ brandSubtitle }}</p>
                    </div>
                </Link>

                <nav class="flex items-center gap-2">
                    <Link
                        v-if="isTenantHost"
                        href="/berita"
                        class="hidden min-h-10 items-center gap-2 rounded-full bg-primary-container px-4 text-sm font-semibold text-on-primary-container sm:inline-flex"
                    >
                        <AppIcon name="article" />
                        Berita
                    </Link>
                    <Link
                        v-if="isTenantHost"
                        href="/kontak"
                        class="hidden min-h-10 items-center gap-2 rounded-full bg-primary-container px-4 text-sm font-semibold text-on-primary-container sm:inline-flex"
                    >
                        <AppIcon name="mail" />
                        Kontak
                    </Link>
                    <Link href="/login" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-on-primary shadow-md transition hover:bg-primary-deep">
                        <AppIcon name="login" />
                        <span class="hidden sm:inline">Masuk Sistem</span>
                        <span class="sm:hidden">Masuk</span>
                    </Link>
                </nav>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <!-- Breadcrumb + back to home -->
                <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-1.5 text-sm text-on-surface-variant">
                    <Link href="/" class="inline-flex items-center gap-1 font-semibold text-primary hover:underline">
                        <AppIcon name="home" class="text-lg" />
                        Beranda
                    </Link>
                    <AppIcon name="chevron_right" class="text-lg text-outline" />
                    <span class="font-semibold text-on-surface">{{ document.title }}</span>
                </nav>

                <div class="mt-6 flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-2xl">
                        <AppBadge tone="primary-soft">{{ document.document_kind }}</AppBadge>
                        <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight text-primary sm:text-4xl">
                            {{ document.heading }}
                        </h1>
                        <p class="mt-3 text-sm leading-relaxed text-on-surface-variant sm:text-base">
                            {{ document.lead }}
                        </p>
                    </div>
                    <Link href="/">
                        <AppButton variant="secondary" icon="arrow_back">Kembali ke Beranda</AppButton>
                    </Link>
                </div>

                <!-- Cross navigation between the two legal documents -->
                <nav aria-label="Dokumen hukum" class="mt-8 flex flex-wrap gap-2 border-b border-outline-variant/60 pb-4">
                    <Link
                        v-for="item in legalDocuments"
                        :key="item.type"
                        :href="item.path"
                        class="inline-flex min-h-10 items-center gap-2 rounded-full px-4 text-sm font-semibold transition"
                        :class="item.type === document.type
                            ? 'bg-primary text-on-primary shadow-sm'
                            : 'bg-primary-container text-on-primary-container hover:bg-primary/20'"
                        :aria-current="item.type === document.type ? 'page' : undefined"
                    >
                        <AppIcon :name="item.icon" class="text-lg" />
                        {{ item.title }}
                    </Link>
                </nav>

                <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,18rem)_minmax(0,1fr)] lg:items-start">
                    <!-- Sticky side rail: status + table of contents -->
                    <aside class="space-y-4 lg:sticky lg:top-24">
                        <AppCard :padded="false">
                            <div class="space-y-3 p-5">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-bold uppercase tracking-wider text-outline">Status Dokumen</span>
                                    <AppBadge tone="success-soft">Berlaku</AppBadge>
                                </div>
                                <dl class="space-y-2 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-on-surface-variant">Terakhir diperbarui</dt>
                                        <dd class="text-right font-semibold text-on-surface">{{ document.last_updated_label }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-on-surface-variant">Penerbit</dt>
                                        <dd class="text-right font-semibold text-on-surface">{{ document.publisher }}</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-on-surface-variant">Bahasa</dt>
                                        <dd class="text-right font-semibold text-on-surface">Bahasa Indonesia</dd>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <dt class="text-on-surface-variant">{{ document.article_label }}</dt>
                                        <dd class="text-right font-semibold text-on-surface">{{ document.sections.length }} bagian</dd>
                                    </div>
                                </dl>
                            </div>
                        </AppCard>

                        <AppCard :padded="false">
                            <div class="border-b border-outline-variant/60 px-5 py-4">
                                <p class="text-xs font-bold uppercase tracking-wider text-outline">Daftar Isi</p>
                            </div>
                            <ol class="max-h-[26rem] overflow-y-auto p-2">
                                <li v-for="item in tocItems" :key="item.id">
                                    <button
                                        type="button"
                                        class="flex w-full items-start gap-2.5 rounded-lg px-3 py-2 text-left text-sm text-on-surface-variant transition hover:bg-surface-container-low hover:text-primary"
                                        @click="scrollToSection(item.id)"
                                    >
                                        <span class="mt-0.5 w-5 shrink-0 text-xs font-bold text-outline tabular-nums">{{ item.number }}.</span>
                                        <span class="leading-snug">{{ item.title }}</span>
                                    </button>
                                </li>
                            </ol>
                        </AppCard>

                        <p v-if="sibling" class="px-1 text-xs leading-relaxed text-on-surface-variant">
                            Ketentuan ini dibaca bersama
                            <Link :href="sibling.path" class="font-semibold text-primary hover:underline">{{ sibling.title }}</Link>
                            yang merupakan bagian tidak terpisahkan darinya.
                        </p>
                    </aside>

                    <!-- Numbered legal sections -->
                    <div class="space-y-6">
                        <AppCard
                            v-for="item in tocItems"
                            :id="`sec-${item.id}`"
                            :key="item.id"
                            bordered
                            :padded="false"
                            class="scroll-mt-24"
                        >
                            <div class="border-b border-outline-variant/60 px-5 py-4 sm:px-6">
                                <div class="flex items-start gap-4">
                                    <AppIcon
                                        :name="item.icon"
                                        tone="primary"
                                        container-size="10"
                                        container-shape="rounded"
                                    />
                                    <div class="min-w-0">
                                        <p class="text-xs font-bold uppercase tracking-wider text-outline">
                                            {{ document.article_label }} {{ item.number }}
                                        </p>
                                        <h2 class="mt-0.5 text-lg font-bold leading-snug text-on-surface sm:text-xl">
                                            {{ item.title }}
                                        </h2>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-5 px-5 py-6 sm:px-6">
                                <template
                                    v-for="(block, blockIndex) in document.sections[item.number - 1].blocks"
                                    :key="`${item.id}-${blockIndex}`"
                                >
                                    <!-- Legal copy comes from LegalDocumentService: static server-side text, never user input. -->
                                    <!-- eslint-disable-next-line vue/no-v-html -->
                                    <p v-if="block.type === 'paragraph'" class="text-sm leading-relaxed text-on-surface-variant sm:text-[0.95rem]" v-html="block.text" />
                                    <h3
                                        v-else-if="block.type === 'subheading'"
                                        class="text-sm font-bold uppercase tracking-wide text-primary"
                                    >
                                        {{ block.text }}
                                    </h3>
                                    <ul v-else-if="block.type === 'list'" class="space-y-2.5">
                                        <li
                                            v-for="(entry, entryIndex) in block.items"
                                            :key="`${item.id}-${blockIndex}-${entryIndex}`"
                                            class="flex items-start gap-3 text-sm leading-relaxed text-on-surface-variant sm:text-[0.95rem]"
                                        >
                                            <AppIcon name="check_small" class="mt-0.5 shrink-0 text-lg text-primary" />
                                            <!-- eslint-disable-next-line vue/no-v-html -->
                                            <span v-html="entry" />
                                        </li>
                                    </ul>
                                    <div
                                        v-else-if="block.type === 'contact'"
                                        class="rounded-xl bg-surface-container-low p-4 sm:p-5"
                                    >
                                        <p class="text-sm font-bold text-on-surface">{{ block.contact.name }}</p>
                                        <dl class="mt-3 space-y-2 text-sm text-on-surface-variant">
                                            <div v-if="block.contact.email" class="flex items-start gap-2.5">
                                                <dt class="sr-only">Email</dt>
                                                <AppIcon name="mail" class="mt-0.5 shrink-0 text-lg text-primary" />
                                                <dd>
                                                    <a :href="`mailto:${block.contact.email}`" class="font-semibold text-primary hover:underline">
                                                        {{ block.contact.email }}
                                                    </a>
                                                </dd>
                                            </div>
                                            <div v-if="block.contact.phone" class="flex items-start gap-2.5">
                                                <dt class="sr-only">Telepon</dt>
                                                <AppIcon name="phone" class="mt-0.5 shrink-0 text-lg text-primary" />
                                                <dd>
                                                    <a :href="`tel:${block.contact.phone}`" class="font-semibold text-primary hover:underline">
                                                        {{ block.contact.phone }}
                                                    </a>
                                                </dd>
                                            </div>
                                            <div v-if="block.contact.address" class="flex items-start gap-2.5">
                                                <dt class="sr-only">Alamat</dt>
                                                <AppIcon name="location_on" class="mt-0.5 shrink-0 text-lg text-primary" />
                                                <dd class="leading-relaxed">{{ block.contact.address }}</dd>
                                            </div>
                                            <div v-if="!block.contact.email && !block.contact.phone && !block.contact.address" class="flex items-start gap-2.5">
                                                <AppIcon name="mail" class="mt-0.5 shrink-0 text-lg text-primary" />
                                                <dd>Hubungi administrator Entitas Anda untuk permintaan dukungan maupun pengaduan.</dd>
                                            </div>
                                        </dl>
                                    </div>
                                </template>
                            </div>
                        </AppCard>

                        <!-- Closing notice -->
                        <AppCard class="bg-primary-container/30">
                            <div class="flex items-start gap-4">
                                <AppIcon name="info" tone="info" container-size="10" />
                                <div class="min-w-0 space-y-2">
                                    <h2 class="text-base font-bold text-on-surface">Ketentuan Ini Bersifat Lengkap</h2>
                                    <p class="text-sm leading-relaxed text-on-surface-variant">
                                        Dokumen ini merupakan perjanjian antara Pengguna, Entitas, dan pengelola Platform.
                                        Apabila ada ketentuan di dalamnya yang dinyatakan tidak sah oleh otoritas yang
                                        berwenang, ketentuan lain tetap berlaku sepenuhnya. Versi terbaru selalu tersedia
                                        pada halaman ini dengan tanggal pemberlakuannya.
                                    </p>
                                    <div class="flex flex-wrap gap-3 pt-1">
                                        <Link v-if="sibling" :href="sibling.path">
                                            <AppButton variant="outline" size="compact" :icon="sibling.icon">
                                                Baca {{ sibling.title }}
                                            </AppButton>
                                        </Link>
                                        <Link v-if="isTenantHost" href="/kontak">
                                            <AppButton variant="ghost" size="compact" icon="support_agent">Hubungi Kami</AppButton>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </AppCard>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-8">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm font-semibold text-on-surface">
                    © {{ new Date().getFullYear() }} {{ organization?.legal_name ?? brandName }}
                </p>
                <p class="mt-1.5 text-xs text-on-surface-variant">
                    <Link href="/privacy" class="font-semibold text-primary hover:underline">Kebijakan Privasi</Link>
                    <span class="px-1.5 text-outline">&bull;</span>
                    <Link href="/terms" class="font-semibold text-primary hover:underline">Syarat Layanan</Link>
                </p>
                <p class="mt-1.5 text-xs text-on-surface-variant">
                    Dikelola dengan
                    <a href="/" class="font-semibold text-primary hover:underline">SIDBM Next</a>
                    — Sistem Informasi Dana Bergulir Masyarakat
                </p>
            </div>
        </footer>
    </div>
</template>
