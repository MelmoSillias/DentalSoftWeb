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
                        class="w-72"
                        :loading="patientOptionsLoading"
                        @filter="handlePatientFilter"
                        @update:modelValue="handlePatientSelect"
                    />
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Colonne de gauche : Infos patient -->
            <div class="lg:col-span-1 space-y-6">
                <div data-tour="patients-dossier.info-card">
                    <DossierPatientInfoCard
                        :patient="patient"
                        @print-dossier="handlePrintDossier"
                        @edit="() => (showEditDialog = true)"
                        @new-rdv="() => (showRdvDialog = true)"
                        @add-antecedent="() => (showAntecedentDialog = true)"
                        @add-allergy="() => (showAllergyDialog = true)"
                        @delete-antecedent="handleDeleteAntecedent"
                        @delete-allergy="handleDeleteAllergy"
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
                        :can-create-consultation="!isMedecin"
                        @print-fiche="handlePrintFiche"
                        @new-consultation="() => (showConsultationDialog = true)"
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
            </div>
        </div>

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
            <FormCreateConsultation :patient="patient" :patient-id="patient?.id" @saved="handleConsultationSaved" @cancel="showConsultationDialog = false" />
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
            <FormRendezVous :patient="patient" :patient-id="patient?.id" @saved="handleRdvSaved" @cancel="showRdvDialog = false" />
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
            <div data-tour="patients-dossier.dialogs">
                <FormPatient :patient="patient" @saved="handlePatientSaved" @cancel="showEditDialog = false" class="mt-2" />
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
            <div class="p-6 space-y-5">
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
import { usePatients } from '@/composables/usePatients';  
import { addPatientAllergy, addPatientAntecedent, deletePatientAllergy, deletePatientAntecedent } from '@/services/patients';
import { fetchPatientDossierPrintData, fetchPatientFichePrintData } from '@/services/printService';
import { useAuthStore } from '@/stores/auth';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createPatientsDossierTour } from '@/tours/patientsDossierTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import { useToast } from 'primevue/usetoast';
import { computed, ref, onBeforeUnmount, onMounted, nextTick, watch } from 'vue';
import { useRouter } from 'vue-router';

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
const toast = useToast();
const { printComponent } = usePrinter();
const router = useRouter();
const auth = useAuthStore();
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
const isGuidedTourStarting = ref(false);
let patientSearchTimeout = null;

const printSectionOptions = [
    { key: 'entretien', label: 'Entretien verbal' },
    { key: 'examens', label: 'Examen' },
    { key: 'images', label: 'Images et documents' },
    { key: 'plan', label: 'Plan de traitement' },
    { key: 'bilan', label: 'Bilan dentaire' },
    { key: 'seances', label: 'Seances passees' }
];

const fiches = computed(() => patient.value.fiches || []);
const rdvs = computed(() => patient.value.rdvs || []);
const paiements = computed(() => patient.value.paiements || []);
const factures = computed(() => patient.value.factures || []);
const isReception = computed(() => Boolean(auth.user?.roles?.includes('ROLE_RECEPTION')));
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const showConsultationsTab = computed(() => isAdmin.value || isMedecin.value);
const currentPatientId = computed(() => props.patientId ?? patient.value?.id ?? null);
const hasOpenDialogs = computed(() => (
    showRdvDialog.value
    || showConsultationDialog.value
    || showEditDialog.value
    || showAntecedentDialog.value
    || showAllergyDialog.value
    || showPrintDialog.value
));

const ensurePatientLists = () => {
    if (!Array.isArray(patient.value.antecedents)) patient.value.antecedents = [];
    if (!Array.isArray(patient.value.allergies)) patient.value.allergies = [];
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
        console.error('Erreur ajout antécédent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'antécédent.", life: 3000 });
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
        console.error('Erreur ajout allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'ajouter l'allergie.", life: 3000 });
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
        console.error('Erreur suppression antécédent', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.', life: 3000 });
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
        console.error('Erreur suppression allergie', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Suppression impossible.', life: 3000 });
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
            value: p.id
        }));
    } catch (error) {
        console.error(error);
    } finally {
        patientOptionsLoading.value = false;
    }
};

const ensureSelectedPatientOption = async (patientId) => {
    if (!patientId) return;
    const exists = patientOptions.value.some((opt) => opt.value === patientId);
    if (exists) return;
    const data = await patientStore.fetchPatientById(patientId);
    if (!data?.id) return;
    patientOptions.value = [
        {
            label: data.fullname || `${data.prenom ?? ''} ${data.nom ?? ''}`.trim() || data.nom,
            value: data.id
        },
        ...patientOptions.value
    ];
};

const handlePatientFilter = (event) => {
    const query = event?.value ?? event?.query ?? '';
    if (patientSearchTimeout) clearTimeout(patientSearchTimeout);
    patientSearchTimeout = setTimeout(() => {
        loadPatientOptions(query);
    }, 250);
};

const loadDossier = async (patientId) => {
    if (!patientId) return;
    const data = await patientStore.fetchPatientDossier(patientId);
    if (data) {
        patient.value = patientStore.normalizePatientDossier(data);
    }
};

const loadConsultations = async (patientId) => {
    if (!patientId) return;
    consultationsLoading.value = true;
    try {
        consultations.value = await patientStore.fetchPatientConsultations(patientId);
    } catch (error) {
        console.error(error);
        consultations.value = [];
    } finally {
        consultationsLoading.value = false;
    }
};

onMounted(async () => {
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    if (props.patientId != null) {
        await loadDossier(props.patientId);
        await loadConsultations(props.patientId);
        selectedPatientId.value = props.patientId;
    }
    await loadPatientOptions();
    await ensureSelectedPatientOption(selectedPatientId.value);
});

onBeforeUnmount(() => {
    if (patientSearchTimeout) {
        clearTimeout(patientSearchTimeout);
    }
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    resetTourDialogs();
});

watch(
    () => props.patientId,
    async (newId) => {
        if (newId == null) return;
        await loadDossier(newId);
        await loadConsultations(newId);
        selectedPatientId.value = newId;
        await ensureSelectedPatientOption(newId);
    }
);

const handlePatientSelect = (value) => {
    if (!value) return;
    router.push({ name: 'patients-dossier', params: { patientId: value } });
};

const handleRdvSaved = async () => {
    showRdvDialog.value = false;
    await loadDossier(props.patientId ?? patient.value?.id);
};

const handleConsultationSaved = async () => {
    showConsultationDialog.value = false;
    await loadConsultations(props.patientId ?? patient.value?.id);
};

const handlePatientSaved = async () => {
    showEditDialog.value = false;
    await loadDossier(props.patientId ?? patient.value?.id);
};

const resetTourDialogs = () => {
    showRdvDialog.value = false;
    showConsultationDialog.value = false;
    showEditDialog.value = false;
    showAntecedentDialog.value = false;
    showAllergyDialog.value = false;
    showPrintDialog.value = false;
};

const openTourEditDialog = () => {
    if (!currentPatientId.value) return;
    showEditDialog.value = true;
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'patients-dossier' || isGuidedTourStarting.value) {
        return;
    }

    if (hasOpenDialogs.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidee',
            detail: 'Fermez les fenetres ouvertes avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    isGuidedTourStarting.value = true;

    try {
        resetTourDialogs();
        await nextTick();

        const steps = createPatientsDossierTour({
            hasPatientContext: Boolean(currentPatientId.value),
            isMedecin: isMedecin.value,
            openEditPatientDialog: openTourEditDialog,
            closeAllDialogs: resetTourDialogs
        });

        await startTourGuide({
            group: 'patients-dossier',
            steps,
            onAfterExit: resetTourDialogs,
            onFinish: resetTourDialogs
        });
    } catch (error) {
        console.error('Erreur lancement guided tour dossier patient', error);
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour du dossier patient.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

const goBackToList = () => {
    router.push({ name: 'patients-liste' });
};

const handlePrintDossier = async () => {
    const patientId = props.patientId ?? patient.value?.id;
    if (!patientId) return;
    try {
        const res = await fetchPatientDossierPrintData(patientId, localStorage.getItem('token'));
        await printComponent(PrintDossierBody, { patient: res.patient });
    } catch (error) {
        console.error(error);
        toast.add({ severity: 'error', summary: 'Dossier', detail: 'Impression indisponible', life: 3500 });
    }
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
    const patientId = props.patientId ?? patient.value?.id;
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
        console.error(error);
        toast.add({ severity: 'error', summary: 'Fiche', detail: 'Impression indisponible', life: 3500 });
    }
};
</script>
