<script setup>
import AppButton from '../AppButton.vue';

const props = defineProps({
    block: { type: Object, required: true },
});

const emit = defineEmits(['submit']);

function onClick() {
    if (props.block.url) {
        window.open(props.block.url, props.block.target || '_blank', 'noopener,noreferrer');
        return;
    }
    if (props.block.value) emit('submit', props.block.value);
}
</script>

<template>
    <div class="flex">
        <AppButton
            size="compact"
            :variant="block.url ? 'outline' : 'primary'"
            :icon="block.icon || (block.url ? 'open_in_new' : 'check')"
            class="w-full !justify-start"
            @click="onClick"
        >
            {{ block.label }}
        </AppButton>
    </div>
</template>