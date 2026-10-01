<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '../../Components/AppButton.vue';
import AppCard from '../../Components/AppCard.vue';
import AppEmptyState from '../../Components/AppEmptyState.vue';
import AuthenticatedLayout from '../../Layouts/AuthenticatedLayout.vue';

defineProps({
    userName: { type: String, default: null },
});
</script>

<template>
    <Head title="Portal Anggota" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-primary sm:text-3xl">Portal Saya</h1>
                <p class="mt-1 text-sm text-on-surface-variant">Akses data keanggotaan dan pinjaman pribadi Anda.</p>
            </header>

            <AppCard>
                <AppEmptyState
                    icon="account_circle"
                    title="Akun Belum Terhubung ke Data Anggota"
                    description="Akun Anda saat ini belum ditautkan dengan data anggota pemanfaat. Hubungi pengurus atau administrator untuk menghubungkan akun Anda di menu Manajemen Pengguna."
                >
                    <Link href="/dashboard">
                        <AppButton icon="arrow_back">Kembali ke Dashboard</AppButton>
                    </Link>
                </AppEmptyState>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
