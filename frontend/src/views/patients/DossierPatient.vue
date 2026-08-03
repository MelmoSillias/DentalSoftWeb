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
                        :filterFields="['label', 'phone', 'searchText']"
                        class="w-72"
                        :loading="patientOptionsLoading"
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

        <div v-if="loadErrorMessage" class="rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 text-center dark:border-amber-800/70 dark:bg-amber-950/20">
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
                @refresh-archive="loadDossier(props.patientId)"
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
                        :patient-age="patientAge"
                        :can-create-consultation="!isMedecin"
                        @print-fiche="handlePrintFiche"
                        @new-consultation="() => (showConsultationDialog = true)"
                        @fiche-updated="handleFicheUpdated"
                    />
                </div>
                <div data-tour="patients-dossier.finance">
                    <RdvPaiementsSection
                        :rdvs="rdvs"
                        :paiements="paiements"
                        :factures="factures"
                        :consultations="consultations"
                        :show-consultations="showConsultationsTab"
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
            v-if="!loadErrorMessage && !dossierHiddenForMedecin"
            data-tour="patients-dossier.layout-toggle"
            :icon="layoutMode === 'tabs' ? 'pi pi-list' : 'pi pi-th-large'"
            rounded
            class="!fixed bottom-6 right-6 z-50 shadow-lg !w-14 !h-14 bg-gradient-to-r from-primary-500 to-primary-600 border-0"
            :aria-label="layoutMode === 'tabs' ? 'Affichage classique' : 'Affichage en onglets'"
            v-tooltip.left="layoutMode === 'tabs' ? 'Affichage classique' : 'Affichage en onglets'"
            @click="toggleLayoutMode"
        />

        <Dialog v-model:visible="showActiveConsultWarn" modal :style="{ width: '35rem' }" :pt="{
            root: 'rounded-2xl',
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
            root: 'rounded-2xl',
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
            root: 'rounded-2xl',
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
            root: 'rounded-2xl',
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
            root: 'rounded-2xl',
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
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import FichesMedicalesSection from '@/components/patients/FichesMedicalesSection.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import ListePatientConsultations from '@/components/patients/ListePatientConsultations.vue';
import RdvPaiementsSection from '@/components/patients/RdvPaiementsSection.vue';
import PrintDossierBody from '@/components/print/PrintDossierBody.vue';
import PrintFicheV2Body from '@/components/print/PrintFicheV2Body.vue';
import { usePrinter } from '@/composables/usePrinter';
import { useDossierLayout } from '@/composables/useDossierLayout';
import { usePatients } from '@/composables/usePatients';
import { computeAgeYears } from '@/utils/formuleDentaireLayout';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import {
    activatePatientsTourMock,
    deactivatePatientsTourMock,
    getPatientsTourMockPatientIdForScenario,
    resetPatientsTourMockData,
    resolvePatientsTourMockScenario
} from '@/services/patientsTourMock';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import { fetchPatientDossierPrintData, fetchPatientFichePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import { useAssurancesStore } from '@/stores/assurances';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import { computed, ref, onBeforeUnmount, onMounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';
import ArchiveFilesSection from '@/components/patients/ArchiveFilesSection.vue';
import DossierPatientTabsView from '@/components/patients/DossierPatientTabsView.vue';

const props = defineProps({
    patientId: {
        type: Number,
        required: false,
        default: null,
        validator: (value) => value === null || typeof value === 'number'
    }
});
// Breadcrumbs
const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [
    { label: 'Patients', to: '/patients' },
    { label: 'Dossier médical', to: '/dossier' }
];

const patientStore = usePatients();
const { layoutMode, toggleLayoutMode } = useDossierLayout();
const toast = useToast();
const { printComponent } = usePrinter();
const router = useRouter();
const auth = useAuthStore();
const assurancesStore = useAssurancesStore();
const token = localStorage.getItem('token');

// Patient data
const patient = ref(patientStore.normalizePatientDossier());
const consultations = ref([]);
const consultationsLoading = ref(false);
const patientOptions = ref([]);
const patientOptionsLoading = ref(false);
const selectedPatientId = ref(null);

const showRdvDialog = ref(false);
const showConsultationDialog = ref(false);
const showEditDialog = ref(false);
const showAntecedentDialog = ref(false);
const showAllergyDialog = ref(false);
const savingAntecedent = ref(false);
const savingAllergy = ref(false);
const showPrintDialog = ref(false);
const selectedFicheForPrint = ref(null);
const printIncludeEmpty = ref(false);
const printSections = ref([]);
const showActiveConsultWarn = ref(false);
const activeConsultInfo = ref({ hasActive: false, consultationId: null, hasFiche: false });
const patientEditFormRef = ref(null);
const loadErrorMessage = ref('');
let patientSearchTimeout = null;
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const printSectionOptions = [
    { key: 'entretien', label: 'Questionnaire médical' },
    { key: 'examens', label: 'Examen' },
    { key: 'images', label: 'Images et documents' },
    { key: 'plan', label: 'Plan de traitement' },
    { key: 'bilan', label: 'Bilan dentaire' },
    { key: 'seances', label: 'Seances passees' }
];

const fiches = computed(() => patient.value.fiches || []);
const patientAge = computed(() => computeAgeYears(patient.value?.dateNaissance || patient.value?.age));
const rdvs = computed(() => patient.value.rdvs || []);
const archiveFiles = computed(() => patient.value.archiveFiles || []);
const paiements = computed(() => patient.value.paiements || []);
const factures = computed(() => patient.value.factures || []);
const isReception = computed(() => Boolean(auth.user?.roles?.includes('ROLE_RECEPTION')));
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const showConsultationsTab = computed(() => isAdmin.value || isMedecin.value);
const currentPatientId = computed(() => props.patientId ?? patient.value?.id ?? null);
const hidePatientDossierForMedecins = ref(false);
const hidePatientPhoneForMedecins = ref(false);
const isRestrictedMedecin = computed(() => isMedecin.value && !isAdmin.value);
const dossierHiddenForMedecin = computed(() => isRestrictedMedecin.value && hidePatientDossierForMedecins.value);
const shouldHidePatientPhoneForMedecin = computed(() => isRestrictedMedecin.value && hidePatientPhoneForMedecins.value);
const hasOpenDialogs = computed(() => (
    showRdvDialog.value
    || showConsultationDialog.value
    || showEditDialog.value
    || showAntecedentDialog.value
    || showAllergyDialog.value
    || showPrintDialog.value
    || showActiveConsultWarn.value
));

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const getDisplayedPatientId = () => (guidedTourDemoActive ? patient.value?.id ?? null : props.patientId ?? patient.value?.id ?? null);

const ensurePatientLists = () => {
    if (!Array.isArray(patient.value.antecedents)) patient.value.antecedents = [];
    if (!Array.isArray(patient.value.allergies)) patient.value.allergies = [];
    if (!Array.isArray(patient.value.archiveFiles)) patient.value.archiveFiles = [];
};

const handleSaveAntecedent = async (payload) => {
    if (!patient.value?.id) return;
    savingAntecedent.value = true;
    try {
        const res = await addPatientAntecedent(patient.value.id, payload, token);
        if (res?.antecedent) {
            ensurePatientLists();
            patient.value.antecedents = [res.antecedent, ...patient.value.antecedents];
        }
        toast.add({ severity: 'success', summary: 'Antécédent ajouté', life: 2500 });
        showAntecedentDialog.value = false;
    } catch (error) {
        logAppError('Erreur ajout antécédent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'antécédent. si le problème persiste, contactez le support.", life: 3000 });
    } finally {
        savingAntecedent.value = false;
    }
};

const handleSaveAllergy = async (payload) => {
    if (!patient.value?.id) return;
    savingAllergy.value = true;
    try {
        const res = await addPatientAllergy(patient.value.id, payload, token);
        if (res?.allergy) {
            ensurePatientLists();
            patient.value.allergies = [res.allergy, ...patient.value.allergies];
        }
        toast.add({ severity: 'success', summary: 'Allergie ajoutée', life: 2500 });
        showAllergyDialog.value = false;
    } catch (error) {
        logAppError('Erreur ajout allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'allergie. si le problème persiste, contactez le support.", life: 3000 });
    } finally {
        savingAllergy.value = false;
    }
};

const handleDeleteAntecedent = async (item) => {
    if (!patient.value?.id || !item?.id) return;
    try {
        await deletePatientAntecedent(patient.value.id, item.id, token);
        ensurePatientLists();
        patient.value.antecedents = patient.value.antecedents.filter((a) => a.id !== item.id);
        toast.add({ severity: 'success', summary: 'Antécédent supprimé', life: 2000 });
    } catch (error) {
        logAppError('Erreur suppression antécédent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible. si le problème persiste, contactez le support.', life: 3000 });
    }
};

const handleDeleteAllergy = async (item) => {
    if (!patient.value?.id || !item?.id) return;
    try {
        await deletePatientAllergy(patient.value.id, item.id, token);
        ensurePatientLists();
        patient.value.allergies = patient.value.allergies.filter((a) => a.id !== item.id);
        toast.add({ severity: 'success', summary: 'Allergie supprimée', life: 2000 });
    } catch (error) {
        logAppError('Erreur suppression allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible. si le problème persiste, contactez le support.', life: 3000 });
    }
};

const handleCreatePortalAccount = async () => {
    if (!patient.value?.id) return;

    const account = await patientStore.createPortalAccount(patient.value.id, token);
    if (!account) {
        toast.add({ severity: 'error', summary: 'Compte patient', detail: 'Création impossible.', life: 3000 });
        return;
    }

    patient.value.portalAccount = account;
    toast.add({
        severity: 'success',
        summary: 'Compte patient',
        detail: `Compte créé (${account.username}) - mot de passe par défaut: 123`,
        life: 4500
    });
};

const handleResetPortalPassword = async () => {
    if (!patient.value?.id || !patient.value?.portalAccount) return;

    const account = await patientStore.resetPortalAccountPassword(patient.value.id, token);
    if (!account) {
        toast.add({ severity: 'error', summary: 'Compte patient', detail: 'Réinitialisation impossible.', life: 3000 });
        return;
    }

    patient.value.portalAccount = account;
    toast.add({ severity: 'success', summary: 'Compte patient', detail: 'Mot de passe réinitialisé à 123.', life: 3500 });
};

const handleTogglePortalActive = async (active) => {
    if (!patient.value?.id || !patient.value?.portalAccount) return;

    const account = await patientStore.togglePortalAccountActive(patient.value.id, Boolean(active), token);
    if (!account) {
        toast.add({ severity: 'error', summary: 'Compte patient', detail: 'Mise à jour du statut impossible.', life: 3000 });
        return;
    }

    patient.value.portalAccount = account;
    toast.add({
        severity: 'success',
        summary: 'Compte patient',
        detail: account.active ? 'Compte activé.' : 'Compte désactivé.',
        life: 3000
    });
};

const handlePhotoSelected = async (file) => {
    if (!patient.value?.id || !file) return;

    const formData = new FormData();
    formData.append('photo', file);
    const loadingToast = {
        severity: 'info',
        summary: 'Photo patient',
        detail: 'Upload en cours...',
        life: 0
    };

    try {
        toast.add(loadingToast);
        const updated = await patientStore.updatePatient(patient.value.id, formData, token);
        if (!updated) {
            throw new Error('patient_photo_update_failed');
        }

        patient.value = {
            ...patient.value,
            photo: updated.photo ?? patient.value.photo
        };

        toast.remove(loadingToast);
        toast.add({ severity: 'success', summary: 'Photo patient', detail: 'Photo mise à jour.', life: 2500 });
    } catch (error) {
        toast.remove(loadingToast);
        logAppError('Erreur mise à jour photo patient', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de mettre à jour la photo du patient.', life: 3000 });
    }
};

const loadPatientOptions = async (query = '') => {
    patientOptionsLoading.value = true;
    try {
        const params = { page: 1, limit: 20, q: query };
        const response = isMedecin.value
            ? await patientStore.fetchPatientsByMedecin(token, params)
            : await patientStore.fetchPatients(token, params);
        const items = response?.items ?? [];
        patientOptions.value = items.map((p) => ({
            label: p.fullname || `${p.prenom ?? ''} ${p.nom ?? ''}`.trim() || p.nom,
            value: p.id,
            phone: p.telephone || p.phone || '',
            searchText: [p.fullname, `${p.prenom ?? ''} ${p.nom ?? ''}`.trim(), p.nom, p.telephone, p.phone].filter(Boolean).join(' ')
        }));
    } catch (error) {
        logAppError('Erreur lors du chargement des patients', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la liste des patients. si le problème persiste, contactez le support.', life: 3000 });
    } finally {
        patientOptionsLoading.value = false;
    }
};

const ensureSelectedPatientOption = async (patientId) => {
    if (!patientId) return;
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

const handlePatientFilter = (event) => {
    const query = event?.value ?? event?.query ?? '';
    if (patientSearchTimeout) clearTimeout(patientSearchTimeout);
    patientSearchTimeout = setTimeout(() => {
        loadPatientOptions(query);
    }, 250);
};

const loadDossier = async (patientId, { asPageLoad = false } = {}) => {
    if (!patientId) return;
    try {
        const data = await patientStore.fetchPatientDossier(patientId);
        if (data) {
            patient.value = patientStore.normalizePatientDossier(data);
        }
        if (asPageLoad) {
            loadErrorMessage.value = '';
        }
        return true;
    } catch (error) {
        logAppError('Erreur lors du chargement du dossier patient', error);
        if (asPageLoad) {
            loadErrorMessage.value = 'Impossible de charger le dossier du patient.';
        }
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger le dossier du patient. si le problème persiste, contactez le support.', life: 3000 });
        return false;
    }
};

const loadConsultations = async (patientId, { asPageLoad = false } = {}) => {
    if (!patientId) return;
    consultationsLoading.value = true;
    try {
        consultations.value = await patientStore.fetchPatientConsultations(patientId);
        if (asPageLoad) {
            loadErrorMessage.value = '';
        }
        return true;
    } catch (error) {
        logAppError('Erreur lors du chargement des consultations', error);
        consultations.value = [];
        if (asPageLoad) {
            loadErrorMessage.value = 'Impossible de charger les consultations du patient.';
        }
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les consultations du patient. si le problème persiste, contactez le support.', life: 3000 });
        return false;
    } finally {
        consultationsLoading.value = false;
    }
};

const retryLoadPage = async () => {
    loadErrorMessage.value = '';
    const patientId = getDisplayedPatientId();
    if (!patientId) return;
    await loadDossier(patientId, { asPageLoad: true });
    await loadConsultations(patientId, { asPageLoad: true });
};

const loadVisibilityPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        hidePatientDossierForMedecins.value = settings?.hidePatientDossierForMedecins === true;
        hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
    } catch (error) {
        logAppError('Erreur chargement politique visibilité dossier', error);
        hidePatientDossierForMedecins.value = false;
        hidePatientPhoneForMedecins.value = false;
    }
};

onMounted(async () => {
    await loadVisibilityPolicy();
    await assurancesStore.load(token).catch(() => []);
    if (props.patientId != null) {
        await loadDossier(props.patientId, { asPageLoad: true });
        await loadConsultations(props.patientId, { asPageLoad: true });
        selectedPatientId.value = props.patientId;
    }
    await loadPatientOptions();
    await ensureSelectedPatientOption(selectedPatientId.value);
});

onBeforeUnmount(() => {
    if (patientSearchTimeout) {
        clearTimeout(patientSearchTimeout);
    }
    deactivatePatientsTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});

watch(
    () => props.patientId,
    async (newId) => {
        if (guidedTourDemoActive) return;
        if (newId == null) return;
        await loadDossier(newId);
        await loadConsultations(newId);
        selectedPatientId.value = newId;
        await ensureSelectedPatientOption(newId);
    }
);

const handlePatientSelect = (value) => {
    if (!value) return;
    if (guidedTourDemoActive) {
        selectedPatientId.value = value;
        loadDossier(value);
        loadConsultations(value);
        return;
    }
    router.push({ name: 'patients-dossier', params: { patientId: value } });
};

const handleRdvSaved = async () => {
    showRdvDialog.value = false;
    await loadDossier(getDisplayedPatientId());
};

const handleConsultationSaved = async () => {
    showConsultationDialog.value = false;
    await loadConsultations(getDisplayedPatientId());
};

const handlePatientSaved = async () => {
    showEditDialog.value = false;
    await loadDossier(getDisplayedPatientId());
};

const resetTourDialogs = () => {
    showRdvDialog.value = false;
    showConsultationDialog.value = false;
    showEditDialog.value = false;
    showAntecedentDialog.value = false;
    showAllergyDialog.value = false;
    showPrintDialog.value = false;
    showActiveConsultWarn.value = false;
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
    guidedTourDemoActive = true;

    await loadPatientOptions();
    selectedPatientId.value = demoPatientId;
    await ensureSelectedPatientOption(demoPatientId);
    await loadDossier(demoPatientId);
    await loadConsultations(demoPatientId);
    await nextTick();
};

const cleanupGuidedTourDemo = async () => {
    if (!guidedTourDemoActive) {
        resetTourDialogs();
        return;
    }

    if (guidedTourCleanupPromise) {
        return guidedTourCleanupPromise;
    }

    guidedTourCleanupPromise = (async () => {
        resetTourDialogs();
        deactivatePatientsTourMock();
        guidedTourDemoActive = false;
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

const { isGuidedTourStarting } = useGuidedTour({
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

const handlePrintDossier = async () => {
    const patientId = getDisplayedPatientId();
    if (!patientId) return;
    try {
        const res = await fetchPatientDossierPrintData(patientId, localStorage.getItem('token'));
        await printComponent(PrintDossierBody, { patient: res.patient });
    } catch (error) {
        logAppError('DossierPatient', error);
        toast.add({ severity: 'error', summary: 'Dossier', detail: 'Impression indisponible', life: 3500 });
    }
};

const handleFicheUpdated = async () => {
    const patientId = getDisplayedPatientId();
    if (!patientId) return;
    await loadDossier(patientId);
};

const handlePrintFiche = async (fiche) => {
    const ficheId = fiche?.id ?? null;
    if (!ficheId) return;
    selectedFicheForPrint.value = fiche;
    printSections.value = printSectionOptions.map((item) => item.key);
    printIncludeEmpty.value = false;
    showPrintDialog.value = true;
};

const submitPrint = async () => {
    const patientId = getDisplayedPatientId();
    const ficheId = selectedFicheForPrint.value?.id ?? null;
    if (!patientId || !ficheId) return;
    try {
        const res = await fetchPatientFichePrintData(patientId, ficheId, localStorage.getItem('token'));
        await printComponent(PrintFicheV2Body, {
            patient: res.patient,
            fiche: res.fiche,
            sections: printSections.value,
            printEmpty: printIncludeEmpty.value
        });
        showPrintDialog.value = false;
    } catch (error) {
        logAppError('DossierPatient', error);
        toast.add({ severity: 'error', summary: 'Fiche', detail: 'Impression indisponible', life: 3500 });
    }
};
</script>
