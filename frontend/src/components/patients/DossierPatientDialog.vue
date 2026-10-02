<script setup>
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import DossierPatientTabsView from '@/components/patients/DossierPatientTabsView.vue';
import CreateConsultationDialog from '@/components/patients/CreateConsultationDialog.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import { usePatientDossier } from '@/composables/usePatientDossier';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ProgressSpinner from 'primevue/progressspinner';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    patientId: {
        type: Number,
        default: null,
        validator: (value) => value === null || typeof value === 'number'
    }
});

const emit = defineEmits(['update:visible', 'updated']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const loading = ref(false);
const rdvFormRef = ref(null);

const {
    patient,
    consultations,
    consultationsLoading,
    loadErrorMessage,
    fiches,
    rdvs,
    archiveFiles,
    paiements,
    factures,
    servicesCabinet,
    isReception,
    isMedecin,
    showConsultationsTab,
    dossierHiddenForMedecin,
    shouldHidePatientPhoneForMedecin,
    showRdvDialog,
    showConsultationDialog,
    showEditDialog,
    showAntecedentDialog,
    showAllergyDialog,
    savingAntecedent,
    savingAllergy,
    showPrintDialog,
    printIncludeEmpty,
    printSections,
    printSectionOptions,
    showActiveConsultWarn,
    activeConsultInfo,
    patientEditFormRef,
    loadAll,
    loadVisibilityPolicy,
    retryLoadPage,
    resetDialogs,
    handleSaveAntecedent,
    handleSaveAllergy,
    handleDeleteAntecedent,
    handleDeleteAllergy,
    handleCreatePortalAccount,
    handleResetPortalPassword,
    handleTogglePortalActive,
    handlePhotoSelected,
    handleRdvSaved,
    handleConsultationSaved,
    handlePatientSaved,
    handleFicheUpdated,
    handlePrintDossier,
    handlePrintFiche,
    submitPrint,
    loadDossier
} = usePatientDossier({
    patientId: computed(() => props.patientId),
    onUpdated: () => emit('updated')
});

const patientTitle = computed(() => {
    if (!patient.value?.id) return 'Dossier patient';
    return patient.value.fullname || `${patient.value.prenom ?? ''} ${patient.value.nom ?? ''}`.trim() || 'Dossier patient';
});

const loadDialogContent = async (patientId) => {
    if (!patientId) return;
    loading.value = true;
    loadErrorMessage.value = '';
    try {
        await loadVisibilityPolicy();
        await loadAll(patientId, { asPageLoad: true });
    } finally {
        loading.value = false;
    }
};

watch(
    () => [props.visible, props.patientId],
    async ([visible, patientId]) => {
        if (!visible) {
            resetDialogs();
            return;
        }
        if (!patientId) return;
        await loadDialogContent(patientId);
    },
    { immediate: true }
);

const handleRetry = async () => {
    loading.value = true;
    try {
        await retryLoadPage();
    } finally {
        loading.value = false;
    }
};
</script>

<template>
    <AppDialog
        v-model:visible="dialogVisible"
        :title="patientTitle"
        subtitle="Dossier complet — affichage en onglets"
        icon="pi pi-address-book"
        icon-tone="primary"
        size="full"
        maximizable
        :show-footer="false"
        :style="{ width: 'min(95vw, 1400px)' }"
        :content-style="{ maxHeight: '85vh', overflow: 'auto' }"
    >
        <div v-if="loading" class="dossier-state">
            <ProgressSpinner style="width: 40px; height: 40px" />
            <p class="dossier-state__text">Chargement du dossier…</p>
        </div>

        <div v-else-if="loadErrorMessage" class="dossier-state dossier-state--warning">
            <div class="dossier-state__icon">
                <i class="pi pi-exclamation-triangle"></i>
            </div>
            <h3 class="dossier-state__title">Chargement interrompu</h3>
            <p class="dossier-state__text">{{ loadErrorMessage }}</p>
            <Button class="mt-2" icon="pi pi-refresh" label="Réessayer" severity="warning" outlined @click="handleRetry" />
        </div>

        <div v-else-if="dossierHiddenForMedecin" class="dossier-state">
            <div class="dossier-state__icon">
                <i class="pi pi-lock"></i>
            </div>
            <h3 class="dossier-state__title">Dossier patient masqué</h3>
            <p class="dossier-state__text">L'accès au dossier patient est restreint pour votre profil.</p>
        </div>

        <DossierPatientTabsView
            v-else-if="patient?.id"
            :patient="patient"
            :patient-id="patientId"
            :fiches="fiches"
            :consultations="consultations"
            :consultations-loading="consultationsLoading"
            :rdvs="rdvs"
            :paiements="paiements"
            :factures="factures"
            :services-cabinet="servicesCabinet"
            :archive-files="archiveFiles"
            :is-reception="isReception"
            :is-medecin="isMedecin"
            :show-consultations-tab="showConsultationsTab"
            :hide-phone="shouldHidePatientPhoneForMedecin"
            hide-spine-actions
            @print-dossier="handlePrintDossier"
            @edit="showEditDialog = true"
            @new-rdv="showRdvDialog = true"
            @photo-selected="handlePhotoSelected"
            @add-antecedent="showAntecedentDialog = true"
            @add-allergy="showAllergyDialog = true"
            @delete-antecedent="handleDeleteAntecedent"
            @delete-allergy="handleDeleteAllergy"
            @create-portal-account="handleCreatePortalAccount"
            @reset-portal-password="handleResetPortalPassword"
            @toggle-portal-active="handleTogglePortalActive"
            @print-fiche="handlePrintFiche"
            @new-consultation="showConsultationDialog = true"
            @fiche-updated="handleFicheUpdated"
            @fiche-created="handleFicheUpdated"
            @refresh-archive="loadDossier(patientId)"
            @refresh="loadDossier(patientId)"
        />

        <AppDialog
            v-model:visible="showActiveConsultWarn"
            title="Consultation en cours"
            icon="fas fa-exclamation-triangle"
            icon-tone="warning"
            size="md"
            :show-footer="true"
            cancel-label="Compris"
            @cancel="showActiveConsultWarn = false"
        >
            <p class="text-surface-700 dark:text-surface-300 mb-4">Une consultation est déjà ouverte pour ce patient. Clôturez-la ou continuez-la avant d'en créer une nouvelle.</p>
            <p v-if="!activeConsultInfo.hasFiche" class="text-sm text-surface-600 dark:text-surface-400 mb-4">Si cette consultation a été ouverte par erreur, vous pouvez l annuler directement depuis ce dialogue.</p>
            <div v-if="activeConsultInfo.hasFiche" class="flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg mb-4">
                <i class="pi pi-info-circle text-surface-500"></i>
                <span class="text-sm text-surface-600 dark:text-surface-400"> Cette consultation est liée à une fiche : elle ne peut pas être supprimée. </span>
            </div>
        </AppDialog>

        <CreateConsultationDialog
            v-if="!isMedecin"
            v-model:visible="showConsultationDialog"
            :patient="patient"
            :patient-id="patient?.id"
            @saved="handleConsultationSaved"
        />

        <AppDialog
            v-model:visible="showRdvDialog"
            title="Nouveau rendez-vous"
            :subtitle="patient?.fullname || patient?.nom || 'Patient'"
            icon="fas fa-calendar-plus"
            icon-tone="info"
            size="lg"
            :loading="Boolean(rdvFormRef?.loading)"
            @cancel="showRdvDialog = false"
        >
            <FormRendezVous
                ref="rdvFormRef"
                hide-actions
                :patient="patient"
                :patient-id="patient?.id"
                @saved="handleRdvSaved"
                @cancel="showRdvDialog = false"
            />
            <template #footer>
                <div class="flex justify-end gap-2 w-full" data-tour="patients-form-rdv.actions">
                    <Button type="button" label="Annuler" severity="secondary" text class="rounded-xl px-5" :disabled="Boolean(rdvFormRef?.loading)" @click="showRdvDialog = false" />
                    <Button type="button" label="Créer" icon="pi pi-check" class="rounded-xl px-5" :loading="Boolean(rdvFormRef?.loading)" @click="rdvFormRef?.submit()" />
                </div>
            </template>
        </AppDialog>

        <AppDialog
            v-model:visible="showEditDialog"
            title="Modifier le patient"
            subtitle="Mettez à jour les informations"
            icon="fas fa-user-edit"
            icon-tone="primary"
            size="lg"
            :loading="Boolean(patientEditFormRef?.loading)"
            @cancel="showEditDialog = false"
        >
            <FormPatient
                ref="patientEditFormRef"
                hide-actions
                :patient="patient"
                @saved="handlePatientSaved"
                @cancel="showEditDialog = false"
            />
            <template #footer>
                <div class="flex justify-end gap-2 w-full" data-tour="patients-form.actions">
                    <Button type="button" label="Annuler" severity="secondary" text class="rounded-xl px-5" :disabled="Boolean(patientEditFormRef?.loading)" @click="showEditDialog = false" />
                    <Button type="button" label="Mettre à jour" icon="pi pi-check" class="rounded-xl px-5" :loading="Boolean(patientEditFormRef?.loading)" @click="patientEditFormRef?.submit()" />
                </div>
            </template>
        </AppDialog>

        <AntecedentDialogForm v-model="showAntecedentDialog" :loading="savingAntecedent" @save="handleSaveAntecedent" />

        <AllergyDialogForm v-model="showAllergyDialog" :loading="savingAllergy" @save="handleSaveAllergy" />

        <AppDialog
            v-model:visible="showPrintDialog"
            title="Impression fiche"
            subtitle="Choisir les sections a imprimer"
            icon="pi pi-print"
            icon-tone="primary"
            size="sm"
            cancel-label="Annuler"
            confirm-label="Imprimer"
            confirm-icon="pi pi-print"
            :confirm-disabled="!printSections.length"
            @cancel="showPrintDialog = false"
            @confirm="submitPrint"
        >
            <div class="space-y-5">
                <div class="space-y-3">
                    <div v-for="item in printSectionOptions" :key="item.key" class="flex items-center gap-3">
                        <Checkbox :inputId="`dialog-print-${item.key}`" :value="item.key" v-model="printSections" />
                        <label :for="`dialog-print-${item.key}`" class="text-sm text-surface-700 dark:text-surface-300">
                            {{ item.label }}
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Checkbox inputId="dialog-print-empty" v-model="printIncludeEmpty" binary />
                    <label for="dialog-print-empty" class="text-sm text-surface-700 dark:text-surface-300"> Imprimer les champs vides </label>
                </div>
            </div>
        </AppDialog>
    </AppDialog>
</template>
