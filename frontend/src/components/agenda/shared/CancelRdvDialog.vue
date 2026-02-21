<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
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
watch(() => props.visible, (v) => (localVisible.value = v), { immediate: true });
watch(localVisible, (v) => emit('update:visible', v));

const close = () => (localVisible.value = false);
const confirm = () => {
    emit('confirm', { id: props.rdv?.id });
    close();
};
</script>

<template>
    <Dialog v-model:visible="localVisible" modal header="Annuler le rendez-vous" style="width: 380px" >
        <div class="flex flex-col gap-3">
            <p class="text-sm text-surface-700">Confirmer l'annulation de ce rendez-vous ?</p>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button label="Retour" text severity="secondary" @click="close" />
                <Button label="Annuler le rendez-vous" icon="pi pi-times" severity="danger" :loading="loading" @click="confirm" />
            </div>
        </template>
    </Dialog>
</template>

