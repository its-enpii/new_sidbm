<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppConfirmDialog from '../Components/AppConfirmDialog.vue';
import AppToast from '../Components/AppToast.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url.split('?')[0]);
const mobileMenuOpen = ref(false);
const logoutForm = useForm({});

const navigation = [
    { label: 'Dashboard', icon: 'dashboard', href: '/province/dashboard', exact: true },
    { label: 'Paket 5 Laporan (PDF)', icon: 'picture_as_pdf', href: '/province/reports/pack' },
    { label: 'Neraca', icon: 'account_balance', href: '/province/reports/balance-sheet' },
    { label: 'Laba Rugi', icon: 'trending_up', href: '/province/reports/income-statement' },
    { label: 'Arus Kas', icon: 'payments', href: '/province/reports/cash-flow' },
    { label: 'Perubahan Ekuitas', icon: 'balance', href: '/province/reports/equity-changes' },
    { label: 'CALK', icon: 'description', href: '/province/reports/calk' },
];

function isActive(item) {
    if (!item.href) return false;
    return item.exact ? currentPath.value === item.href : currentPath.value.startsWith(item.href);
}

function logout() {
    logoutForm.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-surface">
        <button
            v-if="mobileMenuOpen"
            type="button"
            class="fixed inset-0 z-40 bg-primary/40 lg:hidden"
            aria-label="Tutup navigasi"
            @click="mobileMenuOpen = false"
        />

        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-slate-950 py-6 shadow-xl transition-transform lg:translate-x-0"
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="mb-8 px-6">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-emerald-400">Portal Provinsi</p>
                <p class="mt-1 text-lg font-bold text-white">{{ user?.province_name || 'Provinsi' }}</p>
                <p class="text-xs text-slate-400">Konsolidasi Multi-Kabupaten</p>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors"
                    :class="isActive(item) ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                    @click="mobileMenuOpen = false"
                >
                    <AppIcon :name="item.icon" :filled="isActive(item)" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-4 border-t border-slate-800 px-4 pt-4">
                <div class="flex items-center gap-3 rounded-xl bg-slate-900 p-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-emerald-500 text-sm font-bold text-slate-950">
                        {{ user?.name?.charAt(0).toUpperCase() || 'P' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-white">{{ user?.name || 'Supervisor' }}</p>
                        <p class="truncate text-xs text-slate-400">Level Provinsi</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-slate-400 hover:bg-white/10 hover:text-white"
                        aria-label="Keluar"
                        @click="logout"
                    >
                        <AppIcon name="logout" class="text-xl" />
                    </button>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-outline-variant bg-surface px-4 lg:ml-64 lg:px-6">
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    class="rounded-lg p-2 text-primary lg:hidden"
                    aria-label="Buka navigasi"
                    @click="mobileMenuOpen = true"
                >
                    <AppIcon name="menu" />
                </button>
                <div>
                    <p class="text-sm font-bold text-primary">Monitoring & Konsolidasi Laporan Provinsi</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300">
                    <span class="size-1.5 rounded-full bg-emerald-500"></span>
                    {{ user?.province_name || 'Provinsi' }}
                </span>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:ml-64 lg:p-8">
            <slot />
        </main>

        <AppConfirmDialog />
        <AppToast />
    </div>
</template>
