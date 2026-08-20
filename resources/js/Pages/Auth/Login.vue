<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref, onMounted, nextTick } from 'vue';
import gsap from 'gsap';
import AppButton from '../../Components/AppButton.vue';
import AppCheckbox from '../../Components/AppCheckbox.vue';
import AppIcon from '../../Components/AppIcon.vue';
import AppIconButton from '../../Components/AppIconButton.vue';
import AppInput from '../../Components/AppInput.vue';

const showPassword = ref(false);
const form = useForm({ identifier: '', password: '', remember: false });
const formContainerRef = ref(null);

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
        onError: () => {
            if (formContainerRef.value) {
                gsap.fromTo(
                    formContainerRef.value,
                    { x: -10 },
                    { x: 10, duration: 0.08, repeat: 5, yoyo: true, ease: 'power1.inOut', onComplete: () => {
                        gsap.set(formContainerRef.value, { x: 0 });
                    }}
                );
            }
        },
    });
}

onMounted(() => {
    nextTick(() => {
        const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

        // Ambient background circles floating loop
        gsap.to('.ambient-circle-1', {
            y: -20,
            x: 10,
            duration: 6,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
        });
        gsap.to('.ambient-circle-2', {
            y: 25,
            x: -15,
            duration: 7,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 1,
        });

        // Left branding showcase entrance
        tl.fromTo(
            '.brand-header',
            { opacity: 0, y: -20 },
            { opacity: 1, y: 0, duration: 0.6 }
        )
        .fromTo(
            '.chart-bar',
            { scaleY: 0, opacity: 0 },
            {
                scaleY: 1,
                opacity: 1,
                transformOrigin: 'bottom center',
                duration: 0.8,
                stagger: 0.15,
                ease: 'back.out(1.5)',
            },
            '-=0.3'
        )
        .fromTo(
            '.brand-text',
            { opacity: 0, y: 25 },
            { opacity: 1, y: 0, duration: 0.7, stagger: 0.12 },
            '-=0.5'
        )
        .fromTo(
            '.brand-footer',
            { opacity: 0 },
            { opacity: 1, duration: 0.5 },
            '-=0.3'
        );

        // Right form entrance
        gsap.fromTo(
            '.form-anim-item',
            { opacity: 0, y: 24 },
            {
                opacity: 1,
                y: 0,
                duration: 0.65,
                stagger: 0.08,
                ease: 'power2.out',
                delay: 0.1,
            }
        );
    });
});
</script>

<template>
    <Head title="Masuk Ke Portal" />

    <main class="flex min-h-screen overflow-hidden bg-surface font-sans text-on-surface">
        <!-- Left Branding Showcase Panel -->
        <section class="login-panel relative hidden w-[52%] flex-col justify-between overflow-hidden p-12 lg:flex" aria-label="Informasi SIDBM">
            <div class="ambient-circle-1 absolute -left-28 top-1/3 size-96 rounded-full border border-on-primary/15 bg-on-primary/[0.02] blur-sm pointer-events-none" />
            <div class="ambient-circle-2 absolute -left-10 top-1/3 size-96 rounded-full border border-on-primary/10 bg-secondary-container/[0.05] blur-sm pointer-events-none" />

            <!-- Brand Header -->
            <div class="brand-header relative z-10 flex items-center justify-between">
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
                    <div class="chart-bar h-24 rounded-t-2xl bg-on-primary/20 backdrop-blur-sm" />
                    <div class="chart-bar h-36 rounded-t-2xl bg-secondary-container/85 shadow-lg" />
                    <div class="chart-bar h-28 rounded-t-2xl bg-primary-fixed/85 shadow-md" />
                    <div class="col-span-3 h-2 rounded-full bg-on-primary/20" />
                </div>

                <div class="space-y-3">
                    <h1 class="brand-text text-4xl font-black leading-tight text-on-primary">
                        Transformasi Digital <br /> Tata Kelola Dana Desa
                    </h1>
                    <p class="brand-text mx-auto max-w-md text-base leading-relaxed text-primary-fixed/85">
                        Mewujudkan kemandirian finansial masyarakat melalui pengelolaan dana bergulir yang akuntabel dan transparan.
                    </p>
                </div>
            </div>

            <!-- Footer Motto & Compliance -->
            <div class="brand-footer relative z-10 border-t border-on-primary/15 pt-6 flex items-center justify-between text-xs text-primary-fixed-dim font-medium">
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
            <div ref="formContainerRef" class="my-auto mx-auto w-full max-w-md space-y-8">
                <header class="form-anim-item space-y-2">
                    <span class="inline-flex items-center rounded-full bg-primary-fixed px-3 py-1 text-xs font-bold text-primary">
                        Portal Autentikasi Pengurus & Admin
                    </span>
                    <h1 class="text-3xl font-black tracking-tight text-primary">Masuk ke Akun Anda</h1>
                    <p class="text-sm text-on-surface-variant">Masukkan kredensial pengguna terdaftar BUMDesma Anda.</p>
                </header>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="form-anim-item">
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
                    </div>

                    <div class="form-anim-item">
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
                    </div>

                    <div class="form-anim-item flex items-center justify-between text-sm">
                        <AppCheckbox v-model="form.remember" variant="inline" label="Ingat sesi saya" />
                    </div>

                    <div class="form-anim-item">
                        <AppButton
                            type="submit"
                            variant="success"
                            size="large"
                            class="w-full font-bold shadow-md hover:shadow-lg transition-shadow"
                            :loading="form.processing"
                            icon="login"
                        >
                            {{ form.processing ? 'Memverifikasi...' : 'Masuk ke Dashboard' }}
                        </AppButton>
                    </div>
                </form>

                <!-- Help Info -->
                <div class="form-anim-item rounded-xl border border-outline-variant bg-surface-container-low p-4 text-xs text-on-surface-variant leading-relaxed flex items-start gap-3">
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