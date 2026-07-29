<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppToast from '../Components/AppToast.vue';
import AssistantWidget from '../Components/AssistantWidget.vue';

const props = defineProps({ unitName: { type: String, default: null } });

const page = usePage();
const logoPath = computed(() => page.props.logoPath ?? null);
const user = computed(() => page.props.auth?.user);
const assistantEnabled = computed(() => Boolean(page.props.assistant?.enabled));
const currentPath = computed(() => page.url.split('?')[0]);
const mobileMenuOpen = ref(false);
const expanded = ref({});
const logoutForm = useForm({});
const sections = [
    {
        label: 'Dashboard',
        items: [{ label: 'Dashboard', icon: 'dashboard', href: '/dashboard', exact: true }],
    },
    {
        label: 'SIDBM',
        items: [
            {
                key: 'master-data',
                label: 'Master Data',
                icon: 'database',
                children: [
                    { label: 'Data Desa', icon: 'location_city', href: '/master-data/villages' },
                    {
                        key: 'members',
                        label: 'Anggota',
                        icon: 'person',
                        children: [
                            { label: 'Tambah Anggota', href: '/master-data/members/create', exact: true },
                            { label: 'Daftar Anggota', href: '/master-data/members', exclude: '/master-data/members/create' },
                        ],
                    },
                    {
                        key: 'groups',
                        label: 'Kelompok',
                        icon: 'groups',
                        children: [
                            { label: 'Tambah Kelompok', href: '/master-data/groups/create', exact: true },
                            { label: 'Daftar Kelompok', href: '/master-data/groups', exclude: '/master-data/groups/create' },
                        ],
                    },
                    {
                        key: 'institutions',
                        label: 'Lembaga Lain',
                        icon: 'business',
                        children: [
                            { label: 'Tambah Lembaga', href: '/master-data/institutions/create', exact: true },
                            { label: 'Daftar Lembaga', href: '/master-data/institutions', exclude: '/master-data/institutions/create' },
                        ],
                    },
                ],
            },
            { label: 'Register Proposal', icon: 'assignment_add', href: '/lending/loans/create', exact: true },
            { label: 'Tahapan Perguliran', icon: 'sync_alt', href: '/lending/loans', exclude: '/lending/loans/create' },
        ],
    },
    {
        label: 'Keuangan',
        items: [
            { label: 'E-Budgeting', icon: 'account_balance_wallet', href: '/budgeting' },
            {
                key: 'transactions',
                label: 'Transaksi',
                icon: 'receipt_long',
                children: [
                    { label: 'Daftar Jurnal', href: '/accounting/journals' },
                    { label: 'Jurnal Umum', href: '/accounting/journal-entries/create' },
                    { label: 'Jurnal Angsuran', href: '/accounting/journal-entries/installment' },
                ],
            },
            { label: 'Taksiran Pajak', icon: 'request_quote', href: '/accounting/tax-estimate' },
            { label: 'Notifikasi Tagihan', icon: 'sms', href: '/notifications/billing' },
            { label: 'Tutup Buku', icon: 'menu_book', href: '/accounting/period-close' },
            {
                key: 'reports',
                label: 'Pelaporan',
                icon: 'assessment',
                children: [
                    { label: 'Ringkasan Laporan', href: '/accounting/reports', exact: true },
                    { label: 'Portofolio Pinjaman', href: '/lending/reports/portfolio' },
                    { label: 'Rencana vs Realisasi', href: '/lending/reports/schedule-vs-actual' },
                    { label: 'Jurnal Transaksi', href: '/accounting/reports/journals' },
                    { label: 'Neraca Saldo', href: '/accounting/reports/trial-balance' },
                    { label: 'Neraca', href: '/accounting/reports/balance-sheet' },
                    { label: 'Laba Rugi', href: '/accounting/reports/income-statement' },
                    { label: 'Arus Kas', href: '/accounting/reports/cash-flow' },
                    { label: 'Perubahan Ekuitas', href: '/accounting/reports/equity-change' },
                    { label: 'CALK', href: '/accounting/reports/calk' },
                    { label: 'Buku Besar', href: '/accounting/reports/general-ledger' },
                ],
            },
        ],
    },
    {
        label: 'Langganan',
        items: [
            { label: 'Tagihan Langganan', icon: 'receipt_long', href: '/billing/invoices' },
        ],
    },
    {
        label: 'Pengaturan',
        items: [
            { label: 'Pengaturan', icon: 'settings', href: '/settings' },
        ],
    },
];
const platformNavigation = [{ label: 'Panel Admin', icon: 'admin_panel_settings', href: '/admin' }];

function isActive(item) {
    if (item.children) return item.children.some(isActive);
    if (!item.href || (item.exclude && currentPath.value.startsWith(item.exclude))) return false;

    return item.exact ? currentPath.value === item.href : currentPath.value.startsWith(item.href);
}

function toggle(key) {
    expanded.value[key] = !expanded.value[key];
}

function openActiveGroups(items) {
    items.forEach((item) => {
        if (!item.children) return;
        if (isActive(item)) expanded.value[item.key] = true;
        openActiveGroups(item.children);
    });
}

watch(currentPath, () => sections.forEach((section) => openActiveGroups(section.items)), { immediate: true });

function logout() { logoutForm.post('/logout'); }
</script>

<template>
    <div class="min-h-screen bg-surface">
        <button v-if="mobileMenuOpen" type="button" class="fixed inset-0 z-40 bg-primary/40 lg:hidden" aria-label="Tutup navigasi" @click="mobileMenuOpen = false" />
        <aside class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-primary py-6 shadow-xl transition-transform lg:translate-x-0" :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'">
            <div class="mb-6 flex items-center gap-3 px-6">
                <div class="grid size-10 place-items-center overflow-hidden rounded-lg bg-surface-container-lowest text-primary">
                    <img v-if="logoPath" :src="logoPath" alt="Logo lembaga" class="size-full object-contain" />
                    <AppIcon v-else name="account_balance" />
                </div>
                <div><p class="font-bold leading-none text-on-primary">BUMDesma/LKD</p><p class="mt-1 text-[10px] font-semibold uppercase tracking-widest text-primary-fixed-dim">Financial Management</p></div>
            </div>

            <nav class="scrollbar-hidden flex-1 space-y-5 overflow-y-auto px-2" aria-label="Navigasi utama">
                <section v-for="section in sections" :key="section.label">
                    <h2 class="mb-1 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-primary-fixed-dim/70">{{ section.label }}</h2>
                    <div class="space-y-1">
                        <template v-for="item in section.items" :key="item.key || item.label">
                            <button
                                v-if="item.children"
                                type="button"
                                class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left transition-colors hover:bg-primary-container hover:text-on-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-fixed/30"
                                :class="isActive(item) ? 'text-on-primary' : 'text-primary-fixed-dim'"
                                :aria-expanded="Boolean(expanded[item.key])"
                                @click="toggle(item.key)"
                            >
                                <AppIcon :name="item.icon" :filled="isActive(item)" />
                                <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                                <AppIcon name="expand_more" class="text-lg transition-transform duration-200" :class="expanded[item.key] && 'rotate-180'" />
                            </button>
                            <Link
                                v-else-if="item.href"
                                :href="item.href"
                                class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors"
                                :class="isActive(item) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                                @click="mobileMenuOpen = false"
                            >
                                <AppIcon :name="item.icon" :filled="isActive(item)" /><span>{{ item.label }}</span>
                            </Link>
                            <button v-else type="button" disabled class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-left text-primary-fixed-dim/45" :title="`${item.label} belum tersedia`"><AppIcon :name="item.icon" /><span>{{ item.label }}</span></button>

                            <Transition name="sidebar-menu">
                                <div v-if="item.children && expanded[item.key]" class="ml-5 space-y-1 overflow-hidden border-l border-primary-container pl-2">
                                <template v-for="child in item.children" :key="child.key || child.label">
                                    <button
                                        v-if="child.children"
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm transition-colors hover:bg-primary-container hover:text-on-primary focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-fixed/30"
                                        :class="isActive(child) ? 'text-on-primary' : 'text-primary-fixed-dim'"
                                        :aria-expanded="Boolean(expanded[child.key])"
                                        @click="toggle(child.key)"
                                    >
                                        <AppIcon :name="child.icon" :filled="isActive(child)" class="text-xl" />
                                        <span class="min-w-0 flex-1 truncate">{{ child.label }}</span>
                                        <AppIcon name="expand_more" class="text-lg transition-transform duration-200" :class="expanded[child.key] && 'rotate-180'" />
                                    </button>
                                    <Link
                                        v-else-if="child.href"
                                        :href="child.href"
                                        class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                        :class="isActive(child) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                                        @click="mobileMenuOpen = false"
                                    >
                                        <AppIcon :name="child.icon" :filled="isActive(child)" class="text-xl" /><span>{{ child.label }}</span>
                                    </Link>
                                    <button v-else type="button" disabled class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-primary-fixed-dim/45" :title="`${child.label} belum tersedia`"><AppIcon :name="child.icon" class="text-xl" /><span>{{ child.label }}</span></button>

                                    <Transition name="sidebar-menu">
                                        <div v-if="child.children && expanded[child.key]" class="ml-5 space-y-1 overflow-hidden border-l border-primary-container pl-2">
                                            <template v-for="leaf in child.children" :key="leaf.label">
                                                <Link
                                                    v-if="leaf.href"
                                                    :href="leaf.href"
                                                    class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm transition-colors"
                                                    :class="isActive(leaf) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                                                    @click="mobileMenuOpen = false"
                                                ><span class="size-1.5 rounded-full bg-current" />{{ leaf.label }}</Link>
                                                <button v-else type="button" disabled class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-primary-fixed-dim/45" :title="`${leaf.label} belum tersedia`"><span class="size-1.5 rounded-full bg-current" />{{ leaf.label }}</button>
                                            </template>
                                        </div>
                                    </Transition>
                                </template>
                                </div>
                            </Transition>
                        </template>
                    </div>
                </section>

                <section v-if="user?.is_superadmin">
                    <h2 class="mb-1 px-4 text-[10px] font-bold uppercase tracking-[0.18em] text-primary-fixed-dim/70">Platform</h2>
                    <Link v-for="item in platformNavigation" :key="item.label" :href="item.href" class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors" :class="isActive(item) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'" @click="mobileMenuOpen = false"><AppIcon :name="item.icon" :filled="isActive(item)" /><span>{{ item.label }}</span></Link>
                </section>
            </nav>

            <div class="mt-4 border-t border-primary-container px-4 pt-4">
                <div class="flex items-center gap-3 rounded-xl bg-primary-container/50 p-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-fixed text-sm font-bold text-primary">{{ user?.name?.charAt(0).toUpperCase() || 'U' }}</div>
                    <div class="min-w-0 flex-1"><p class="truncate font-bold text-on-primary">{{ user?.name || 'Pengguna' }}</p><p class="truncate text-xs text-primary-fixed-dim">{{ props.unitName || 'Unit belum dipilih' }}</p></div>
                    <button type="button" class="rounded-lg p-2 text-primary-fixed-dim hover:bg-on-primary/10 hover:text-on-primary" aria-label="Keluar" @click="logout"><AppIcon name="logout" class="text-xl" /></button>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-outline-variant bg-surface px-4 lg:ml-64 lg:px-6">
            <button type="button" class="mr-3 rounded-lg p-2 text-primary lg:hidden" aria-label="Buka navigasi" @click="mobileMenuOpen = true"><AppIcon name="menu" /></button>
            <div class="relative w-full max-w-md"><AppIcon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-on-surface-variant" /><input type="search" disabled placeholder="Pencarian segera tersedia" aria-label="Pencarian belum tersedia" class="w-full rounded-full border-0 bg-surface-container-low py-2 pr-4 pl-11 text-sm text-on-surface-variant disabled:cursor-not-allowed"></div>
            <p v-if="props.unitName" class="ml-6 hidden items-center gap-2 text-sm font-bold text-primary xl:flex"><AppIcon name="location_on" class="text-secondary" />{{ props.unitName }}</p>
            <div class="ml-auto flex items-center gap-1 pl-3">
                <button type="button" disabled class="rounded-full p-2 text-on-surface-variant" aria-label="Notifikasi belum tersedia"><AppIcon name="notifications" /></button>
                <Link href="/profile" class="rounded-full p-2 text-on-surface-variant transition-colors hover:bg-surface-container hover:text-primary" aria-label="Profil"><AppIcon name="account_circle" /></Link>
            </div>
        </header>
        <main class="p-4 sm:p-6 lg:ml-64 lg:p-8"><slot /></main>
        <AssistantWidget v-if="assistantEnabled" />
        <AppToast />
    </div>
</template>

<style scoped>
.sidebar-menu-enter-active,
.sidebar-menu-leave-active {
    max-height: 40rem;
    opacity: 1;
    transform: translateY(0);
    transition: max-height 240ms ease, opacity 180ms ease, transform 240ms ease;
}

.sidebar-menu-enter-from,
.sidebar-menu-leave-to {
    max-height: 0;
    opacity: 0;
    transform: translateY(-0.25rem);
}

@media (prefers-reduced-motion: reduce) {
    .sidebar-menu-enter-active,
    .sidebar-menu-leave-active {
        transition: none;
    }
}
</style>
