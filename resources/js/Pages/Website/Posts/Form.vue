<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppRichEditor from '../../../Components/AppRichEditor.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    post: { type: Object, default: null },
});

const editing = Boolean(props.post);
const form = useForm({
    title: props.post?.title || '',
    slug: props.post?.slug || '',
    excerpt: props.post?.excerpt || '',
    content: props.post?.content || '',
    status: props.post?.status || 'draft',
    published_at: props.post?.published_at || '',
    meta_description: props.post?.meta_description || '',
    cover_image: null,
});

const statusOptions = [
    { value: 'draft', label: 'Draf (belum tampil)' },
    { value: 'published', label: 'Terbit' },
];

const path = '/website/posts';
function submit() {
    editing ? form.put(`${path}/${props.post.row_id}`) : form.post(path);
}
</script>

<template>
    <Head :title="editing ? 'Edit Berita' : 'Tulis Berita'" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl">
            <header class="mb-6">
                <Link :href="path" class="text-sm font-semibold text-primary">← Kembali ke daftar berita</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">{{ editing ? 'Edit Berita' : 'Tulis Berita' }}</h1>
                <p class="mt-1 text-on-surface-variant">{{ editing ? 'Perbarui isi berita lalu simpan.' : 'Isi detail berita yang akan tampil di situs publik.' }}</p>
            </header>

            <AppCard>
                <form class="space-y-5" @submit.prevent="submit">
                    <section>
                        <h2 class="font-semibold text-primary">Konten</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <AppInput v-model="form.title" label="Judul Berita" icon="title" required :error="form.errors.title" />
                            <AppInput
                                v-model="form.slug"
                                label="Slug (opsional)"
                                icon="link"
                                hint="Kosongkan untuk dibuat otomatis dari judul. Format: teks-dengan-tanda-hubung"
                                :error="form.errors.slug"
                            />
                        </div>
                        <div class="mt-4">
                            <label class="mb-1.5 block text-sm font-medium text-on-surface-variant">Isi Berita</label>
                            <AppRichEditor v-model="form.content" placeholder="Tulis isi berita…" />
                            <p v-if="form.errors.content" class="mt-1 text-sm text-error">{{ form.errors.content }}</p>
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Ringkasan &amp; Gambar Sampul</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-on-surface-variant">Ringkasan (excerpt)</label>
                                <textarea
                                    v-model="form.excerpt"
                                    rows="3"
                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none"
                                    placeholder="Ringkasan singkat yang tampil di daftar berita…"
                                />
                                <p v-if="form.errors.excerpt" class="mt-1 text-sm text-error">{{ form.errors.excerpt }}</p>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-on-surface-variant">Gambar Sampul</label>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface file:mr-3 file:rounded-full file:border-0 file:bg-primary-container file:px-4 file:py-1.5 file:text-sm file:font-semibold file:text-on-primary-container"
                                    @change="e => form.cover_image = e.target.files[0]"
                                >
                                <p class="mt-1 text-xs text-on-surface-variant">PNG / JPG / WebP · Maks 2 MB</p>
                                <p v-if="form.errors.cover_image" class="mt-1 text-sm text-error">{{ form.errors.cover_image }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="border-t border-outline-variant pt-4">
                        <h2 class="font-semibold text-primary">Publikasi &amp; SEO</h2>
                        <div class="mt-3 grid gap-4 sm:grid-cols-2">
                            <SmartSelect
                                v-model="form.status"
                                label="Status"
                                :options="statusOptions"
                                :error="form.errors.status"
                            />
                            <AppInput
                                v-model="form.published_at"
                                label="Tanggal Terbit"
                                icon="event"
                                type="datetime-local"
                                hint="Kosongkan saat mempublikasikan untuk memakai waktu sekarang."
                                :error="form.errors.published_at"
                            />
                            <AppInput
                                v-model="form.meta_description"
                                label="Meta Deskripsi"
                                icon="manage_search"
                                class="sm:col-span-2"
                                hint="Deskripsi singkat untuk mesin pencari (maks. 255 karakter)."
                                :error="form.errors.meta_description"
                            />
                        </div>
                    </section>

                    <div class="flex justify-end gap-3 border-t border-outline-variant pt-5">
                        <Link :href="path"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AuthenticatedLayout>
</template>
