<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppButton from '../../Components/AppButton.vue';
import AppCheckbox from '../../Components/AppCheckbox.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <Head title="Masuk Ke Portal" />

    <main class="flex min-h-screen overflow-hidden bg-surface font-sans text-on-surface">
        <!-- Left Branding Showcase Panel -->
        <section class="login-panel relative hidden w-[52%] flex-col justify-between overflow-hidden p-12 lg:flex" aria-label="Informasi SIDBM">
            <div class="absolute -left-28 top-1/3 size-96 rounded-full border border-on-primary/10" />
            <div class="absolute -left-10 top-1/3 size-96 rounded-full border border-on-primary/10" />

            <!-- Brand Header -->
            <div class="relative z-10 flex items-center justify-between">
                <Link href="/" class="flex items-center gap-3 transition hover:opacity-90">
                    <div class="grid size-12 place-items-center rounded-2xl bg-surface-container-lowest text-primary-container shadow-md">
                        <AppIcon name="account_balance" class="text-3xl" />
                    </div>
                    <div>
                        <p class="text-xl font-extrabold tracking-tight text-on-primary">SIDBM <span class="text-secondary-container">Next</span></p>
                        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-fixed-dim">BUMDesma LKD Financial System</p>
                    </div>
                </Link>

                <Link href="/">
                    <AppButton variant="ghost" size="compact" class="!text-on-primary hover:!bg-on-primary/10" icon="arrow_back">
                        Kembali ke Beranda
                    </AppButton>
                </Link>
            </div>

            <!-- Central Content Pitch -->
            <div class="relative z-10 my-auto mx-auto max-w-xl text-center space-y-6">
                <div class="mx-auto grid max-w-xs grid-cols-3 items-end gap-4" aria-hidden="true">
                    <div class="h-24 rounded-t-2xl bg-on-primary/15" />
                    <div class="h-36 rounded-t-2xl bg-secondary-container/80 shadow-lg" />
                    <div class="h-28 rounded-t-2xl bg-primary-fixed/80" />
                    <div class="col-span-3 h-2 rounded-full bg-on-primary/20" />
                </div>

                <div class="space-y-3">
                    <h1 class="text-4xl font-black leading-tight text-on-primary">
                        Transformasi Digital <br /> Tata Kelola Dana Desa
                    </h1>
                    <p class="mx-auto max-w-md text-base leading-relaxed text-primary-fixed/85">
                        Mewujudkan kemandirian finansial masyarakat melalui pengelolaan dana bergulir yang akuntabel dan transparan.
                    </p>
                </div>
            </div>

            <!-- Footer Motto & Compliance -->
            <div class="relative z-10 border-t border-on-primary/15 pt-6 flex items-center justify-between text-xs text-primary-fixed-dim font-medium">
                <p class="italic">“Mengelola Dana Desa, Membangun Kesejahteraan Bersama”</p>
                <span>Standar SAK EP & PP No. 11/2021</span>
            </div>
        </section>

        <!-- Right Login Form Panel -->
        <section class="flex w-full flex-col justify-between px-6 py-8 md:px-12 lg:w-[48%] bg-surface-container-lowest">
            <!-- Mobile Top Header -->
            <div class="flex items-center justify-between lg:hidden mb-8">
                <Link href="/" class="flex items-center gap-2">
                    <div class="grid size-10 place-items-center rounded-xl bg-primary-container text-on-primary shadow-sm">
                        <AppIcon name="account_balance" class="text-2xl" />
                    </div>
                    <span class="text-lg font-black text-primary">SIDBM Next</span>
                </Link>

                <Link href="/">
                    <AppButton variant="ghost" size="compact" icon="arrow_back">
                        Beranda
                    </AppButton>
                </Link>
            </div>

            <!-- Form Container -->
            <div class="my-auto mx-auto w-full max-w-md space-y-8">
                <header class="space-y-2">
                    <span class="inline-flex items-center rounded-full bg-primary-fixed px-3 py-1 text-xs font-bold text-primary">
                        Portal Autentikasi Pengurus & Admin
                    </span>
                    <h1 class="text-3xl font-black tracking-tight text-primary">Masuk ke Akun Anda</h1>
                    <p class="text-sm text-on-surface-variant">Masukkan kredensial pengguna terdaftar BUMDesma Anda.</p>
                </header>

                <form class="space-y-6" @submit.prevent="submit">
                    <AppInput
                        v-model="form.identifier"
                        label="Username atau Email"
                        icon="person"
                        autocomplete="username"
                        placeholder="Masukkan username atau email anda"
                        required
                        autofocus
                        :error="form.errors.identifier"
                    />

                    <AppInput
                        v-model="form.password"
                        label="Kata Sandi (Password)"
                        icon="lock"
                        :type="showPassword ? 'text' : 'password'"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        required
                        :error="form.errors.password"
                    >
                        <template #trailing>
                            <AppIconButton
                                :name="showPassword ? 'visibility_off' : 'visibility'"
                                size="sm"
                                tone="neutral"
                                rounded="lg"
                                :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
                                @click="showPassword = !showPassword"
                            />
                        </template>
                    </AppInput>

                    <div class="flex items-center justify-between text-sm">
                        <AppCheckbox v-model="form.remember" variant="inline" label="Ingat sesi saya" />
                    </div>

                    <AppButton
                        type="submit"
                        variant="success"
                        size="large"
                        class="w-full font-bold shadow-md"
                        :loading="form.processing"
                        icon="login"
                    >
                        {{ form.processing ? 'Memverifikasi...' : 'Masuk ke Dashboard' }}
                    </AppButton>
                </form>

                <!-- Help Info -->
                <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4 text-xs text-on-surface-variant leading-relaxed flex items-start gap-3">
                    <AppIcon name="info" class="text-primary text-lg shrink-0 mt-0.5" />
                    <div>
                        <p class="font-bold text-primary">Butuh bantuan akses?</p>
                        <p>Lupa kata sandi atau akun terblokir? Silakan hubungi Administrator Utama BUMDesma atau Dinas PMD setempat.</p>
                    </div>
                </div>
            </div>

            <!-- Footer Copyright -->
            <footer class="mt-8 text-center text-xs font-medium text-outline">
                © 2026 BUMDesma LKD. Seluruh hak cipta dilindungi.
            </footer>
        </section>
    </main>
</template>