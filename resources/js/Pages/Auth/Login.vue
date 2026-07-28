<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk" />

    <main class="flex min-h-screen overflow-hidden bg-surface">
        <section class="login-panel relative hidden w-[55%] flex-col overflow-hidden p-12 lg:flex" aria-label="Tentang SIDBM">
            <div class="absolute -left-28 top-1/3 size-96 rounded-full border border-on-primary/10" />
            <div class="absolute -left-10 top-1/3 size-96 rounded-full border border-on-primary/10" />
            <div class="relative z-10 flex items-center gap-3">
                <div class="grid size-12 place-items-center rounded-xl bg-surface-container-lowest text-primary-container"><AppIcon name="account_balance" class="text-3xl" /></div>
                <div><p class="text-xl font-bold tracking-tight text-on-primary">BUMDesma</p><p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-fixed-dim">LKD Financial Management</p></div>
            </div>
            <div class="relative z-10 my-auto mx-auto max-w-xl text-center">
                <div class="mx-auto mb-10 grid max-w-md grid-cols-3 items-end gap-5" aria-hidden="true"><div class="h-28 rounded-t-2xl bg-on-primary/10" /><div class="h-44 rounded-t-2xl bg-secondary-container/80" /><div class="h-36 rounded-t-2xl bg-primary-fixed/80" /><div class="col-span-3 h-2 rounded-full bg-on-primary/20" /></div>
                <h1 class="text-4xl font-bold leading-tight text-on-primary">Transformasi Digital<br>Ekonomi Desa</h1>
                <p class="mx-auto mt-5 max-w-lg text-lg leading-7 text-primary-fixed/75">Mewujudkan kemandirian finansial masyarakat melalui pengelolaan dana bergulir yang transparan dan akuntabel.</p>
            </div>
            <p class="relative z-10 border-t border-on-primary/10 pt-8 text-lg font-medium italic text-on-primary">“Mengelola Dana Desa, Membangun Kesejahteraan Bersama”</p>
        </section>

        <section class="flex w-full flex-col px-6 py-8 md:px-12 lg:w-[45%]">
            <div class="mb-10 flex flex-col items-center text-center lg:hidden">
                <div class="grid size-14 place-items-center rounded-xl bg-primary-container text-on-primary shadow-lg"><AppIcon name="account_balance" class="text-4xl" /></div>
                <p class="mt-3 text-2xl font-black text-primary">BUMDesma LKD</p>
            </div>
            <div class="my-auto mx-auto w-full max-w-md">
                <header class="mb-10"><h1 class="text-3xl font-black tracking-tight text-primary">Masuk ke Akun Anda</h1><p class="mt-2 text-on-surface-variant">Sistem Informasi Dana Bergulir Masyarakat</p></header>
                <form class="space-y-6" @submit.prevent="submit">
                    <AppInput v-model="form.identifier" label="Username atau Email" icon="person" autocomplete="username" placeholder="Contoh: admin_desa" required autofocus :error="form.errors.identifier" />
                    <AppInput v-model="form.password" label="Password" icon="lock" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" placeholder="••••••••" required :error="form.errors.password">
                        <template #trailing>
                            <AppButton variant="ghost" size="compact" icon-only :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'" :aria-pressed="showPassword" @click="showPassword = !showPassword"><AppIcon :name="showPassword ? 'visibility_off' : 'visibility'" class="text-base" /></AppButton>
                        </template>
                    </AppInput>
                    <label class="flex w-fit cursor-pointer items-center gap-2 text-sm font-medium text-primary"><input v-model="form.remember" type="checkbox" class="size-5 rounded border-outline-variant text-primary focus:ring-primary">Ingat saya</label>
                    <AppButton type="submit" variant="success" size="large" class="w-full" :loading="form.processing" icon="login">{{ form.processing ? 'Memproses…' : 'Masuk' }}</AppButton>
                </form>
            </div>
            <footer class="mt-10 text-center text-xs font-medium text-outline">© 2026 BUMDesma LKD. Seluruh hak cipta dilindungi.</footer>
        </section>
    </main>
</template>
