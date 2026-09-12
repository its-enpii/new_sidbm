<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppBadge from '../../../../Components/AppBadge.vue';
import AppButton from '../../../../Components/AppButton.vue';
import AppCard from '../../../../Components/AppCard.vue';
import AppCheckbox from '../../../../Components/AppCheckbox.vue';
import AppIcon from '../../../../Components/AppIcon.vue';
import AppInput from '../../../../Components/AppInput.vue';
import AppTextarea from '../../../../Components/AppTextarea.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenant: { type: Object, required: true },
    role: { type: Object, default: null },
    permissionGroups: { type: Array, default: () => [] },
    systemRoleCodes: { type: Array, default: () => [] },
});

const editing = Boolean(props.role);
const isLocked = Boolean(props.role?.is_locked);
const isSystem = Boolean(props.role?.is_system);
const codeReadonly = editing;

const baseAction = `/admin/tenants/${props.tenant.row_id}/roles`;

const form = useForm({
    name: props.role?.name || '',
    code: props.role?.code || '',
    description: props.role?.description || '',
    permissions: props.role?.permissions ? [...props.role.permissions] : [],
});

const totalPermissions = computed(() => props.permissionGroups.reduce((sum, group) => sum + group.permissions.length, 0));
const selectedCount = computed(() => form.permissions.length);

function isGroupAllSelected(group) {
    if (isLocked) return true;
    return group.permissions.every((permission) => form.permissions.includes(permission.key));
}

function toggleGroup(group) {
    if (isLocked) return;
    const groupKeys = group.permissions.map((permission) => permission.key);
    form.permissions = isGroupAllSelected(group)
        ? form.permissions.filter((key) => !groupKeys.includes(key))
        : [...form.permissions, ...groupKeys.filter((key) => !form.permissions.includes(key))];
}

function togglePermission(key) {
    if (isLocked) return;
    form.permissions = form.permissions.includes(key)
        ? form.permissions.filter((selected) => selected !== key)
        : [...form.permissions, key];
}

function selectAllPermissions() {
    if (isLocked) return;
    form.permissions = props.permissionGroups.flatMap((group) => group.permissions.map((permission) => permission.key));
}

function clearAllPermissions() {
    if (isLocked) return;
    form.permissions = [];
}

function submit() {
    if (isLocked) return;
    if (editing) {
        form.put(`${baseAction}/${props.role.row_id}`, { preserveScroll: true });
    } else {
        form.post(baseAction, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="editing ? `Role: ${role.name}` : 'Tambah Role Tenant'" />
    <AdminLayout>
        <div class="mx-auto max-w-5xl space-y-6">
            <header>
                <Link :href="baseAction" class="text-sm font-semibold text-primary">← Role Tenant {{ tenant.name }}</Link>
                <div class="mt-2 flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-primary sm:text-3xl">
                            {{ editing ? `Role: ${role.name}` : 'Tambah Role Kustom' }}
                        </h1>
                        <p class="mt-1 text-sm text-on-surface-variant">
                            Susun hak akses peran untuk staf tenant <strong>{{ tenant.code }}</strong> langsung dari panel platform.
                        </p>
                    </div>
                    <div v-if="editing" class="flex items-center gap-2">
                        <AppBadge v-if="isLocked" tone="primary">Terkunci (Admin)</AppBadge>
                        <AppBadge v-else-if="isSystem" tone="neutral">Bawaan Sistem</AppBadge>
                        <AppBadge v-else tone="info-soft">Kustom</AppBadge>
                    </div>
                </div>
            </header>

            <AppCard v-if="isLocked" bordered>
                <div class="flex items-start gap-3">
                    <AppIcon name="lock" tone="primary" />
                    <div class="space-y-1">
                        <p class="font-bold text-primary">Role Administrator Terkunci Penuh</p>
                        <p class="text-sm text-on-surface-variant">
                            Role Administrator memegang wewenang penuh (*) ke seluruh modul dan tidak dapat diubah dari panel platform.
                        </p>
                    </div>
                </div>
            </AppCard>

            <form class="space-y-6" @submit.prevent="submit">
                <AppCard>
                    <template #header>
                        <h2 class="font-bold text-primary">Identitas Role</h2>
                    </template>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput
                            v-model="form.name"
                            label="Nama Role"
                            placeholder="Contoh: Petugas Lapangan"
                            required
                            :readonly="isLocked"
                            :error="form.errors.name"
                        />
                        <AppInput
                            v-model="form.code"
                            label="Kode Role"
                            placeholder="Contoh: petugas_lapangan"
                            :required="!editing"
                            :readonly="isLocked || codeReadonly"
                            hint="Huruf kecil, angka, atau underscore. Tidak dapat diubah setelah dibuat."
                            :error="form.errors.code"
                        />
                        <div class="sm:col-span-2">
                            <AppTextarea
                                v-model="form.description"
                                label="Deskripsi / Catatan Peran"
                                placeholder="Jelaskan tanggung jawab atau wewenang peran ini..."
                                :rows="2"
                                :readonly="isLocked"
                                :error="form.errors.description"
                            />
                        </div>
                    </div>
                    <p v-if="!editing && systemRoleCodes.length" class="mt-3 text-xs text-on-surface-variant">
                        Kode sudah dipakai role sistem pada tenant ini: {{ systemRoleCodes.join(', ') }}.
                    </p>
                </AppCard>

                <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-lg font-bold text-primary">Matriks Hak Akses</h2>
                        <p class="text-xs text-on-surface-variant">
                            {{ isLocked ? 'Semua izin aktif otomatis untuk role admin.' : `${selectedCount} dari ${totalPermissions} izin terpilih.` }}
                        </p>
                    </div>
                    <div v-if="!isLocked" class="flex items-center gap-2">
                        <AppButton variant="secondary" size="compact" type="button" @click="selectAllPermissions">Pilih Semua</AppButton>
                        <AppButton variant="ghost" size="compact" type="button" @click="clearAllPermissions">Kosongkan</AppButton>
                    </div>
                </div>

                <AppCard v-for="group in permissionGroups" :key="group.category">
                    <template #header>
                        <div class="flex w-full items-center justify-between gap-4">
                            <div class="flex items-center gap-2">
                                <AppIcon :name="group.icon" class="text-xl text-primary" />
                                <span class="font-bold text-primary">{{ group.label }}</span>
                            </div>
                            <AppButton
                                v-if="!isLocked"
                                variant="ghost"
                                size="compact"
                                type="button"
                                class="text-xs"
                                @click="toggleGroup(group)"
                            >
                                {{ isGroupAllSelected(group) ? 'Batalkan Semua' : 'Pilih Semua' }}
                            </AppButton>
                        </div>
                    </template>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="permission in group.permissions"
                            :key="permission.key"
                            class="flex items-start gap-3 rounded-lg border border-outline-variant p-3 transition-colors hover:bg-surface-container-lowest"
                            :class="[isLocked ? 'opacity-80' : 'cursor-pointer']"
                            @click="togglePermission(permission.key)"
                        >
                            <AppCheckbox
                                :model-value="form.permissions.includes(permission.key)"
                                :disabled="isLocked"
                                @click.stop="togglePermission(permission.key)"
                            />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-on-surface">{{ permission.label }}</p>
                                <p class="text-xs text-on-surface-variant">{{ permission.description }}</p>
                                <code class="mt-0.5 inline-block font-mono text-[10px] text-outline">{{ permission.key }}</code>
                            </div>
                        </div>
                    </div>
                </AppCard>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <Link :href="baseAction">
                        <AppButton variant="secondary" type="button">Batal</AppButton>
                    </Link>
                    <AppButton v-if="!isLocked" type="submit" :loading="form.processing" icon="save">
                        {{ editing ? 'Simpan Perubahan Hak Akses' : 'Buat Role' }}
                    </AppButton>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>
