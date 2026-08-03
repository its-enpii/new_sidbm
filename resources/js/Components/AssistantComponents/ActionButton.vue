<script setup>
import AppButton from '../AppButton.vue';

const props = defineProps({
    block: { type: Object, required: true },
});

const emit = defineEmits(['submit']);

function onClick() {
    if (props.block.value) emit('submit', props.block.value);
}
</script>

<template>
    <div class="flex">
        <AppButton
            size="compact"
            variant="primary"
            icon="check"
            class="w-full !justify-start"
            @click="onClick"
        >
            {{ block.label }}
        </AppButton>
    </div>
</template>
