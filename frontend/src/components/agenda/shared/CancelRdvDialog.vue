<script setup>
import AppDialog from '@/components/layout/AppDialog.vue';
import { ref, watch } from 'vue';

const props = defineProps({
    visible: Boolean,
    rdv: {
        type: Object,
        default: null
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:visible', 'confirm']);

const localVisible = ref(props.visible);
watch(
    () => props.visible,
    (v) => (localVisible.value = v),
    { immediate: true }
);
watch(localVisible, (v) => emit('update:visible', v));

const close = () => (localVisible.value = false);
const confirm = () => {
    emit('confirm', { id: props.rdv?.id });
    close();
};
</script>

<template>
    <AppDialog
        v-model:visible="localVisible"
        title="Annuler le rendez-vous"
        icon="pi pi-times-circle"
        icon-tone="danger"
        size="sm"
        :loading="loading"
        cancel-label="Retour"
        confirm-label="Annuler le rendez-vous"
        confirm-icon="pi pi-times"
        confirm-severity="danger"
        @cancel="close"
        @confirm="confirm"
    >
        <div class="flex flex-col gap-3">
            <p class="text-sm text-surface-700">Confirmer l'annulation de ce rendez-vous ?</p>
        </div>
    </AppDialog>
</template>
