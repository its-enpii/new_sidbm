<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '../Components/AppButton.vue';
import AppIcon from '../Components/AppIcon.vue';
import AppCard from '../Components/AppCard.vue';
import AppBadge from '../Components/AppBadge.vue';

defineProps({
    name: { type: String, default: 'SIDBM Next' },
    status: { type: String, default: 'ok' },
});

const mobileNavOpen = ref(false);

const features = [
    {
        icon: 'account_balance',
        title: 'Pengelolaan Dana Bergulir',
        desc: 'Manajemen permohonan pinjaman kelompok SPP & UEP, verifikasi berjenjang, jadwal angsuran amortisasi, hingga mutasi kolektibilitas per pemanfaat.',
        badge: 'Lending Engine',
    },
    {
        icon: 'bar_chart',
        title: 'Konsolidasi Keuangan Kabupaten',
        desc: 'Portal pengawasan terpadu untuk Pemerintah Kabupaten (Dinas PMD & Inspektorat). Laporan Neraca, Laba Rugi, Buku Besar, dan CALK otomatis se-wilayah.',
        badge: 'Regency Portal',
    },
    {
        icon: 'smart_toy',
        title: 'AI Assistant & Pengetahuan Regulasi',
        desc: 'Asisten cerdas terintegrasi untuk analisis data pinjaman, proyeksi keuangan bulanan, serta konsultasi regulasi dan SOP BUMDesma.',
        badge: 'AI Intelligence',
    },
    {
        icon: 'qr_code_2',
        title: 'Otomatisasi Tagihan & QRIS',
        desc: 'Integrasi sistem pembayaran tagihan langganan otomatis via QRIS dan Virtual Account Bank nasional (BCA, BRI, Mandiri, BNI, dll).',
        badge: 'Auto Billing',
    },
    {
        icon: 'chat',
        title: 'Notifikasi WhatsApp Otomatis',
        desc: 'Pengiriman slip pencairan pinjaman, struk angsuran, dan notifikasi pengingat jatuh tempo langsung ke nomor WhatsApp pengurus & pemanfaat.',
        badge: 'WA Gateway',
    },
    {
        icon: 'shield',
        title: 'Keamanan Data & Isolasi Sharding',
        desc: 'Arsitektur basis data terisolasi untuk tiap BUMDesma, menjamin kerahasiaan data, integritas saldo pembukuan, dan performa tinggi.',
        badge: 'Enterprise Security',
    },
];

const stats = [
    { label: 'Kecamatan / BUMDesma Siap Terlayani', value: '500+', icon: 'location_city' },
    { label: 'Kelompok Pemanfaat Dikelola', value: '12.500+', icon: 'groups' },
    { label: 'Otomatisasi Jurnal & Buku Besar', value: '100%', icon: 'receipt_long' },
    { label: 'Tingkat Akurasi Laporan Keuangan', value: '99,9%', icon: 'verified' },
];

const steps = [
    {
        num: '01',
        title: 'Registrasi & Alokasi Tenant',
        desc: 'Pendaftaran BUMDesma LKD untuk mendapatkan alokasi basis data terisolasi yang aman dan siap pakai.',
    },
    {
        num: '02',
        title: 'Migrasi & Saldo Awal',
        desc: 'Fasilitas Import Wizard pintar untuk memindahkan data master kelompok, anggota, dan saldo awal dari sistem sebelumnya.',
    },
    {
        num: '03',
        title: 'Operasional Harian',
        desc: 'Pencatatan pinjaman, pembayaran angsuran, kas/bank, dan jurnal akuntansi otomatis sesuai standar SAK Entitas Privat.',
    },
    {
        num: '04',
        title: 'Laporan & Pengawasan Pemda',
        desc: 'Penerbitan laporan resmi berkala untuk pertanggungjawaban musyawarah antar desa (MAD) dan monitoring dinas terkait.',
    },
];

const faqs = [
    {
        q: 'Apa itu SIDBM Next dan siapa saja yang dapat menggunakannya?',
        a: 'SIDBM Next adalah sistem informasi tata kelola keuangan terpadu yang dirancang khusus untuk BUMDesma LKD (Lembaga Keuangan Desa / Eks UPK PNPM-MPd), pengelola dana bergulir masyarakat, serta instansi pembina teknis di tingkat Kabupaten (Dinas PMD & Inspektorat).',
    },
    {
        q: 'Apakah sistem ini sesuai dengan regulasi pemerintah dan standar akuntansi terkini?',
        a: 'Ya, sistem telah diselaraskan dengan amanat PP No. 11 Tahun 2021 tentang BUMDesa, Permendesa PDTT, serta standar bagan akun (COA) akuntansi keuangan entitas mikro & privat (SAK EP/ETAP) untuk menghasilkan Neraca, Laba Rugi, Arus Kas, dan CALK yang akuntabel.',
    },
    {
        q: 'Bagaimana keamanan dan kerahasiaan data keuangan masing-masing BUMDesma?',
        a: 'Sistem menggunakan teknologi Database Sharding terisolasi, di mana setiap BUMDesma memiliki ruang data yang independen dan terenkripsi sehingga data antar-kecamatan tidak dapat saling bercampur atau diakses tanpa izin.',
    },
    {
        q: 'Apakah data lama dari format Excel atau database Access dapat dipindahkan?',
        a: 'Tersedia modul Import Wizard dan migrasi data pintar yang memudahkan pengurus memasukkan data master desa, kelompok pemanfaat, data anggota, serta riwayat saldo pinjaman lama secara cepat tanpa harus input manual satu per satu.',
    },
    {
        q: 'Bagaimana cara BUMDesma mendaftarkan unit atau berkonsultasi implementasi?',
        a: 'Pengurus BUMDesma maupun perwakilan Dinas PMD dapat menghubungi tim teknis kami melalui tombol "Konsultasi & Pendaftaran" di bawah ini untuk pendampingan registrasi, demonstrasi sistem, dan pelatihan operator.',
    },
];

const activeFaq = ref(null);
function toggleFaq(idx) {
    activeFaq.value = activeFaq.value === idx ? null : idx;
}

function smoothScrollTo(id) {
    mobileNavOpen.value = false;
    const el = document.getElementById(id);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<template>
    <Head title="SIDBM Next - Sistem Informasi Dana Bergulir Masyarakat" />

    <div class="min-h-screen bg-surface font-sans text-on-surface antialiased scroll-smooth selection:bg-primary selection:text-on-primary">
        <!-- Top Banner -->
        <div class="bg-primary px-4 py-2 text-center text-xs font-semibold tracking-wide text-on-primary">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-2">
                <span class="inline-flex items-center rounded-full bg-secondary px-2 py-0.5 text-[11px] font-bold text-on-secondary">RESMI</span>
                <span>Sistem Tata Kelola Keuangan & Dana Bergulir BUMDesma LKD Sesuai Regulasi PP No. 11/2021</span>
            </div>
        </div>

        <!-- Sticky Header / Navbar -->
        <header class="sticky top-0 z-40 border-b border-outline-variant/60 bg-surface-container-lowest/95 backdrop-blur-md transition-all">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3.5 sm:px-6 lg:px-8">
                <!-- Brand Logo -->
                <a href="#" class="flex items-center gap-3 transition hover:opacity-90">
                    <div class="grid size-10 place-items-center rounded-xl bg-primary text-on-primary shadow-sm">
                        <AppIcon name="account_balance" class="text-2xl" />
                    </div>
                    <div>
                        <span class="block text-lg font-black tracking-tight text-primary">SIDBM <span class="text-secondary">Next</span></span>
                        <span class="block text-[10px] font-bold uppercase tracking-wider text-outline">BUMDesma LKD Platform</span>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden items-center gap-8 md:flex">
                    <a href="#fitur" @click.prevent="smoothScrollTo('fitur')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Fitur Unggulan</a>
                    <a href="#solusi" @click.prevent="smoothScrollTo('solusi')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Alur Kerja</a>
                    <a href="#statistik" @click.prevent="smoothScrollTo('statistik')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Capaian</a>
                    <a href="#faq" @click.prevent="smoothScrollTo('faq')" class="text-sm font-semibold text-on-surface-variant transition hover:text-primary">Tanya Jawab</a>
                </nav>

                <!-- Header Actions -->
                <div class="flex items-center gap-3">
                    <!-- Tombol Masuk -> Menuju Halaman Login -->
                    <Link href="/login">
                        <AppButton variant="secondary" size="compact" icon="login">
                            Masuk
                        </AppButton>
                    </Link>

                    <!-- Tombol Mulai Sekarang -> Scroll ke Bagian Onboarding / Pendaftaran -->
                    <button type="button" @click="smoothScrollTo('daftar')" class="hidden sm:inline-flex">
                        <AppButton variant="success" size="compact" icon="how_to_reg">
                            Mulai Sekarang
                        </AppButton>
                    </button>

                    <!-- Mobile Menu Toggle -->
                    <button
                        type="button"
                        class="inline-flex size-10 items-center justify-center rounded-xl border border-outline-variant bg-surface-container-low text-primary md:hidden"
                        aria-label="Buka Menu"
                        @click="mobileNavOpen = !mobileNavOpen"
                    >
                        <AppIcon :name="mobileNavOpen ? 'close' : 'menu'" class="text-xl" />
                    </button>
                </div>
            </div>

            <!-- Mobile Drawer Menu -->
            <transition name="drawer">
                <div v-if="mobileNavOpen" class="border-b border-outline-variant bg-surface-container-lowest px-6 py-4 md:hidden space-y-3 shadow-lg">
                    <a href="#fitur" class="block text-sm font-semibold text-on-surface-variant hover:text-primary" @click.prevent="smoothScrollTo('fitur')">Fitur Unggulan</a>
                    <a href="#solusi" class="block text-sm font-semibold text-on-surface-variant hover:text-primary" @click.prevent="smoothScrollTo('solusi')">Alur Kerja</a>
                    <a href="#statistik" class="block text-sm font-semibold text-on-surface-variant hover:text-primary" @click.prevent="smoothScrollTo('statistik')">Capaian</a>
                    <a href="#faq" class="block text-sm font-semibold text-on-surface-variant hover:text-primary" @click.prevent="smoothScrollTo('faq')">Tanya Jawab</a>
                    <div class="pt-2 border-t border-outline-variant/40">
                        <button type="button" @click="smoothScrollTo('daftar')" class="w-full text-left font-bold text-secondary text-sm">
                            Konsultasi & Pendaftaran BUMDesma →
                        </button>
                    </div>
                </div>
            </transition>
        </header>

        <main>
            <!-- Hero Section -->
            <section class="relative overflow-hidden bg-gradient-to-b from-surface-container-low/40 via-surface to-surface py-16 lg:py-24">
                <div class="absolute -right-24 top-12 size-96 rounded-full bg-primary/5 blur-3xl pointer-events-none" />
                <div class="absolute -left-24 bottom-12 size-96 rounded-full bg-secondary/5 blur-3xl pointer-events-none" />

                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-12">
                        <!-- Left Pitch -->
                        <div class="lg:col-span-7 text-center lg:text-left space-y-6">
                            <div class="inline-flex items-center gap-2 rounded-full border border-outline-variant/80 bg-surface-container-lowest px-3.5 py-1.5 shadow-sm">
                                <AppIcon name="verified" class="text-secondary text-lg" />
                                <span class="text-xs font-bold text-primary">Standar Tata Kelola Keuangan BUMDesma LKD</span>
                            </div>

                            <h1 class="text-4xl font-black tracking-tight text-primary sm:text-5xl lg:text-6xl leading-[1.15]">
                                Akuntabilitas Keuangan Desa <br />
                                <span class="bg-gradient-to-r from-primary via-primary-container to-secondary bg-clip-text text-transparent">
                                    Transparan & Terintegrasi
                                </span>
                            </h1>

                            <p class="mx-auto lg:mx-0 max-w-2xl text-base sm:text-lg leading-relaxed text-on-surface-variant">
                                Platform enterprise untuk pengelolaan dana bergulir masyarakat, pembukuan akuntansi standar SAK Entitas Privat, dan portal pengawasan konsolidasi tingkat Kabupaten secara realtime.
                            </p>

                            <!-- CTAs -->
                            <div class="flex flex-wrap items-center justify-center lg:justify-start gap-4 pt-2">
                                <Link href="/login">
                                    <AppButton variant="primary" size="large" icon="login">
                                        Masuk ke Portal Aplikasi
                                    </AppButton>
                                </Link>

                                <button type="button" @click="smoothScrollTo('daftar')">
                                    <AppButton variant="secondary" size="large" icon="arrow_downward">
                                        Pelajari Alur Pendaftaran
                                    </AppButton>
                                </button>
                            </div>

                            <!-- Trust Badges -->
                            <div class="pt-6 border-t border-outline-variant/50 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs text-on-surface-variant font-medium">
                                <span class="flex items-center gap-1.5">
                                    <AppIcon name="shield" class="text-secondary text-base" /> Standar COA Resmi
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <AppIcon name="lock" class="text-secondary text-base" /> Data Terisolasi & Aman
                                </span>
                                <span class="flex items-center gap-1.5">
                                    <AppIcon name="bolt" class="text-secondary text-base" /> Otomatisasi Laporan
                                </span>
                            </div>
                        </div>

                        <!-- Right Graphic Showcase (Preview Mockup) -->
                        <div class="lg:col-span-5 relative">
                            <AppCard bordered class="relative z-10 shadow-2xl p-6 sm:p-8 space-y-6">
                                <!-- Card Header -->
                                <div class="flex items-center justify-between border-b border-outline-variant/60 pb-4">
                                    <div class="flex items-center gap-2">
                                        <div class="size-3 rounded-full bg-error" />
                                        <div class="size-3 rounded-full bg-tertiary-fixed-dim" />
                                        <div class="size-3 rounded-full bg-secondary" />
                                    </div>
                                    <span class="inline-flex rounded-full bg-primary-fixed px-2.5 py-0.5 text-xs font-bold text-primary">
                                        Executive Dashboard Preview
                                    </span>
                                </div>

                                <!-- Mock Stats Cards -->
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="rounded-xl bg-surface-container-low p-4 space-y-1">
                                        <span class="text-xs font-semibold text-outline">Total Portofolio Pinjaman</span>
                                        <p class="text-xl font-extrabold text-primary">Rp 1.480.500.000</p>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-secondary">
                                            <AppIcon name="trending_up" class="text-sm" /> +12.4% bln ini
                                        </span>
                                    </div>
                                    <div class="rounded-xl bg-surface-container-low p-4 space-y-1">
                                        <span class="text-xs font-semibold text-outline">Tingkat Pengembalian</span>
                                        <p class="text-xl font-extrabold text-secondary">98,6%</p>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-primary">
                                            <AppIcon name="check_circle" class="text-sm" /> Kategori Sehat
                                        </span>
                                    </div>
                                </div>

                                <!-- AI Assistant Mock -->
                                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 flex items-start gap-3 shadow-sm">
                                    <div class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary-container text-on-primary">
                                        <AppIcon name="smart_toy" class="text-lg" />
                                    </div>
                                    <div class="space-y-1 text-xs">
                                        <p class="font-bold text-primary">Asisten Cerdas SIDBM</p>
                                        <p class="text-on-surface-variant leading-relaxed">
                                            "Proyeksi arus kas periode berjalan siap. Rasio kolektibilitas lancar terpantau optimal."
                                        </p>
                                    </div>
                                </div>

                                <!-- Status bar preview -->
                                <div class="flex items-center justify-between pt-2 text-xs font-semibold text-outline">
                                    <span class="flex items-center gap-1.5"><AppIcon name="sync" class="text-sm" /> Sinkronisasi Basis Data</span>
                                    <span class="text-secondary font-bold">Status: Online</span>
                                </div>
                            </AppCard>

                            <!-- Decorative backdrop -->
                            <div class="absolute -bottom-5 -right-5 size-full rounded-2xl bg-primary/10 border border-primary/20 pointer-events-none -z-10 transform rotate-2" />
                        </div>
                    </div>
                </div>
            </section>

            <!-- Stats Bar Section -->
            <section id="statistik" class="bg-primary text-on-primary py-12 scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-2 gap-8 md:grid-cols-4">
                        <div v-for="(s, idx) in stats" :key="idx" class="text-center space-y-2">
                            <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-on-primary/10 text-on-primary">
                                <AppIcon :name="s.icon" class="text-2xl" />
                            </div>
                            <p class="text-3xl sm:text-4xl font-black tracking-tight">{{ s.value }}</p>
                            <p class="text-xs sm:text-sm font-medium text-primary-fixed-dim">{{ s.label }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Features Grid Section -->
            <section id="fitur" class="py-20 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-12">
                    <div class="text-center space-y-4 max-w-3xl mx-auto">
                        <AppBadge tone="primary">Fitur Unggulan</AppBadge>
                        <h2 class="text-3xl sm:text-4xl font-black text-primary tracking-tight">
                            Solusi Komprehensif untuk BUMDesma & Instansi Pembina
                        </h2>
                        <p class="text-on-surface-variant text-base sm:text-lg">
                            Didesain khusus untuk memenuhi tata kelola permodalan masyarakat dengan standar kepatuhan regulasi.
                        </p>
                    </div>

                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <AppCard v-for="(f, idx) in features" :key="idx" bordered class="transition-all duration-300 hover:-translate-y-1 hover:shadow-xl space-y-4">
                            <div class="flex items-center justify-between">
                                <div class="grid size-12 place-items-center rounded-xl bg-primary-container text-on-primary">
                                    <AppIcon :name="f.icon" class="text-2xl" />
                                </div>
                                <AppBadge tone="neutral">{{ f.badge }}</AppBadge>
                            </div>
                            <h3 class="text-xl font-bold text-primary">{{ f.title }}</h3>
                            <p class="text-sm text-on-surface-variant leading-relaxed">{{ f.desc }}</p>
                        </AppCard>
                    </div>
                </div>
            </section>

            <!-- How it works Section -->
            <section id="solusi" class="py-20 bg-surface-container-low/60 border-y border-outline-variant/60 scroll-mt-20">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-16">
                    <div class="text-center space-y-4 max-w-3xl mx-auto">
                        <AppBadge tone="success">Alur Kerja Sistem</AppBadge>
                        <h2 class="text-3xl sm:text-4xl font-black text-primary tracking-tight">
                            Tahapan Implementasi & Operasional
                        </h2>
                        <p class="text-on-surface-variant text-base sm:text-lg">
                            Proses transisi yang mudah dari pembukuan konvensional menuju sistem digital terpadu.
                        </p>
                    </div>

                    <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                        <div v-for="(step, idx) in steps" :key="idx" class="relative space-y-4 rounded-2xl bg-surface-container-lowest p-6 border border-outline-variant shadow-sm transition hover:shadow-md">
                            <span class="text-4xl font-black text-secondary/30">{{ step.num }}</span>
                            <h3 class="text-lg font-bold text-primary">{{ step.title }}</h3>
                            <p class="text-xs sm:text-sm text-on-surface-variant leading-relaxed">{{ step.desc }}</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FAQ Section with Animated Accordion -->
            <section id="faq" class="py-20 bg-surface scroll-mt-20">
                <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-12">
                    <div class="text-center space-y-4">
                        <AppBadge tone="primary">Tanya Jawab</AppBadge>
                        <h2 class="text-3xl sm:text-4xl font-black text-primary tracking-tight">
                            Pertanyaan Seputar Layanan & Regulasi
                        </h2>
                        <p class="text-on-surface-variant text-base">
                            Informasi penting mengenai legalitas, standar pembukuan, dan penerapan sistem.
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(f, idx) in faqs"
                            :key="idx"
                            class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden transition-all duration-200"
                            :class="activeFaq === idx ? 'shadow-md border-primary/40' : ''"
                        >
                            <button
                                type="button"
                                class="flex w-full items-center justify-between p-5 text-left font-bold text-primary hover:bg-surface-container-low transition-colors"
                                :aria-expanded="activeFaq === idx"
                                @click="toggleFaq(idx)"
                            >
                                <span class="pr-4">{{ f.q }}</span>
                                <AppIcon
                                    name="expand_more"
                                    class="text-2xl shrink-0 transition-transform duration-300 text-outline"
                                    :class="activeFaq === idx ? 'rotate-180 text-primary' : ''"
                                />
                            </button>

                            <!-- Smooth Animated Accordion Content -->
                            <div
                                class="grid transition-all duration-300 ease-in-out"
                                :class="activeFaq === idx ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'"
                            >
                                <div class="overflow-hidden">
                                    <div class="border-t border-outline-variant/40 px-5 pb-5 pt-3 text-sm leading-relaxed text-on-surface-variant">
                                        {{ f.a }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Onboarding & Consultation Section (Target of 'Mulai Sekarang') -->
            <section id="daftar" class="py-20 bg-surface-container-low/80 border-t border-outline-variant/60 scroll-mt-20">
                <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-12">
                    <div class="text-center space-y-4">
                        <AppBadge tone="success">Konsultasi & Pendaftaran Tenant</AppBadge>
                        <h2 class="text-3xl sm:text-4xl font-black text-primary tracking-tight">
                            Mulai Implementasi di BUMDesma / Kecamatan Anda
                        </h2>
                        <p class="mx-auto max-w-2xl text-on-surface-variant text-base">
                            Bagi pengurus BUMDesma LKD atau instansi pembina kabupaten yang ingin mengaktifkan layanan atau membutuhkan asistensi migrasi data, silakan hubungi tim pendamping teknis kami.
                        </p>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <!-- Card 1: Sudah Memiliki Akun -->
                        <AppCard bordered class="p-6 sm:p-8 space-y-5 flex flex-col justify-between">
                            <div class="space-y-3">
                                <div class="grid size-12 place-items-center rounded-xl bg-primary text-on-primary">
                                    <AppIcon name="verified_user" class="text-2xl" />
                                </div>
                                <h3 class="text-xl font-bold text-primary">Sudah Memiliki Akses Akun?</h3>
                                <p class="text-sm text-on-surface-variant leading-relaxed">
                                    Jika BUMDesma Anda telah terdaftar dan memiliki kredensial resmi, Anda dapat langsung masuk ke dashboard operasional.
                                </p>
                            </div>
                            <Link href="/login" class="pt-4">
                                <AppButton variant="primary" size="large" class="w-full" icon="login">
                                    Masuk ke Akun Anda
                                </AppButton>
                            </Link>
                        </AppCard>

                        <!-- Card 2: Belum Terdaftar / Ingin Konsultasi -->
                        <AppCard bordered class="p-6 sm:p-8 space-y-5 flex flex-col justify-between bg-surface-container-lowest">
                            <div class="space-y-3">
                                <div class="grid size-12 place-items-center rounded-xl bg-secondary text-on-secondary">
                                    <AppIcon name="support_agent" class="text-2xl" />
                                </div>
                                <h3 class="text-xl font-bold text-primary">Registrasi Tenant Baru</h3>
                                <p class="text-sm text-on-surface-variant leading-relaxed">
                                    Dapatkan bantuan pendampingan registrasi shard database mandiri, import data eksisting, dan pelatihan operator BUMDesma.
                                </p>
                            </div>
                            <div class="pt-4 space-y-2">
                                <a href="https://wa.me/?text=Halo%20Admin%20SIDBM,%20kami%20ingin%20berkonsultasi%20mengenai%20pendaftaran%20dan%20implementasi%20BUMDesma%20LKD." target="_blank" rel="noopener noreferrer" class="block">
                                    <AppButton variant="success" size="large" class="w-full" icon="chat">
                                        Hubungi Pendamping Teknis
                                    </AppButton>
                                </a>
                            </div>
                        </AppCard>
                    </div>
                </div>
            </section>

            <!-- Bottom CTA Banner -->
            <section class="py-16 bg-gradient-to-r from-primary-deep via-primary-container to-primary text-on-primary">
                <div class="mx-auto max-w-5xl px-4 text-center space-y-6 sm:px-6 lg:px-8">
                    <h2 class="text-3xl sm:text-4xl font-black tracking-tight">
                        Wujudkan Pengelolaan Dana Desa yang Akuntabel
                    </h2>
                    <p class="mx-auto max-w-2xl text-base sm:text-lg text-primary-fixed/80">
                        Bergabunglah bersama komunitas pengelola keuangan desa di seluruh Indonesia untuk mendorong kemandirian ekonomi masyarakat.
                    </p>
                    <div class="pt-2 flex flex-wrap justify-center gap-4">
                        <Link href="/login">
                            <AppButton variant="success" size="large" icon="login">
                                Masuk ke Portal Aplikasi
                            </AppButton>
                        </Link>
                    </div>
                </div>
            </section>
        </main>

        <!-- Clean Professional Footer (No Tech Stack Leak) -->
        <footer class="border-t border-outline-variant bg-surface-container-lowest py-12 text-sm text-on-surface-variant">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-8">
                <div class="flex flex-wrap items-center justify-between gap-6 border-b border-outline-variant/60 pb-8">
                    <div class="flex items-center gap-3">
                        <div class="grid size-10 place-items-center rounded-xl bg-primary text-on-primary">
                            <AppIcon name="account_balance" class="text-2xl" />
                        </div>
                        <div>
                            <span class="block text-lg font-black tracking-tight text-primary">SIDBM <span class="text-secondary">Next</span></span>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-outline">Platform Dana Bergulir Masyarakat</span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-6 font-semibold text-xs text-on-surface-variant">
                        <a href="#fitur" @click.prevent="smoothScrollTo('fitur')" class="hover:text-primary transition">Fitur Utama</a>
                        <a href="#solusi" @click.prevent="smoothScrollTo('solusi')" class="hover:text-primary transition">Alur Kerja</a>
                        <a href="#statistik" @click.prevent="smoothScrollTo('statistik')" class="hover:text-primary transition">Capaian</a>
                        <a href="#faq" @click.prevent="smoothScrollTo('faq')" class="hover:text-primary transition">Tanya Jawab</a>
                        <Link href="/login" class="hover:text-primary font-bold text-primary transition">Portal Masuk</Link>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-outline font-medium">
                    <p>© 2026 BUMDesma LKD. Seluruh Hak Cipta Dilindungi.</p>
                    <p class="flex items-center gap-2">
                        <span>Standar Kepatuhan PP No. 11/2021</span> • <span>SAK Entitas Privat</span>
                    </p>
                </div>
            </div>
        </footer>
    </div>
</template>

<style scoped>
.drawer-enter-active,
.drawer-leave-active {
    transition: opacity 200ms ease, transform 200ms ease;
}
.drawer-enter-from,
.drawer-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
