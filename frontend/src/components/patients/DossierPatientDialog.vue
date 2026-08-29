<script setup>
import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import DossierPatientTabsView from '@/components/patients/DossierPatientTabsView.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import { usePatientDossier } from '@/composables/usePatientDossier';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
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
    return patient.value.fullname
        || `${patient.value.prenom ?? ''} ${patient.value.nom ?? ''}`.trim()
        || 'Dossier patient';
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
    <Dialog
        v-model:visible="dialogVisible"
        modal
        maximizable
        :style="{ width: '95vw', maxWidth: '1400px' }"
        :contentStyle="{ maxHeight: '85vh', overflow: 'auto' }"
        :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b border-surface-200 dark:border-surface-700',
            content: 'p-4 md:p-6'
        }"
    >
        <template #header>
            <div class="flex items-center gap-3 min-w-0">
                <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900/30 shrink-0">
                    <i class="pi pi-address-book text-primary-600 dark:text-primary-400"></i>
                </div>
                <div class="min-w-0">
                    <h4 class="m-0 text-surface-900 dark:text-surface-100 truncate">{{ patientTitle }}</h4>
                    <p class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">
                        Dossier complet — affichage en onglets
                    </p>
                </div>
            </div>
        </template>

        <div v-if="loading" class="flex flex-col items-center justify-center py-20 gap-3">
            <ProgressSpinner style="width: 48px; height: 48px" />
            <p class="text-sm text-surface-500">Chargement du dossier…</p>
        </div>

        <div
            v-else-if="loadErrorMessage"
            class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 text-center dark:border-amber-800/70 dark:bg-amber-950/20"
        >
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <h3 class="mt-3 text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</h3>
            <p class="mt-2 text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            <Button class="mt-4" icon="pi pi-refresh" label="Réessayer" severity="warning" @click="handleRetry" />
        </div>

        <div
            v-else-if="dossierHiddenForMedecin"
            class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 p-8 text-center"
        >
            <i class="pi pi-lock text-3xl text-surface-400"></i>
            <h3 class="mt-3 text-lg font-semibold text-surface-900 dark:text-surface-100">Dossier patient masqué</h3>
            <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                L'accès au dossier patient est restreint pour votre profil.
            </p>
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
            :archive-files="archiveFiles"
            :is-reception="isReception"
            :is-medecin="isMedecin"
            :show-consultations-tab="showConsultationsTab"
            :hide-phone="shouldHidePatientPhoneForMedecin"
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

        <Dialog
            v-model:visible="showActiveConsultWarn"
            modal
            :style="{ width: '35rem' }"
            :pt="{
                root: 'rounded-2xl overflow-hidden',
                header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
                content: 'p-0 mt-4'
            }"
        >
            <div class="p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="p-2 rounded-lg bg-amber-100 dark:bg-amber-900/30">
                        <i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-400"></i>
                    </div>
                    <h4 class="m-0 text-surface-900 dark:text-surface-100">Consultation en cours</h4>
                </div>
                <p class="text-surface-700 dark:text-surface-300 mb-4">
                    Une consultation est déjà ouverte pour ce patient. Clôturez-la ou continuez-la avant d'en créer une
                    nouvelle.
                </p>
                <p v-if="!activeConsultInfo.hasFiche" class="text-sm text-surface-600 dark:text-surface-400 mb-4">
                    Si cette consultation a été ouverte par erreur, vous pouvez l annuler directement depuis ce dialogue.
                </p>
                <div
                    v-if="activeConsultInfo.hasFiche"
                    class="flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg mb-4"
                >
                    <i class="pi pi-info-circle text-surface-500"></i>
                    <span class="text-sm text-surface-600 dark:text-surface-400">
                        Cette consultation est liée à une fiche : elle ne peut pas être supprimée.
                    </span>
                </div>
                <div class="flex justify-end gap-2">
                    <Button label="Compris" severity="secondary" class="rounded-xl px-5" @click="showActiveConsultWarn = false" />
                </div>
            </div>
        </Dialog>

        <Dialog
            v-if="!isMedecin"
            v-model:visible="showConsultationDialog"
            modal
            :style="{ width: '50rem' }"
            :pt="{
                root: 'rounded-2xl overflow-hidden',
                header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
                content: 'p-0 mt-4'
            }"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-green-100 dark:bg-green-900/30">
                        <i class="fas fa-stethoscope text-green-600 dark:text-green-400"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">Nouvelle consultation</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            {{ patient?.fullname || patient?.nom || 'Patient' }}
                        </p>
                    </div>
                </div>
            </template>
            <FormCreateConsultation
                :patient="patient"
                :patient-id="patient?.id"
                @saved="handleConsultationSaved"
                @cancel="showConsultationDialog = false"
            />
        </Dialog>

        <Dialog
            v-model:visible="showRdvDialog"
            modal
            :style="{ width: '45rem' }"
            :pt="{
                root: 'rounded-2xl overflow-hidden',
                header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
                content: 'p-0 mt-4'
            }"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                        <i class="fas fa-calendar-plus text-blue-600 dark:text-blue-400"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">Nouveau rendez-vous</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            {{ patient?.fullname || patient?.nom || 'Patient' }}
                        </p>
                    </div>
                </div>
            </template>
            <FormRendezVous
                :patient="patient"
                :patient-id="patient?.id"
                @saved="handleRdvSaved"
                @cancel="showRdvDialog = false"
            />
        </Dialog>

        <Dialog
            v-model:visible="showEditDialog"
            modal
            :style="{ width: '45rem' }"
            :pt="{
                root: 'rounded-2xl overflow-hidden',
                header: 'px-6 py-4 border-b border-surface-200 dark:border-surface-700 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800',
                content: 'p-0 mt-4'
            }"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900/30">
                        <i class="fas fa-user-edit text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">Modifier le patient</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Mettez à jour les informations</p>
                    </div>
                </div>
            </template>
            <FormPatient
                ref="patientEditFormRef"
                :patient="patient"
                class="mt-2"
                @saved="handlePatientSaved"
                @cancel="showEditDialog = false"
            />
        </Dialog>

        <AntecedentDialogForm
            v-model="showAntecedentDialog"
            :loading="savingAntecedent"
            @save="handleSaveAntecedent"
        />

        <AllergyDialogForm
            v-model="showAllergyDialog"
            :loading="savingAllergy"
            @save="handleSaveAllergy"
        />

        <Dialog
            v-model:visible="showPrintDialog"
            modal
            :style="{ width: '32rem' }"
            :pt="{
                root: 'rounded-2xl overflow-hidden',
                header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
                content: 'p-0'
            }"
        >
            <template #header>
                <div class="flex items-center gap-3">
                    <div class="p-2 rounded-lg bg-primary-100 dark:bg-primary-900/30">
                        <i class="pi pi-print text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">Impression fiche</h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Choisir les sections a imprimer</p>
                    </div>
                </div>
            </template>
            <div class="p-6 space-y-5">
                <div class="space-y-3">
                    <div v-for="item in printSectionOptions" :key="item.key" class="flex items-center gap-3">
                        <Checkbox
                            :inputId="`dialog-print-${item.key}`"
                            :value="item.key"
                            v-model="printSections"
                        />
                        <label :for="`dialog-print-${item.key}`" class="text-sm text-surface-700 dark:text-surface-300">
                            {{ item.label }}
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Checkbox inputId="dialog-print-empty" v-model="printIncludeEmpty" binary />
                    <label for="dialog-print-empty" class="text-sm text-surface-700 dark:text-surface-300">
                        Imprimer les champs vides
                    </label>
                </div>
            </div>
            <template #footer>
                <div class="flex items-center justify-end gap-2 px-6 pb-6">
                    <Button label="Annuler" severity="secondary" outlined @click="showPrintDialog = false" />
                    <Button label="Imprimer" icon="pi pi-print" :disabled="!printSections.length" @click="submitPrint" />
                </div>
            </template>
        </Dialog>
    </Dialog>
</template>
