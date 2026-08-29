<template>
    <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <!-- Header -->
        <div class="mb-6 md:mb-8">
            <div class="mb-6">
                <div class="inline-flex items-center gap-3 mb-4 p-3 rounded-2xl bg-surface-0/80 dark:bg-surface-800/80 backdrop-blur-sm border border-surface-200/50 dark:border-surface-700/50">
                    <div class="p-2.5 rounded-xl bg-gradient-to-br from-primary-500 to-primary-600">
                        <i class="pi pi-address-book text-white text-xl"></i>
                    </div>
                    <div>
                        <h1 class="text-2xl md:text-3xl font-bold text-surface-900 dark:text-surface-50">Dossier Patient</h1>
                        <p class="text-sm text-surface-600 dark:text-surface-300">Informations complètes et historique médical</p>
                    </div>
                </div>
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="text-sm" />
            </div>
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <Button icon="pi pi-arrow-left" label="Retour à la liste" severity="secondary" outlined @click="goBackToList" />
                </div>
                <div class="flex items-center gap-2" data-tour="patients-dossier.selector">
                    <Select
                        v-model="selectedPatientId"
                        :options="patientOptions"
                        optionLabel="label"
                        optionValue="value"
                        placeholder="Sélectionner un patient"
                        filter
                        showClear
                        :filterFields="['label', 'phone', 'searchText']"
                        class="w-72"
                        :loading="patientOptionsLoading || dossierLoading"
                        @filter="handlePatientFilter"
                        @update:modelValue="handlePatientSelect"
                    >
                        <template #option="{ option }">
                            <div class="flex flex-col">
                                <span class="font-medium">{{ option.label }}</span>
                                <small class="text-surface-500 dark:text-surface-400">{{ option.phone || 'Téléphone non renseigné' }}</small>
                            </div>
                        </template>
                    </Select>
                </div>
            </div>
        </div>

        <div v-if="dossierLoading" class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 p-12 flex flex-col items-center justify-center gap-3">
            <ProgressSpinner style="width: 48px; height: 48px" />
            <p class="text-sm text-surface-500 dark:text-surface-400">Chargement du dossier…</p>
        </div>

        <div v-else-if="!hasPatientSelection" class="rounded-2xl border border-dashed border-surface-200/70 dark:border-surface-700/70 bg-surface-0 dark:bg-surface-800/80 p-12 text-center">
            <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gradient-to-br from-surface-100 to-surface-200 dark:from-surface-800 dark:to-surface-700">
                <i class="pi pi-user text-3xl text-surface-400"></i>
            </div>
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Aucune sélection</h3>
            <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                Sélectionnez un patient dans la liste ci-dessus pour afficher son dossier.
            </p>
        </div>

        <div v-else-if="loadErrorMessage" class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 text-center dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="mx-auto mb-3 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <h3 class="mt-3 text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</h3>
            <p class="mt-2 text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            <Button class="mt-4" icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoadPage" />
        </div>

        <div v-else-if="dossierHiddenForMedecin" class="rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 p-8 text-center">
            <i class="pi pi-lock text-3xl text-surface-400"></i>
            <h3 class="mt-3 text-lg font-semibold text-surface-900 dark:text-surface-100">Dossier patient masqué</h3>
            <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">L'accès au dossier patient est restreint pour votre profil.</p>
        </div>

        <div v-else-if="layoutMode === 'tabs'" class="space-y-6">
            <DossierPatientTabsView
                :patient="patient"
                :patient-id="props.patientId"
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
                @edit="() => (showEditDialog = true)"
                @new-rdv="() => (showRdvDialog = true)"
                @photo-selected="handlePhotoSelected"
                @add-antecedent="() => (showAntecedentDialog = true)"
                @add-allergy="() => (showAllergyDialog = true)"
                @delete-antecedent="handleDeleteAntecedent"
                @delete-allergy="handleDeleteAllergy"
                @create-portal-account="handleCreatePortalAccount"
                @reset-portal-password="handleResetPortalPassword"
                @toggle-portal-active="handleTogglePortalActive"
                @print-fiche="handlePrintFiche"
                @new-consultation="() => (showConsultationDialog = true)"
                @fiche-updated="handleFicheUpdated"
                @fiche-created="handleFicheUpdated"
                @refresh-archive="loadDossier(props.patientId)"
                @refresh="loadDossier(props.patientId)"
            />
        </div>

        <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Colonne de gauche : Infos patient -->
            <div class="lg:col-span-1 space-y-6">
                <div data-tour="patients-dossier.info-card">
                    <DossierPatientInfoCard
                        :patient="patient"
                        :hide-phone="shouldHidePatientPhoneForMedecin"
                        @print-dossier="handlePrintDossier"
                        @edit="() => (showEditDialog = true)"
                        @new-rdv="() => (showRdvDialog = true)"
                        @photo-selected="handlePhotoSelected"
                        @add-antecedent="() => (showAntecedentDialog = true)"
                        @add-allergy="() => (showAllergyDialog = true)"
                        @delete-antecedent="handleDeleteAntecedent"
                        @delete-allergy="handleDeleteAllergy"
                        @create-portal-account="handleCreatePortalAccount"
                        @reset-portal-password="handleResetPortalPassword"
                        @toggle-portal-active="handleTogglePortalActive"
                    />
                </div>

                <!-- Statistiques rapides -->
                <!-- <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
                    <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100 flex items-center gap-2">
                            <i class="pi pi-chart-bar text-primary-500"></i>
                            Statistiques
                        </h3>
                    </div>
                    <div class="p-5 grid grid-cols-2 gap-4">
                        <div class="text-center p-4 rounded-xl bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 border border-blue-200/50 dark:border-blue-800/50">
                            <div class="text-2xl font-bold text-blue-900 dark:text-blue-100">{{ patient.stats.fiches }}</div>
                            <div class="text-sm text-blue-700 dark:text-blue-300 mt-1">Fiches</div>
                        </div>
                        <div class="text-center p-4 rounded-xl bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20 border border-green-200/50 dark:border-green-800/50">
                            <div class="text-2xl font-bold text-green-900 dark:text-green-100">{{ patient.stats.rdv }}</div>
                            <div class="text-sm text-green-700 dark:text-green-300 mt-1">RDV</div>
                        </div>
                        <div class="text-center p-4 rounded-xl bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 border border-amber-200/50 dark:border-amber-800/50">
                            <div class="text-2xl font-bold text-amber-900 dark:text-amber-100">{{ patient.stats.hospitalisations }}</div>
                            <div class="text-sm text-amber-700 dark:text-amber-300 mt-1">Hospitalisations</div>
                        </div>
                        <div class="text-center p-4 rounded-xl bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 border border-purple-200/50 dark:border-purple-800/50">
                            <div class="text-2xl font-bold text-purple-900 dark:text-purple-100">{{ patient.stats.urgences }}</div>
                            <div class="text-sm text-purple-700 dark:text-purple-300 mt-1">Urgences</div>
                        </div>
                    </div>
                </div> -->
            </div>

            <!-- Colonne centrale : Fiches médicales -->
            <div class="lg:col-span-2 space-y-6">
                <div data-tour="patients-dossier.medical">
                    <ListePatientConsultations
                        v-if="isReception"
                        :consultations="consultations"
                        :loading="consultationsLoading"
                    />
                    <FichesMedicalesSection
                        v-else
                        :fiches="fiches"
                        :patient-id="props.patientId"
                        :patient-age="patientAge"
                        :can-create-consultation="!isMedecin"
                        @print-fiche="handlePrintFiche"
                        @new-consultation="() => (showConsultationDialog = true)"
                        @fiche-updated="handleFicheUpdated"
                        @fiche-created="handleFicheUpdated"
                    />
                </div>
                <div data-tour="patients-dossier.finance">
                    <PatientActiviteFinancesSection
                        :rdvs="rdvs"
                        :paiements="paiements"
                        :factures="factures"
                        :consultations="consultations"
                        :show-consultations="showConsultationsTab"
                        @refresh="loadDossier(props.patientId)"
                    />
                </div>
                    <!-- Colonne de gauche, après DossierPatientInfoCard -->
                    <div class="mt-6" data-tour="patients-dossier.archive-files">
                        <ArchiveFilesSection
                            :patient-id="props.patientId"
                            :files="archiveFiles"
                            @refresh="loadDossier(props.patientId)"
                        />
                    </div>
            </div>
        </div>

        <Button
            v-if="hasPatientSelection && !dossierLoading && !loadErrorMessage && !dossierHiddenForMedecin"
            data-tour="patients-dossier.layout-toggle"
            :icon="layoutMode === 'tabs' ? 'pi pi-list' : 'pi pi-th-large'"
            rounded
            class="!fixed bottom-6 right-6 z-50 shadow-lg !w-14 !h-14 bg-gradient-to-r from-primary-500 to-primary-600 border-0"
            :aria-label="layoutMode === 'tabs' ? 'Affichage classique' : 'Affichage en onglets'"
            v-tooltip.left="layoutMode === 'tabs' ? 'Affichage classique' : 'Affichage en onglets'"
            @click="toggleLayoutMode"
        />

        <Dialog v-model:visible="showActiveConsultWarn" modal :style="{ width: '35rem' }" :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }">
            <div class="p-6" data-tour="patients-dossier.dialog.active-warning">
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

                <div v-if="activeConsultInfo.hasFiche"
                    class="flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg mb-4">
                    <i class="pi pi-info-circle text-surface-500"></i>
                    <span class="text-sm text-surface-600 dark:text-surface-400">
                        Cette consultation est liée à une fiche : elle ne peut pas être supprimée.
                    </span>
                </div>

                <div class="flex justify-end gap-2">
                    <Button label="Compris" severity="secondary" @click="showActiveConsultWarn = false"
                        class="rounded-xl px-5" />
                </div>
            </div>
        </Dialog>

        <Dialog v-if="!isMedecin" v-model:visible="showConsultationDialog" modal :style="{ width: '50rem' }" :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }">
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
            <div data-tour="patients-dossier.dialog.consultation">
                <FormCreateConsultation :patient="patient" :patient-id="patient?.id" @saved="handleConsultationSaved" @cancel="showConsultationDialog = false" />
            </div>
        </Dialog>

        <Dialog v-model:visible="showRdvDialog" modal :style="{ width: '45rem' }" :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }">
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
            <div data-tour="patients-dossier.dialog.rdv">
                <FormRendezVous :patient="patient" :patient-id="patient?.id" @saved="handleRdvSaved" @cancel="showRdvDialog = false" />
            </div>
        </Dialog>

        <Dialog v-model:visible="showEditDialog" modal :style="{ width: '45rem' }" :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: ({ props }) => ({
                class: [
                    'px-6 py-4 border-b border-surface-200 dark:border-surface-700',
                    'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800'
                ]
            }),
            content: 'p-0 mt-4'
        }">
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
            <div data-tour="patients-dossier.dialog.edit">
                <FormPatient ref="patientEditFormRef" :patient="patient" @saved="handlePatientSaved" @cancel="showEditDialog = false" class="mt-2" />
            </div>
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

        <Dialog v-model:visible="showPrintDialog" modal :style="{ width: '32rem' }" :pt="{
            root: 'rounded-2xl overflow-hidden',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0'
        }">
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
            <div class="p-6 space-y-5" data-tour="patients-dossier.dialog.print">
                <div class="space-y-3">
                    <div v-for="item in printSectionOptions" :key="item.key" class="flex items-center gap-3">
                        <Checkbox
                            :inputId="`print-${item.key}`"
                            :value="item.key"
                            v-model="printSections"
                        />
                        <label :for="`print-${item.key}`" class="text-sm text-surface-700 dark:text-surface-300">
                            {{ item.label }}
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <Checkbox inputId="print-empty" v-model="printIncludeEmpty" binary />
                    <label for="print-empty" class="text-sm text-surface-700 dark:text-surface-300">
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
    </section>
</template>

<script setup>
import { logAppError } from '@/utils/appLogger';

import AllergyDialogForm from '@/components/patients/AllergyDialogForm.vue';
import AntecedentDialogForm from '@/components/patients/AntecedentDialogForm.vue';
import ArchiveFilesSection from '@/components/patients/ArchiveFilesSection.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import DossierPatientTabsView from '@/components/patients/DossierPatientTabsView.vue';
import FichesMedicalesSection from '@/components/patients/FichesMedicalesSection.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import ListePatientConsultations from '@/components/patients/ListePatientConsultations.vue';
import PatientActiviteFinancesSection from '@/components/patients/PatientActiviteFinancesSection.vue';
import { useDossierLayout } from '@/composables/useDossierLayout';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { usePatientDossier } from '@/composables/usePatientDossier';
import {
    activatePatientsTourMock,
    deactivatePatientsTourMock,
    getPatientsTourMockPatientIdForScenario,
    resetPatientsTourMockData,
    resolvePatientsTourMockScenario
} from '@/services/patientsTourMock';
import { useAssurancesStore } from '@/stores/assurances';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import ProgressSpinner from 'primevue/progressspinner';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';

const props = defineProps({
    patientId: {
        type: Number,
        required: false,
        default: null,
        validator: (value) => value === null || typeof value === 'number'
    }
});

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [
    { label: 'Patients', to: '/patients' },
    { label: 'Dossier médical', to: '/dossier' }
];

const { layoutMode, toggleLayoutMode } = useDossierLayout();
const toast = useToast();
const router = useRouter();
const assurancesStore = useAssurancesStore();

let patientSearchTimeout = null;
let guidedTourPageState = null;
let guidedTourCleanupPromise = null;
const guidedTourDemoActive = ref(false);
const patientOptions = ref([]);
const patientOptionsLoading = ref(false);
const selectedPatientId = ref(null);
const ignorePatientSelectEvents = ref(false);

const {
    patientStore,
    token,
    patient,
    consultations,
    consultationsLoading,
    dossierLoading,
    loadErrorMessage,
    fiches,
    patientAge,
    rdvs,
    archiveFiles,
    paiements,
    factures,
    isReception,
    isMedecin,
    showConsultationsTab,
    dossierHiddenForMedecin,
    shouldHidePatientPhoneForMedecin,
    hasOpenDialogs,
    showRdvDialog,
    showConsultationDialog,
    showEditDialog,
    showAntecedentDialog,
    showAllergyDialog,
    savingAntecedent,
    savingAllergy,
    showPrintDialog,
    selectedFicheForPrint,
    printIncludeEmpty,
    printSections,
    printSectionOptions,
    showActiveConsultWarn,
    activeConsultInfo,
    patientEditFormRef,
    loadDossier,
    clearDossier,
    loadAll,
    retryLoadPage,
    loadVisibilityPolicy,
    resetDialogs: resetTourDialogs,
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
    submitPrint
} = usePatientDossier({
    patientId: computed(() => props.patientId),
    getPatientId: () => (
        guidedTourDemoActive.value
            ? selectedPatientId.value ?? props.patientId ?? null
            : null
    )
});

const isValidPatientId = (id) => id != null && Number.isFinite(Number(id)) && Number(id) > 0;

const currentPatientId = computed(() => {
    const candidate = guidedTourDemoActive.value
        ? selectedPatientId.value ?? props.patientId ?? patient.value?.id ?? null
        : props.patientId ?? null;
    return isValidPatientId(candidate) ? Number(candidate) : null;
});
const hasPatientSelection = computed(() => currentPatientId.value != null);

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const loadPatientOptions = async (query = '') => {
    patientOptionsLoading.value = true;
    try {
        const params = { page: 1, limit: 20, q: query };
        const response = isMedecin.value
            ? await patientStore.fetchPatientsByMedecin(token, params)
            : await patientStore.fetchPatients(token, params);
        const items = response?.items ?? [];
        const mapped = items.map((p) => ({
            label: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom,
            value: p.id,
            phone: p.telephone || p.phone || '',
            searchText: [p.fullname, `${p.prenom ?? ''} ${p.nom ?? ''}`.trim(), p.nom, p.telephone, p.phone].filter(Boolean).join(' ')
        }));
        const keepId = isValidPatientId(selectedPatientId.value)
            ? selectedPatientId.value
            : (isValidPatientId(props.patientId) ? props.patientId : null);
        patientOptions.value = mapped;
        if (keepId != null) {
            await ensureSelectedPatientOption(keepId);
        }
    } catch (error) {
        logAppError('Erreur lors du chargement des patients', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la liste des patients. si le problème persiste, contactez le support.', life: 3000 });
    } finally {
        patientOptionsLoading.value = false;
    }
};

const ensureSelectedPatientOption = async (patientId) => {
    if (!isValidPatientId(patientId)) return;
    const exists = patientOptions.value.some((opt) => opt.value === patientId);
    if (exists) return;
    try {
        const data = await patientStore.fetchPatientById(patientId);
        if (!data?.id) return;
        patientOptions.value = [
            {
                label: data.fullname || `${data.prenom ?? ''} ${data.nom ?? ''}`.trim() || data.nom,
                value: data.id,
                phone: data.telephone || data.phone || '',
                searchText: [data.fullname, `${data.prenom ?? ''} ${data.nom ?? ''}`.trim(), data.nom, data.telephone, data.phone].filter(Boolean).join(' ')
            },
            ...patientOptions.value
        ];
    } catch (error) {
        logAppError('Erreur lors du chargement du patient sélectionné', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger le patient sélectionné. si le problème persiste, contactez le support.', life: 3000 });
    }
};

const applySelectedPatientId = async (patientId) => {
    ignorePatientSelectEvents.value = true;
    try {
        const normalizedId = isValidPatientId(patientId) ? Number(patientId) : null;
        if (normalizedId != null) {
            await ensureSelectedPatientOption(normalizedId);
        }
        selectedPatientId.value = normalizedId;
        await nextTick();
    } finally {
        ignorePatientSelectEvents.value = false;
    }
};

const handlePatientFilter = (event) => {
    const query = event?.value ?? event?.query ?? '';
    if (patientSearchTimeout) clearTimeout(patientSearchTimeout);
    patientSearchTimeout = setTimeout(() => {
        loadPatientOptions(query);
    }, 250);
};

onMounted(async () => {
    await loadVisibilityPolicy();
    await assurancesStore.load(token).catch(() => []);
    const initialId = isValidPatientId(props.patientId) ? props.patientId : null;
    const dossierPromise = initialId != null
        ? loadAll(initialId, { asPageLoad: true })
        : null;
    await loadPatientOptions();
    if (initialId != null) {
        await applySelectedPatientId(initialId);
        await dossierPromise;
    } else {
        await applySelectedPatientId(null);
        clearDossier();
    }
});

onBeforeUnmount(() => {
    if (patientSearchTimeout) {
        clearTimeout(patientSearchTimeout);
    }
    deactivatePatientsTourMock();
    guidedTourDemoActive.value = false;
    resetTourDialogs();
});

watch(
    () => props.patientId,
    async (newId, oldId) => {
        if (guidedTourDemoActive.value) return;
        if (newId === oldId) return;
        if (!isValidPatientId(newId)) {
            await applySelectedPatientId(null);
            clearDossier();
            return;
        }
        await applySelectedPatientId(newId);
        await loadAll(newId, { asPageLoad: true });
    }
);

const handlePatientSelect = async (value) => {
    if (ignorePatientSelectEvents.value) return;

    if (guidedTourDemoActive.value) {
        if (!isValidPatientId(value)) {
            await applySelectedPatientId(null);
            clearDossier();
            return;
        }
        await applySelectedPatientId(value);
        loadAll(value);
        return;
    }

    if (!isValidPatientId(value)) {
        // Select peut émettre null si l'option n'est pas encore dans la liste : ne pas vider la route.
        if (isValidPatientId(props.patientId)) {
            const optionPresent = patientOptions.value.some((opt) => opt.value === props.patientId);
            if (!optionPresent || patientOptionsLoading.value) {
                await applySelectedPatientId(props.patientId);
                return;
            }
            router.push({ name: 'patients-dossier' });
            return;
        }
        await applySelectedPatientId(null);
        clearDossier();
        return;
    }

    if (value === props.patientId) return;
    router.push({ name: 'patients-dossier', params: { patientId: value } });
};

const capturePageState = () => ({
    patient: cloneValue(patient.value),
    consultations: cloneValue(consultations.value),
    patientOptions: cloneValue(patientOptions.value),
    selectedPatientId: selectedPatientId.value,
    selectedFicheForPrint: cloneValue(selectedFicheForPrint.value),
    printSections: cloneValue(printSections.value),
    printIncludeEmpty: printIncludeEmpty.value
});

const restorePageState = async (state) => {
    if (!state) return;

    patient.value = cloneValue(state.patient) || patientStore.normalizePatientDossier();
    consultations.value = cloneValue(state.consultations) || [];
    patientOptions.value = cloneValue(state.patientOptions) || [];
    selectedPatientId.value = state.selectedPatientId ?? null;
    selectedFicheForPrint.value = cloneValue(state.selectedFicheForPrint) || null;
    printSections.value = cloneValue(state.printSections) || [];
    printIncludeEmpty.value = Boolean(state.printIncludeEmpty);
    await nextTick();
};

const prepareGuidedTourDemo = async ({ taskId = 'overview', variantId = null } = {}) => {
    guidedTourPageState = capturePageState();
    const scenario = resolvePatientsTourMockScenario(taskId, variantId, 'static');
    const demoPatientId = getPatientsTourMockPatientIdForScenario(scenario);

    activatePatientsTourMock(scenario);
    resetPatientsTourMockData(scenario);
    guidedTourDemoActive.value = true;

    await loadPatientOptions();
    await applySelectedPatientId(demoPatientId);
    await loadAll(demoPatientId);
    await nextTick();
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive.value) {
        resetTourDialogs();
        return;
    }

    if (guidedTourCleanupPromise) {
        return guidedTourCleanupPromise;
    }

    guidedTourCleanupPromise = (async () => {
        resetTourDialogs();
        deactivatePatientsTourMock();
        guidedTourDemoActive.value = false;
        const stateToRestore = guidedTourPageState;
        guidedTourPageState = null;
        await restorePageState(stateToRestore);
    })().finally(() => {
        guidedTourCleanupPromise = null;
    });

    return guidedTourCleanupPromise;
};

const openTourEditDialog = async () => {
    if (!patient.value?.id) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    showEditDialog.value = true;
    await nextTick();
};

const switchPatientFormTab = async (tab = 'personal') => {
    patientEditFormRef.value?.switchTab?.(tab);
    await nextTick();
    await waitForTourUi();
};

const hasActiveInsuranceTab = () => (
    (assurancesStore.items || []).some((item) => item?.actif !== false)
);

const openTourRdvDialog = async () => {
    if (!patient.value?.id) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    showRdvDialog.value = true;
    await nextTick();
};

const openTourConsultationDialog = async () => {
    if (!patient.value?.id || isMedecin.value || isReception.value) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi(220);
    showConsultationDialog.value = true;
    await nextTick();
    await waitForTourUi(120);

    if (!showConsultationDialog.value) {
        showConsultationDialog.value = true;
        await nextTick();
    }
};

const openTourPrintDialog = async () => {
    const fiche = fiches.value[0] || null;
    if (!fiche) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    selectedFicheForPrint.value = cloneValue(fiche);
    printSections.value = printSectionOptions.map((item) => item.key);
    printIncludeEmpty.value = false;
    showPrintDialog.value = true;
    await nextTick();
};

const openTourDuplicateConsultationDialog = async (variantId = 'blocked-no-fiche') => {
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();

    activeConsultInfo.value = {
        hasActive: true,
        consultationId: 5001,
        hasFiche: variantId === 'blocked-with-fiche'
    };
    showActiveConsultWarn.value = true;
    await nextTick();
    await waitForTourUi();
};

useGuidedTour({
    routeName: 'patients-dossier',
    hasOpenDialogs: () => hasOpenDialogs.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => ({
        hasPatientContext: Boolean(currentPatientId.value),
        isMedecin: isMedecin.value,
        isReception: isReception.value,
        hasFiches: fiches.value.length > 0,
        hasInsuranceTab: hasActiveInsuranceTab(),
        hasInsuranceProfile: Boolean(patient.value?.insuranceProfile?.assurance),
        openEditPatientDialog: openTourEditDialog,
        openRdvDialog: openTourRdvDialog,
        openConsultationDialog: openTourConsultationDialog,
        openPrintDialog: openTourPrintDialog,
        openDuplicateConsultationDialog: openTourDuplicateConsultationDialog,
        switchPatientFormTab,
        closeAllDialogs: resetTourDialogs
    }),
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour du dossier patient.'
});

const goBackToList = () => {
    router.push({ name: 'patients-liste' });
};
</script>
