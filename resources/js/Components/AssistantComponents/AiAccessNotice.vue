<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AppBadge from '../AppBadge.vue';
import AppButton from '../AppButton.vue';
import AppCard from '../AppCard.vue';
import AppIcon from '../AppIcon.vue';
import { useCan } from '../../composables/useCan';

const page = usePage();
const { can } = useCan();

const isGated = computed(() => page.props.assistant?.gated === true);
const canManage = computed(() => can('settings.manage'));
const shouldShow = computed(() => isGated.value && canManage.value);

const form = useForm({});

function submitRequest() {
    form.post('/assistant/access-request', {
        preserveScroll: true,
    });
}
</script>

<template>
    <div v-if="shouldShow" class="mx-auto mb-4 max-w-7xl sm:mb-5">
        <AppCard :padded="false" class="border-primary/20 bg-primary-container/10 p-3.5 sm:p-4 lg:p-4.5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-3">
                    <AppIcon name="smart_toy" tone="primary" container-size="8" container-shape="pill" />
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="text-xs sm:text-sm font-bold text-primary">Fitur AI Belum Aktif</p>
                            <AppBadge tone="primary-soft">Langganan</AppBadge>
                        </div>
                        <p class="mt-0.5 text-xs text-on-surface-variant">
                            Fitur AI belum aktif untuk usaha ini. Ajukan langganan ke administrator Anda untuk mengaktifkan asisten AI cerdas.
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <AppButton
                        variant="primary"
                        size="compact"
                        icon="send"
                        class="text-xs sm:text-sm"
                        :loading="form.processing"
                        @click="submitRequest"
                    >
                        Ajukan Langganan
                    </AppButton>
                </div>
            </div>
        </AppCard>
    </div>
</template>
