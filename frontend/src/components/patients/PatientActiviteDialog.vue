<script setup>
import AppDialog from '@/components/layout/AppDialog.vue';
import PatientActiviteFinancesSection from '@/components/patients/PatientActiviteFinancesSection.vue';
import { fetchPatientConsultations, fetchPatientDossier } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';
import ProgressSpinner from 'primevue/progressspinner';
import { useToast } from 'primevue/usetoast';
import { computed, ref, watch } from 'vue';

const visible = defineModel('visible', { type: Boolean, default: false });

const props = defineProps({
    patientId: { type: [Number, String], default: null },
    patientName: { type: String, default: '' }
});

const auth = useAuthStore();
const toast = useToast();
const token = () => auth.token || localStorage.getItem('token');

const loading = ref(false);
const dossier = ref(null);
const consultations = ref([]);

const showConsultations = computed(() => {
    const roles = auth.user?.roles || [];
    return roles.includes('ROLE_ADMIN') || roles.includes('ROLE_MEDECIN');
});

const patientLabel = computed(() => {
    if (props.patientName) return props.patientName;
    const patient = dossier.value;
    return `${patient?.nom || ''} ${patient?.prenom || ''}`.trim() || 'Patient';
});

const load = async () => {
    if (!props.patientId) return;
    loading.value = true;
    try {
        dossier.value = await fetchPatientDossier(props.patientId, token());
        consultations.value = showConsultations.value ? await fetchPatientConsultations(props.patientId, token()) : [];
    } catch (error) {
        logAppError('Activité patient', error);
        dossier.value = null;
        consultations.value = [];
        toast.add({ severity: 'error', summary: 'Activité', detail: "Impossible de charger l'activité du patient.", life: 3000 });
    } finally {
        loading.value = false;
    }
};

watch(
    () => [visible.value, props.patientId],
    ([open]) => {
        if (open) load();
    }
);
</script>

<template>
    <AppDialog v-model:visible="visible" title="Activité du patient" :subtitle="patientLabel" icon="pi pi-chart-line" icon-tone="primary" size="full" cancel-label="Fermer" @cancel="visible = false">
        <div v-if="loading" class="flex items-center justify-center py-16">
            <ProgressSpinner style="width: 40px; height: 40px" />
        </div>
        <PatientActiviteFinancesSection
            v-else-if="dossier"
            :rdvs="dossier.rdvs || []"
            :paiements="dossier.paiements || []"
            :factures="dossier.factures || []"
            :consultations="consultations"
            :services-cabinet="dossier.servicesCabinet || []"
            :fiches="dossier.fiches || []"
            :patient-id="patientId"
            :patient-name="patientLabel"
            :show-consultations="showConsultations"
            @refresh="load"
        />
    </AppDialog>
</template>
