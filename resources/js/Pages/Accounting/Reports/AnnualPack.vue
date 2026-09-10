<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppIcon from '../../../Components/AppIcon.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AuthenticatedLayout from '../../../Layouts/AuthenticatedLayout.vue';

const props = defineProps({
    year: { type: Number, required: true },
    period_label: { type: String, required: true },
    identity: { type: Object, required: true },
    villages: { type: Array, required: true },
    filters: { type: Object, required: true },
});

const selectedYear = ref(String(props.filters.year));

const yearOptions = computed(() => {
    const current = new Date().getFullYear();
    const list = [];
    for (let y = current + 1; y >= current - 5; y--) {
        list.push({ value: String(y), label: String(y) });
    }
    return list;
});

function apply() {
    router.get(
        '/accounting/reports/annual-pack',
        { year: selectedYear.value },
        { preserveState: true, replace: true },
    );
}

const docs = computed(() => [
    {
        title: 'Cover Buku Laporan Tahunan',
        desc: 'Sampul resmi buku laporan keuangan & LPJ tahunan berlogo',
        href: `/accounting/reports/annual-pack/cover/pdf?year=${selectedYear.value}`,
        icon: 'auto_stories',
    },
    {
        title: 'Surat Pengantar Laporan (LPJ)',
        desc: 'Surat dinas penyampaian bendel laporan kepada MAD, BPD, dan Pembina',
        href: `/accounting/reports/annual-pack/surat-pengantar/pdf?year=${selectedYear.value}`,
        icon: 'mark_email_read',
    },
    {
        title: 'Berita Acara Pengesahan (LPJ)',
        desc: 'Naskah Berita Acara Musyawarah Antar Desa penetapan surplus & pelepasan tanggung jawab',
        href: `/accounting/reports/annual-pack/ba-pergantian/pdf?year=${selectedYear.value}`,
        icon: 'fact_check',
    },
    {
        title: 'Naskah Kerjasama Antar Desa (MoU)',
        desc: 'Kesepakatan bersama Kepala Desa se-Kecamatan atas pengelolaan usaha dana bergulir',
        href: `/accounting/reports/annual-pack/mou/pdf?year=${selectedYear.value}`,
        icon: 'handshake',
    },
]);
</script>

<template>
    <Head title="Dokumen LPJ & Cover Tahunan" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl space-y-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-on-surface-variant">Administrasi Pelaporan</p>
                    <h1 class="mt-1 text-2xl font-bold text-primary">Paket Dokumen LPJ & Cover Tahunan</h1>
                    <p class="mt-1 text-sm text-on-surface-variant">
                        Dokumen administratif pelaporan pertanggungjawaban tahunan dan perjanjian kerjasama antar desa
                    </p>
                </div>
            </div>

            <!-- Filters -->
            <AppCard class="p-4">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <SmartSelect v-model="selectedYear" label="Tahun Buku" :options="yearOptions" @update:model-value="apply" />
                    </div>
                </div>
            </AppCard>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <AppCard class="p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <AppIcon name="folder_zip" class="text-primary text-xl" />
                            <h2 class="text-sm font-bold text-on-surface">Download Bundle Laporan (ZIP)</h2>
                        </div>
                        <p class="mt-2 text-xs text-on-surface-variant">
                            Satu file ZIP berisi 8 laporan keuangan PDF + Buku Besar per akun + 4 dokumen LPJ untuk periode terpilih.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-outline-variant/20 flex justify-end">
                        <a :href="`/accounting/reports/bundle/pdf?year=${selectedYear.value}&month=12`">
                            <AppButton variant="primary" size="compact" icon="folder_zip">
                                Unduh Bundle ZIP
                            </AppButton>
                        </a>
                    </div>
                </AppCard>

                <AppCard class="p-5 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center gap-2">
                            <AppIcon name="table_view" class="text-primary text-xl" />
                            <h2 class="text-sm font-bold text-on-surface">Ekspor Excel (Auditor)</h2>
                        </div>
                        <p class="mt-2 text-xs text-on-surface-variant">
                            Satu file Excel (.xlsx) multi-sheet: Ringkasan, Neraca, Laba Rugi, Arus Kas, Neraca Saldo, Buku Besar, dan Portofolio Piutang.
                        </p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-outline-variant/20 flex justify-end">
                        <a :href="`/accounting/reports/bundle/xlsx?year=${selectedYear.value}&month=12`">
                            <AppButton variant="secondary" size="compact" icon="table_view">
                                Unduh Excel Auditor (.xlsx)
                            </AppButton>
                        </a>
                    </div>
                </AppCard>
            </div>

            <!-- Document Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <AppCard v-for="doc in docs" :key="doc.title" class="p-5 flex flex-col justify-between hover:shadow-md transition">
                    <div class="flex items-start gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <AppIcon :name="doc.icon" class="text-xl" />
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-on-surface">{{ doc.title }}</h3>
                            <p class="text-xs text-on-surface-variant mt-1">{{ doc.desc }}</p>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-outline-variant/20 flex justify-end">
                        <a :href="doc.href" target="_blank">
                            <AppButton size="compact" variant="outline" icon="picture_as_pdf">
                                Unduh / Cetak PDF
                            </AppButton>
                        </a>
                    </div>
                </AppCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
