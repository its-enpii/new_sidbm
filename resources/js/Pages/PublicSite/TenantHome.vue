<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    organization: { type: Object, required: true },
    tenant: { type: Object, required: true },
});
</script>

<template>
    <Head :title="`${organization.name} — Sistem Informasi Dana Bergulir Masyarakat`" />

    <div class="flex min-h-screen flex-col bg-surface font-sans text-on-surface antialiased">
        <!-- Top bar: organization identity -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <!-- Logo: tenant's own, fallback to monogram -->
                    <div class="grid size-11 shrink-0 place-items-center overflow-hidden rounded-xl bg-primary-container shadow-md">
                        <img
                            v-if="organization.logo_url"
                            :src="organization.logo_url"
                            :alt="`Logo ${organization.name}`"
                            class="size-full object-contain"
                        >
                        <span v-else class="text-lg font-extrabold text-on-primary-container">{{ organization.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-base font-bold leading-tight text-on-surface">{{ organization.name }}</p>
                        <p v-if="organization.regency_name || organization.district_name" class="truncate text-xs text-on-surface-variant">
                            {{ [organization.district_name, organization.regency_name].filter(Boolean).join(', ') }}
                        </p>
                    </div>
                </div>

                <Link href="/login" class="inline-flex min-h-10 items-center gap-2 rounded-full bg-primary px-5 text-sm font-semibold text-on-primary shadow-md transition hover:bg-primary-deep">
                    <AppIcon name="login" />
                    Masuk Sistem
                </Link>
            </div>
        </header>

        <!-- Hero -->
        <main class="flex-1">
            <section class="relative overflow-hidden bg-gradient-to-b from-primary-container via-primary-deep to-primary-deep text-on-primary">
                <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-on-primary-container/10 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-32 -left-16 size-80 rounded-full bg-secondary-container/10 blur-3xl" />

                <div class="relative mx-auto flex max-w-7xl flex-col items-center gap-6 px-4 py-20 text-center sm:px-6 md:py-28 lg:px-8">
                    <span class="inline-flex items-center gap-2 rounded-full bg-on-primary/10 px-4 py-1.5 text-xs font-bold tracking-widest uppercase text-on-primary-container">
                        <AppIcon name="verified" />
                        Situs Resmi
                    </span>

                    <h1 class="max-w-3xl text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl md:text-5xl">
                        {{ organization.legal_name }}
                    </h1>

                    <p class="max-w-2xl text-base leading-relaxed text-on-primary-container sm:text-lg">
                        Portal informasi resmi {{ organization.name }} — pengelolaan dana bergulir masyarakat
                        yang transparan, akuntabel, dan berorientasi pada kesejahteraan warga.
                    </p>

                    <div class="mt-2 flex flex-wrap items-center justify-center gap-3">
                        <Link href="/login" class="inline-flex min-h-12 items-center gap-2 rounded-full bg-on-primary px-7 text-sm font-bold text-primary-deep shadow-lg transition hover:bg-primary-fixed">
                            <AppIcon name="apartment" />
                            Portal Pengelolaan Keuangan
                        </Link>
                        <a
                            v-if="organization.phone"
                            :href="`tel:${organization.phone}`"
                            class="inline-flex min-h-12 items-center gap-2 rounded-full border border-outline-variant/40 px-7 text-sm font-semibold text-on-primary transition hover:bg-on-primary/10"
                        >
                            <AppIcon name="call" />
                            Hubungi Kami
                        </a>
                    </div>
                </div>
            </section>

            <!-- Contact / information cards -->
            <section class="mx-auto -mt-10 max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="place" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Alamat</h2>
                        </div>
                        <p v-if="organization.address" class="mt-3 text-sm leading-relaxed text-on-surface">{{ organization.address }}</p>
                        <p v-else class="mt-3 text-sm italic text-on-surface-variant">Alamat belum dipublikasikan.</p>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="call" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Kontak</h2>
                        </div>
                        <ul class="mt-3 space-y-2 text-sm">
                            <li v-if="organization.phone" class="flex items-center gap-2 text-on-surface">
                                <AppIcon name="call" />
                                {{ organization.phone }}
                            </li>
                            <li v-if="organization.email" class="flex items-center gap-2 text-on-surface">
                                <AppIcon name="mail" />
                                {{ organization.email }}
                            </li>
                            <li v-if="organization.website" class="flex items-center gap-2 text-on-surface">
                                <AppIcon name="language" />
                                {{ organization.website }}
                            </li>
                            <li v-if="!organization.phone && !organization.email && !organization.website" class="text-sm italic text-on-surface-variant">
                                Kanal kontak belum dipublikasikan.
                            </li>
                        </ul>
                    </div>

                    <div class="rounded-2xl border border-outline-variant/40 bg-surface-container-lowest p-6 shadow-md">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary-container text-on-primary-container">
                                <AppIcon name="history" />
                            </span>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-on-surface-variant">Berdiri</h2>
                        </div>
                        <p v-if="organization.operational_start_year" class="mt-3 text-sm text-on-surface">
                            Sejak {{ organization.operational_start_year }} melayani pengelolaan dana bergulir masyarakat.
                        </p>
                        <p v-else class="mt-3 text-sm italic text-on-surface-variant">Informasi tahun berdiri belum tersedia.</p>
                    </div>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="border-t border-outline-variant/60 bg-surface-container-lowest py-8">
            <div class="mx-auto max-w-7xl px-4 text-center sm:px-6 lg:px-8">
                <p class="text-sm font-semibold text-on-surface">
                    © {{ new Date().getFullYear() }} {{ organization.legal_name }}
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
