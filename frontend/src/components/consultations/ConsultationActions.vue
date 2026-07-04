<script setup>
import ActionsComposants from '@/components/ActionsComposants.vue';
import { computed } from 'vue';

const props = defineProps({
    consultation: {
        type: Object,
        required: true
    },
    isAdmin: {
        type: Boolean,
        default: false
    },
    canModifyInvoiceByRole: {
        type: Boolean,
        default: false
    },
    cancelLoading: {
        type: Boolean,
        default: false
    },
    factureLoading: {
        type: Boolean,
        default: false
    },
    detailsLoading: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['cancel', 'open-dossier', 'edit-facture', 'view-details']);

const canCancel = computed(() => props.consultation?.state === 0);
const canOpenDossier = computed(() => props.consultation?.state !== 0 && Boolean(props.consultation?.patientId));
const canEditFacture = computed(
    () =>
        props.canModifyInvoiceByRole &&
        props.consultation?.state === 1 &&
        props.consultation?.factModifiable === true
);

const actions = computed(() => {
    const base = [];
    if (canCancel.value) {
        base.push({
            id: 'cancel',
            label: 'Annuler',
            icon: 'pi pi-times',
            severity: 'danger',
            command: () => emit('cancel', props.consultation),
            loading: props.cancelLoading
        });
    }

    base.push({
        id: 'details',
        label: 'Détails',
        icon: 'pi pi-eye',
        severity: 'info',
        command: () => emit('view-details', props.consultation),
        loading: props.detailsLoading
    });

    if (canOpenDossier.value) {
        base.push({
            id: 'dossier',
            label: 'Dossier patient',
            icon: 'fas fa-folder-open',
            severity: 'secondary',
            command: () => emit('open-dossier', props.consultation)
        });
    }

    if (canEditFacture.value) {
        base.push({
            id: 'facture',
            label: 'Modifier facture',
            icon: 'pi pi-file-edit',
            severity: 'warning',
            outlined: true,
            command: () => emit('edit-facture', props.consultation),
            loading: props.factureLoading
        });
    }

    return base;
});
</script>

<template>
    <ActionsComposants :actions="actions" :show-labels="false" dropdown-label="Actions" dropdown-icon="pi pi-ellipsis-v"
        dropdown-severity="secondary" size="small" />
</template>
