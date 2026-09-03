<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    settings: { type: Object, required: true },
    heroImageUrl: { type: String, default: null },
});

const form = useForm({
    hero_tagline: props.settings.hero_tagline ?? '',
    hero_description: props.settings.hero_description ?? '',
    about_short: props.settings.about_short ?? '',
    facebook_url: props.settings.facebook_url ?? '',
    instagram_url: props.settings.instagram_url ?? '',
    youtube_url: props.settings.youtube_url ?? '',
    contact_phone: props.settings.contact_phone ?? '',
    contact_email: props.settings.contact_email ?? '',
    contact_address: props.settings.contact_address ?? '',
    footer_note: props.settings.footer_note ?? '',
    hero_image: null,
    remove_hero_image: false,
    _method: 'PUT',
});

function onHeroImageChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.hero_image = file;
    if (file) {
        form.remove_hero_image = false;
    }
}

function submit() {
    form.post(route('website.settings.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.hero_image = null;
            form.remove_hero_image = false;
        },
    });
}
</script>

<template>
    <Head title="Pengaturan Situs" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-2xl font-extrabold tracking-tight text-primary">Pengaturan Situs</h1>
            <p class="mt-1 text-sm text-on-surface-variant">
                Tampilan halaman depan, kanal sosial media, dan kontak pada situs publik desa.
            </p>
        </template>

        <form class="mx-auto max-w-3xl space-y-6" @submit.prevent="submit">
            <AppCard padded>
                <h2 class="text-base font-bold text-primary">Hero Halaman Depan</h2>
                <div class="mt-4 space-y-5">
                    <AppInput
                        v-model="form.hero_tagline"
                        label="Tagline"
                        icon="campaign"
                        placeholder="Contoh: Melayani desa dengan transparan"
                        :error="form.errors.hero_tagline"
                    />
                    <AppTextarea
                        v-model="form.hero_description"
                        label="Deskripsi Hero"
                        icon="notes"
                        placeholder="Kalimat pengantar yang tampil di bawah nama lembaga"
                        :error="form.errors.hero_description"
                    />

                    <div class="space-y-2">
                        <label class="ml-1 block text-sm font-bold uppercase tracking-wider text-primary">Gambar Hero</label>
                        <div v-if="heroImageUrl && !form.remove_hero_image" class="flex items-center gap-4">
                            <img :src="heroImageUrl" alt="Gambar hero saat ini" class="h-24 w-40 rounded-xl border border-outline-variant object-cover">
                            <AppButton variant="secondary" size="compact" type="button" @click="form.remove_hero_image = true">
                                Hapus gambar
                            </AppButton>
                        </div>
                        <input
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="block w-full text-sm text-on-surface-variant file:mr-4 file:rounded-xl file:border-0 file:bg-primary-container file:px-4 file:py-2.5 file:text-sm file:font-bold file:text-on-primary-container hover:file:bg-primary/20"
                            @change="onHeroImageChange"
                        >
                        <p v-if="form.errors.hero_image" class="ml-1 text-sm text-error">{{ form.errors.hero_image }}</p>
                        <p v-if="form.remove_hero_image" class="ml-1 text-sm text-tertiary">
                            Gambar akan dihapus saat disimpan.
                            <button type="button" class="font-semibold underline" @click="form.remove_hero_image = false">Batalkan</button>
                        </p>
                    </div>
                </div>
            </AppCard>

            <AppCard padded>
                <h2 class="text-base font-bold text-primary">Tentang Singkat</h2>
                <div class="mt-4">
                    <AppTextarea
                        v-model="form.about_short"
                        label="Ringkasan Profil"
                        icon="info"
                        placeholder="Paragraf singkat tentang lembaga"
                        :error="form.errors.about_short"
                    />
                </div>
            </AppCard>

            <AppCard padded>
                <h2 class="text-base font-bold text-primary">Sosial Media</h2>
                <div class="mt-4 space-y-5">
                    <AppInput
                        v-model="form.facebook_url"
                        label="Facebook"
                        icon="public"
                        type="url"
                        placeholder="https://facebook.com/nama-lembaga"
                        :error="form.errors.facebook_url"
                    />
                    <AppInput
                        v-model="form.instagram_url"
                        label="Instagram"
                        icon="photo_camera"
                        type="url"
                        placeholder="https://instagram.com/nama-lembaga"
                        :error="form.errors.instagram_url"
                    />
                    <AppInput
                        v-model="form.youtube_url"
                        label="YouTube"
                        icon="smart_display"
                        type="url"
                        placeholder="https://youtube.com/@nama-lembaga"
                        :error="form.errors.youtube_url"
                    />
                </div>
            </AppCard>

            <AppCard padded>
                <h2 class="text-base font-bold text-primary">Kontak &amp; Footer</h2>
                <div class="mt-4 space-y-5">
                    <AppInput
                        v-model="form.contact_phone"
                        label="Telepon Kontak"
                        icon="call"
                        placeholder="Contoh: 0812-3456-7890"
                        :error="form.errors.contact_phone"
                    />
                    <AppInput
                        v-model="form.contact_email"
                        label="Email Kontak"
                        icon="mail"
                        type="email"
                        placeholder="contoh@desa.test"
                        :error="form.errors.contact_email"
                    />
                    <AppTextarea
                        v-model="form.contact_address"
                        label="Alamat Kontak"
                        icon="place"
                        placeholder="Alamat kantor yang tampil di halaman kontak"
                        :error="form.errors.contact_address"
                    />
                    <AppInput
                        v-model="form.footer_note"
                        label="Catatan Footer"
                        icon="copyright"
                        placeholder="Teks singkat di bagian bawah situs"
                        :error="form.errors.footer_note"
                    />
                </div>
            </AppCard>

            <div class="flex justify-end">
                <AppButton type="submit" icon="save" :loading="form.processing">
                    Simpan Pengaturan
                </AppButton>
            </div>
        </form>
    </AuthenticatedLayout>
</template>
