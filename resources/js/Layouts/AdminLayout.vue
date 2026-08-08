<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppIcon from '../Components/AppIcon.vue';
import AppConfirmDialog from '../Components/AppConfirmDialog.vue';
import AppToast from '../Components/AppToast.vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const appName = computed(() => page.props.appName || 'SIDBM Next');
const currentPath = computed(() => page.url.split('?')[0]);
const mobileMenuOpen = ref(false);
const logoutForm = useForm({});

const navigation = [
    { label: 'Dashboard', icon: 'dashboard', href: '/admin', exact: true },
    { label: 'Tenant', icon: 'domain', href: '/admin/tenants' },
    { label: 'Invoice', icon: 'receipt_long', href: '/admin/invoices' },
    { label: 'Plan', icon: 'workspace_premium', href: '/admin/plans' },
    { label: 'Integrasi', icon: 'hub', href: '/admin/integrations/orchestrator' },
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
            class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-primary py-6 shadow-xl transition-transform lg:translate-x-0"
            :class="mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <div class="mb-8 px-6">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-primary-fixed-dim">Platform Admin</p>
                <p class="mt-1 text-lg font-bold text-on-primary">{{ appName }}</p>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3">
                <Link
                    v-for="item in navigation"
                    :key="item.label"
                    :href="item.href"
                    class="flex items-center gap-3 rounded-lg px-4 py-2.5 transition-colors"
                    :class="isActive(item) ? 'bg-primary-container text-on-primary' : 'text-primary-fixed-dim hover:bg-primary-container hover:text-on-primary'"
                    @click="mobileMenuOpen = false"
                >
                    <AppIcon :name="item.icon" :filled="isActive(item)" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="mt-4 border-t border-primary-container px-4 pt-4">
                <div class="flex items-center gap-3 rounded-xl bg-primary-container/50 p-3">
                    <div class="grid size-10 shrink-0 place-items-center rounded-full bg-primary-fixed text-sm font-bold text-primary">
                        {{ user?.name?.charAt(0).toUpperCase() || 'A' }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-bold text-on-primary">{{ user?.name || 'Admin' }}</p>
                        <p class="truncate text-xs text-primary-fixed-dim">Superadmin</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-2 text-primary-fixed-dim hover:bg-on-primary/10 hover:text-on-primary"
                        aria-label="Keluar"
                        @click="logout"
                    >
                        <AppIcon name="logout" class="text-xl" />
                    </button>
                </div>
            </div>
        </aside>

        <header class="sticky top-0 z-30 flex h-16 items-center border-b border-outline-variant bg-surface px-4 lg:ml-64 lg:px-6">
            <button
                type="button"
                class="mr-3 rounded-lg p-2 text-primary lg:hidden"
                aria-label="Buka navigasi"
                @click="mobileMenuOpen = true"
            >
                <AppIcon name="menu" />
            </button>
            <p class="text-sm font-bold text-primary">Panel Admin Platform</p>
        </header>

        <main class="p-4 sm:p-6 lg:ml-64 lg:p-8">
            <slot />
        </main>

        <AppConfirmDialog />
        <AppToast />
    </div>
</template>
