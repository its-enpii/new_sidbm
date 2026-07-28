<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppButton from '../../../Components/AppButton.vue';
import AppCard from '../../../Components/AppCard.vue';
import AppInput from '../../../Components/AppInput.vue';
import AppTextarea from '../../../Components/AppTextarea.vue';
import SmartSelect from '../../../Components/SmartSelect.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    tenants: { type: Array, required: true },
    subscriptions: { type: Array, default: () => [] },
    selected_tenant_id: { type: Number, default: null },
});

const form = useForm({
    tenant_id: props.selected_tenant_id || '',
    subscription_id: '',
    amount: '',
    currency: 'IDR',
    due_at: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 10),
    description: '',
    notes: '',
    status: 'issued',
});

const statusOptions = [
    { value: 'issued', label: 'Terbitkan sekarang' },
    { value: 'draft', label: 'Draft' },
];

function submit() {
    form.post('/admin/invoices');
}
</script>

<template>
    <Head title="Buat Invoice" />
    <AdminLayout>
        <div class="mx-auto max-w-3xl space-y-6">
            <header>
                <Link href="/admin/invoices" class="text-sm font-semibold text-primary">← Daftar invoice</Link>
                <h1 class="mt-3 text-2xl font-bold text-primary">Buat Invoice</h1>
            </header>

            <AppCard>
                <form class="space-y-4" @submit.prevent="submit">
                    <SmartSelect
                        v-model="form.tenant_id"
                        label="Tenant"
                        :options="tenants.map((t) => ({ value: t.row_id, label: `${t.name} (${t.code})` }))"
                        searchable
                        required
                        :error="form.errors.tenant_id"
                    />
                    <SmartSelect
                        v-model="form.subscription_id"
                        label="Langganan (opsional)"
                        :options="[{ value: '', label: '— Tanpa langganan —' }, ...subscriptions.filter((s) => !form.tenant_id || s.tenant_id === form.tenant_id).map((s) => ({ value: s.row_id, label: `#${s.row_id} · ${s.plan?.name || 'plan'} · ${s.status}` }))]"
                        :error="form.errors.subscription_id"
                    />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <AppInput v-model="form.amount" label="Nominal" type="number" step="0.01" min="0.01" required :error="form.errors.amount" />
                        <AppInput v-model="form.due_at" label="Jatuh tempo" type="date" :error="form.errors.due_at" />
                        <AppInput v-model="form.currency" label="Mata uang" :error="form.errors.currency" />
                        <SmartSelect v-model="form.status" label="Status" :options="statusOptions" :error="form.errors.status" />
                    </div>
                    <AppInput v-model="form.description" label="Deskripsi" :error="form.errors.description" />
                    <AppTextarea v-model="form.notes" label="Catatan" :error="form.errors.notes" />
                    <div class="flex justify-end gap-3">
                        <Link href="/admin/invoices"><AppButton variant="secondary">Batal</AppButton></Link>
                        <AppButton type="submit" :loading="form.processing" icon="save">Simpan</AppButton>
                    </div>
                </form>
            </AppCard>
        </div>
    </AdminLayout>
</template>
