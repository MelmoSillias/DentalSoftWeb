<script setup>
import { ref, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import Button from 'primevue/button';

const props = defineProps({ visible: Boolean, eventId: [String, Number], loading: Boolean });
const emit = defineEmits(['delete', 'validate', 'hide', 'update:visible']);

const showDeleteConfirm = ref(false);
const showValidateConfirm = ref(false);
watch(
    () => props.visible,
    (visible) => {
        if (!visible) {
            showDeleteConfirm.value = false;
            showValidateConfirm.value = false;
        }
    }
);

function onDelete() {
    showDeleteConfirm.value = true;
}
function onValidate() {
    showValidateConfirm.value = true;
}

function confirmDelete() {
    if (props.loading) return;
    emit('delete', props.eventId);
}

function confirmValidate() {
    if (props.loading) return;
    emit('validate', props.eventId);
}

const onHide = () => {
    emit('hide');
    emit('update:visible', false);
};
</script>

<template>
    <div>
        <AppDialog :visible="visible" title="Actions" icon="pi pi-cog" icon-tone="neutral" size="sm" :show-footer="false" :dismissable-mask="false" @update:visible="(val) => !val && onHide()" @hide="onHide">
            <div class="flex flex-col gap-2">
                <Button label="Valider" icon="pi pi-check" severity="success" class="w-full" :disabled="loading" @click="onValidate" />
                <Button label="Supprimer" icon="pi pi-trash" severity="danger" class="w-full" :disabled="loading" @click="onDelete" />
            </div>
        </AppDialog>

        <AppDialog
            v-model:visible="showValidateConfirm"
            title="Confirmer la validation"
            icon="pi pi-check-circle"
            icon-tone="success"
            size="sm"
            cancel-label="Annuler"
            confirm-label="Valider"
            confirm-severity="success"
            :loading="loading"
            @cancel="showValidateConfirm = false"
            @confirm="confirmValidate"
        >
            <p>Voulez-vous valider cet événement ? Il sera marqué comme "Confirmé".</p>
        </AppDialog>

        <AppDialog
            v-model:visible="showDeleteConfirm"
            title="Confirmer la suppression"
            icon="pi pi-trash"
            icon-tone="danger"
            size="sm"
            cancel-label="Annuler"
            confirm-label="Supprimer"
            confirm-severity="danger"
            :loading="loading"
            @cancel="showDeleteConfirm = false"
            @confirm="confirmDelete"
        >
            <p>Voulez-vous vraiment supprimer cet événement ? Cette action est irréversible.</p>
        </AppDialog>
    </div>
</template>
