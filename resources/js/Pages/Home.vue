<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppButton from '../Components/AppButton.vue';
import AppIcon from '../Components/AppIcon.vue';
import AppCard from '../Components/AppCard.vue';

defineProps({ name: String, status: String });
</script>

<template>
    <Head title="Beranda" />
    <main class="grid min-h-screen place-items-center bg-surface p-8 text-on-surface">
        <AppCard class="w-full max-w-2xl" bordered>
            <p class="text-xs font-bold uppercase tracking-[0.15em] text-secondary">Laravel 13 · Vue 3 · Inertia · Tailwind CSS</p>
            <h1 class="my-2 text-5xl font-bold tracking-tight text-primary sm:text-7xl">{{ name }}</h1>
            <p class="leading-7 text-on-surface-variant">Foundation platform dan tenant shard siap dikembangkan.</p>
            <span class="mt-5 inline-flex items-center gap-2 font-bold text-secondary"><AppIcon name="check_circle" filled class="text-xl" />{{ status }}</span>
            <Link href="/login" class="mt-8 inline-block"><AppButton icon="login">Masuk ke aplikasi</AppButton></Link>
        </AppCard>
    </main>
</template>
