<script setup>
import Button from 'primevue/button';

defineProps({
    cancelLabel: {
        type: String,
        default: 'Annuler'
    },
    confirmLabel: {
        type: String,
        default: null
    },
    confirmIcon: {
        type: String,
        default: 'pi pi-check'
    },
    confirmSeverity: {
        type: String,
        default: 'primary'
    },
    loading: {
        type: Boolean,
        default: false
    },
    confirmDisabled: {
        type: Boolean,
        default: false
    },
    showCancel: {
        type: Boolean,
        default: true
    },
    showConfirm: {
        type: Boolean,
        default: true
    }
});

defineEmits(['cancel', 'confirm']);
</script>

<template>
    <div class="dialog-footer flex flex-wrap items-center justify-between gap-3 w-full">
        <div v-if="$slots.start" class="flex items-center gap-2">
            <slot name="start" />
        </div>
        <div class="flex items-center justify-end gap-2 ml-auto">
            <slot name="actions">
                <Button
                    v-if="showCancel"
                    type="button"
                    :label="cancelLabel"
                    severity="secondary"
                    text
                    class="rounded-xl px-5"
                    :disabled="loading"
                    @click="$emit('cancel')"
                />
                <Button
                    v-if="showConfirm && confirmLabel"
                    type="button"
                    :label="confirmLabel"
                    :icon="confirmIcon"
                    :severity="confirmSeverity"
                    :loading="loading"
                    :disabled="confirmDisabled || loading"
                    class="rounded-xl px-5"
                    @click="$emit('confirm')"
                />
            </slot>
        </div>
    </div>
</template>
