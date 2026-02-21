<script setup>
import ActionsComposants from '@/components/ActionsComposants.vue';
import { computed } from 'vue';

const props = defineProps({
    patient: {
        type: Object,
        required: true
    },
    compact: {
        type: Boolean,
        default: false
    },
    consultationLoading: {
        type: Boolean,
        default: false
    },
    hideConsultation: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['new-rdv', 'new-consultation', 'open-dossier', 'edit']);

const handleNewRdv = () => emit('new-rdv', props.patient);
const handleNewConsultation = () => emit('new-consultation', props.patient);
const handleOpenDossier = () => emit('open-dossier', props.patient);
const handleEdit = () => emit('edit', props.patient);

const actions = computed(() => {
    const base = [];

    if (!props.hideConsultation) {
        base.push({
            id: 'consultation',
            label: 'Consultation',
            icon: 'fas fa-stethoscope',
            severity: 'success',
            command: handleNewConsultation,
            loading: props.consultationLoading
        });
    }

    base.push(
        {
            id: 'rdv',
            label: 'Nouveau RDV',
            icon: 'fas fa-calendar',
            severity: 'warn',
            command: handleNewRdv
        },
        {
            id: 'dossier',
            label: 'Dossier',
            icon: 'fas fa-folder-open',
            severity: 'info',
            command: handleOpenDossier
        },
        {
            id: 'edit',
            label: 'Modifier',
            icon: 'fas fa-pencil-alt',
            severity: 'secondary',
            command: handleEdit
        }
    );

    return base;
});
</script>

<template>
    <ActionsComposants :actions="actions" :show-labels="!compact" dropdown-label="Actions"
        dropdown-icon="pi pi-ellipsis-v" dropdown-severity="info" />
</template>
