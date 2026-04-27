<script setup>
import ActionsPatient from '@/components/patients/ActionsPatient.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import PatientAvatar from '@/components/patients/PatientAvatar.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { usePatients } from '@/composables/usePatients';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import {
    activatePatientsTourMock,
    deactivatePatientsTourMock,
    getPatientsTourMockActivePatient,
    resetPatientsTourMockData,
    resolvePatientsTourMockScenario
} from '@/services/patientsTourMock';
import { useAuthStore } from '@/stores/auth';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createPatientsListTour } from '@/tours/patientsListTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext'; 
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { InputIcon } from 'primevue';

const toast = useToast();
const router = useRouter();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const { patients, totalRecords, loading, fetchPatients, fetchPatientsByMedecin, normalizePatient, checkConsultationActive, deleteConsultation } = usePatients();
const auth = useAuthStore();
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const hidePatientPhoneForMedecins = ref(false);
const shouldHidePatientPhoneForMedecin = computed(() => isMedecin.value && !isAdmin.value && hidePatientPhoneForMedecins.value);
const searchQuery = ref('');
const first = ref(0);
const rowsPerPage = ref(10);
const sortField = ref(null);
const sortOrder = ref(null);
const lastTouchedId = ref(null);
let highlightTimeout = null;
const toolbarConsultLoading = ref(false);
const consultationLoading = ref({});
const isGuidedTourStarting = ref(false);
let guidedTourTableState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;
let syncingTourState = false;

const showPatientDialog = ref(false);
const showConsultationDialog = ref(false);
const showRdvDialog = ref(false);
const showActiveConsultWarn = ref(false);

const editingPatient = ref(null);
const consultationPatient = ref(null);
const rdvPatient = ref(null);
const activeConsultWarnPatient = ref(null);
const activeConsultInfo = ref({ hasActive: false, consultationId: null, hasFiche: false });

const setConsultationLoading = (key, value) => {
    if (key === undefined || key === null) return;
    consultationLoading.value = { ...consultationLoading.value, [key]: value };
};

let searchTimeout = null;

const wait = (ms = 220) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const loadPatients = async ({ page = 1, limit = rowsPerPage.value, q = searchQuery.value, sort = sortField.value, order = sortOrder.value } = {}) => {
    const orderValue = order === 1 ? 'asc' : order === -1 ? 'desc' : null;
    try {
        if (isMedecin.value) {
            await fetchPatientsByMedecin(token, { page, limit, q, sortField: sort, sortOrder: orderValue });
        } else {
            await fetchPatients(token, { page, limit, q, sortField: sort, sortOrder: orderValue });
        }
    } catch (error) {
        console.error('Erreur lors de la récupération des patients:', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de récupérer les patients.', life: 3000 });
    }
};

const loadVisibilityPolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
    } catch (error) {
        console.error('Erreur chargement politique visibilité patients', error);
        hidePatientPhoneForMedecins.value = false;
    }
};

onMounted(() => {
    loadVisibilityPolicy();
    loadPatients({ page: 1, limit: rowsPerPage.value });
});

function formatAge(dateNaissance) {
    if (!dateNaissance) return '—';
    const birthDate = new Date(dateNaissance);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    return `${age} ans`;
}

function getConsultationSeverity(statut) {
    const severities = {
        'URGENT': 'danger',
        'NORMAL': 'success',
        'CONTROLE': 'info',
        'SUIVI': 'warning'
    };
    return severities[statut] || 'secondary';
}

const printPatients = async () => {
    const rows = (patients.value || []).map((p) => ({
        nom: p?.nom || '—',
        prenom: p?.prenom || '—',
        telephone: p?.telephone || '—',
        age: formatAge(p?.dateNaissance),
        sexe: p?.sexe || '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des Patients',
        subtitle: `${rows.length} patient(s)`,
        columns: [
            { key: 'nom', label: 'Nom' },
            { key: 'prenom', label: 'Prénom' },
            { key: 'telephone', label: 'Téléphone' },
            { key: 'age', label: 'Âge' },
            { key: 'sexe', label: 'Sexe' }
        ],
        rows
    });
};

const openCreatePatient = () => {
    editingPatient.value = null;
    showPatientDialog.value = true;
};

const openEditPatient = (patient) => {
    editingPatient.value = patient;
    showPatientDialog.value = true;
};

const handlePatientSaved = (saved) => {
    if (!saved?.id) {
        showPatientDialog.value = false;
        loadPatients();
        return;
    }

    const normalized = normalizePatient(saved);
    const idx = patients.value.findIndex((p) => p.id === normalized.id);
    if (idx >= 0) {
        patients.value[idx] = normalizePatient({ ...patients.value[idx], ...normalized });
    } else {
        patients.value.unshift(normalized);
    }
    lastTouchedId.value = normalized.id;
    if (highlightTimeout) clearTimeout(highlightTimeout);
    highlightTimeout = setTimeout(() => {
        lastTouchedId.value = null;
    }, 3000);
    showPatientDialog.value = false;
};

const openConsultation = async (patient = null) => {
    const loadingKey = patient?.id ?? null;
    if (loadingKey) {
        setConsultationLoading(loadingKey, true);
    } else {
        toolbarConsultLoading.value = true;
    }

    try {
        if (patient?.id) {
            const res = await checkConsultationActive(patient.id, token);
            activeConsultInfo.value = {
                hasActive: Boolean(res?.hasActive),
                consultationId: res?.consultationId ?? null,
                hasFiche: Boolean(res?.hasFiche)
            };
            if (activeConsultInfo.value.hasActive) {
                activeConsultWarnPatient.value = patient;
                showActiveConsultWarn.value = true;
                return;
            }
        }

        consultationPatient.value = patient;
        showConsultationDialog.value = true;
    } catch (error) {
        console.error('Erreur lors de la vérification des consultations actives', error);
        toast.add({ severity: 'warn', summary: 'Vérification', detail: 'Impossible de vérifier les consultations en cours.', life: 2500 });
    } finally {
        if (loadingKey) {
            setConsultationLoading(loadingKey, false);
        } else {
            toolbarConsultLoading.value = false;
        }
    }
};

const handleConsultationSaved = () => {
    showConsultationDialog.value = false;
};

const openRendezVous = (patient = null) => {
    rdvPatient.value = patient;
    showRdvDialog.value = true;
};

const closeActiveConsultWarn = () => {
    const patientId = activeConsultWarnPatient.value?.id;
    showActiveConsultWarn.value = false;
    activeConsultWarnPatient.value = null;
    activeConsultInfo.value = { hasActive: false, consultationId: null, hasFiche: false };
    toolbarConsultLoading.value = false;
    if (patientId) {
        setConsultationLoading(patientId, false);
    }
};

const goToDossierFromWarn = () => {
    if (activeConsultWarnPatient.value?.id) {
        openDossier(activeConsultWarnPatient.value);
    }
    closeActiveConsultWarn();
};

const cancelActiveConsultation = async () => {
    if (!activeConsultInfo.value.consultationId) return;
    if (activeConsultWarnPatient.value?.id) {
        setConsultationLoading(activeConsultWarnPatient.value.id, true);
    }
    try {
        await deleteConsultation(activeConsultInfo.value.consultationId, token);
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'La consultation en cours a été supprimée.' });
        const patient = activeConsultWarnPatient.value;
        closeActiveConsultWarn();
        await loadPatients();
        if (patient) {
            openConsultation(patient);
        }
    } catch (error) {
        console.error('Erreur lors de la suppression de la consultation active', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer la consultation en cours.', life: 3000 });
    } finally {
        if (activeConsultWarnPatient.value?.id) {
            setConsultationLoading(activeConsultWarnPatient.value.id, false);
        }
    }
};

const handleRdvSaved = () => {
    showRdvDialog.value = false;
};

const openDossier = (patient) => {
    if (!patient?.id) return;
    router.push({ name: 'patients-dossier', params: { patientId: parseInt(patient.id) } });
};

const formatConsultationDate = (dateValue) => {
    if (!dateValue) return '';
    const parsed = new Date(dateValue);
    if (Number.isNaN(parsed.getTime())) return dateValue;
    return parsed.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
};

watch(searchQuery, () => {
    if (syncingTourState) return;
    first.value = 0;
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadPatients({ page: 1, limit: rowsPerPage.value, q: searchQuery.value });
    }, 300);
});

const handlePage = (event) => {
    first.value = event.first;
    rowsPerPage.value = event.rows;
    const page = Math.floor(event.first / event.rows) + 1;
    loadPatients({ page, limit: event.rows, q: searchQuery.value, sort: sortField.value, order: sortOrder.value });
};

const handleSort = (event) => {
    sortField.value = event.sortField || null;
    sortOrder.value = event.sortOrder ?? null;
    loadPatients({ page: 1, limit: rowsPerPage.value, q: searchQuery.value, sort: sortField.value, order: sortOrder.value });
};

const rowClass = (data) => ({ 'row-highlight': data.id === lastTouchedId.value });

const resetTourDialogs = () => {
    showPatientDialog.value = false;
    showConsultationDialog.value = false;
    showRdvDialog.value = false;
    showActiveConsultWarn.value = false;
    editingPatient.value = null;
    consultationPatient.value = null;
    rdvPatient.value = null;
    activeConsultWarnPatient.value = null;
    activeConsultInfo.value = { hasActive: false, consultationId: null, hasFiche: false };
};

const hasOpenPatientDialog = computed(() => (
    showPatientDialog.value
    || showConsultationDialog.value
    || showRdvDialog.value
    || showActiveConsultWarn.value
));

const captureTableState = () => ({
    patients: cloneValue(patients.value),
    totalRecords: totalRecords.value,
    searchQuery: searchQuery.value,
    first: first.value,
    rowsPerPage: rowsPerPage.value,
    sortField: sortField.value,
    sortOrder: sortOrder.value,
    lastTouchedId: lastTouchedId.value
});

const restoreTableState = async (state) => {
    if (!state) {
        await loadPatients({ page: 1, limit: rowsPerPage.value });
        return;
    }

    if (searchTimeout) clearTimeout(searchTimeout);
    syncingTourState = true;
    searchQuery.value = state.searchQuery;
    first.value = state.first;
    rowsPerPage.value = state.rowsPerPage;
    sortField.value = state.sortField;
    sortOrder.value = state.sortOrder;
    patients.value = cloneValue(state.patients) || [];
    totalRecords.value = state.totalRecords ?? patients.value.length;
    lastTouchedId.value = state.lastTouchedId ?? null;
    await nextTick();
    syncingTourState = false;
};

const prepareGuidedTourDemo = async () => {
    guidedTourTableState = captureTableState();
    const scenario = resolvePatientsTourMockScenario('static');

    activatePatientsTourMock(scenario);
    resetPatientsTourMockData(scenario);
    guidedTourDemoActive = true;

    if (searchTimeout) clearTimeout(searchTimeout);
    syncingTourState = true;
    searchQuery.value = '';
    first.value = 0;
    sortField.value = null;
    sortOrder.value = null;
    await nextTick();
    syncingTourState = false;
    await loadPatients({ page: 1, limit: rowsPerPage.value, q: '', sort: null, order: null });
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
        const stateToRestore = guidedTourTableState;
        guidedTourTableState = null;
        await restoreTableState(stateToRestore);
    })().finally(() => {
        guidedTourCleanupPromise = null;
    });

    return guidedTourCleanupPromise;
};

const findTourDuplicateConsultationPatient = () => (
    patients.value.find((patient) => Number(patient?.derniereConsultation?.statut) === 0)
    || patients.value[0]
    || null
);

const openTourConsultationWarning = async () => {
    resetTourDialogs();
    await nextTick();
    await wait();

    const patient = getPatientsTourMockActivePatient() || findTourDuplicateConsultationPatient();
    if (!patient?.id) {
        return;
    }

    await openConsultation(patient);
    await nextTick();
    await wait();
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'patients-liste' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidée',
            detail: 'Attendez la fin du chargement des patients avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    if (hasOpenPatientDialog.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidée',
            detail: 'Fermez d abord les fenetres ouvertes avant de lancer le tour.',
            life: 3000
        });
        return;
    }

    isGuidedTourStarting.value = true;

    try {
        await cleanupGuidedTourDemo();
        await prepareGuidedTourDemo();
        resetTourDialogs();
        await nextTick();

        const steps = createPatientsListTour({
            hasPatients: patients.value.length > 0,
            isMedecin: isMedecin.value,
            openCreatePatientDialog: openCreatePatient,
            openRendezVousDialog: () => openRendezVous(),
            openConsultationDialog: () => openConsultation(),
            openDuplicateConsultationDialog: openTourConsultationWarning,
            closeAllDialogs: resetTourDialogs
        });

        await startTourGuide({
            group: 'patients-liste',
            steps,
            onAfterExit: cleanupGuidedTourDemo,
            onFinish: cleanupGuidedTourDemo
        });
    } catch (error) {
        console.error('Erreur lancement guided tour patients', error);
        await cleanupGuidedTourDemo();
        toast.add({
            severity: 'error',
            summary: 'Aide guidée',
            detail: 'Impossible de lancer le tour guide sur la page patients.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

onBeforeUnmount(() => {
    if (highlightTimeout) clearTimeout(highlightTimeout);
    if (searchTimeout) clearTimeout(searchTimeout);
    deactivatePatientsTourMock();
    guidedTourDemoActive = false;
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    resetTourDialogs();
});

onMounted(() => {
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});
</script>

<template>
    <section
        class="min-h-screen w-full px-4 py-4 sm:px-5 sm:py-5 md:px-6 md:py-6 lg:px-8 lg:py-8 transition-colors duration-300">
        <!-- Header Section -->
        <div class="mb-6 md:mb-8 w-full">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div class="space-y-2" data-tour="patients-list.header">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="fas fa-user-injured text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <div>
                            <h1
                                class="text-2xl sm:text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                                Gestion des Patients
                            </h1>
                            <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base mt-1">
                                Gérez les dossiers médicaux et les consultations de vos patients
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-row gap-3 w-full md:w-auto" data-tour="patients-list.toolbar">
                    <Button label="Nouveau rendez-vous" icon="fas fa-calendar-plus" severity="warn"
                        data-tour="patients-list.rdv-button"
                        class=" sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-blue-500 to-blue-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                        @click="openRendezVous()" :pt="{ label: { class: 'hidden sm:inline' } }" />
                    <Button v-if="!isMedecin" label="Nouvelle consultation" severity="success" icon="fas fa-stethoscope"
                        data-tour="patients-list.consultation-button"
                        class="sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-green-500 to-green-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                        :loading="toolbarConsultLoading" @click="openConsultation()"
                        :pt="{ label: { class: 'hidden sm:inline' } }" />
                    <Button label="Ajouter un patient" icon="fas fa-plus"
                        data-tour="patients-list.add-patient-button"
                        class="sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                        @click="openCreatePatient" :pt="{ label: { class: 'hidden sm:inline' } }" />
                </div>
            </div> 
        </div>
 
        <!-- Patients Table Card -->
        <div
            class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm">
            <!-- Table Header -->
            <div
                class="px-4 sm:px-2 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                <div class="flex flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1 col-6">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            Liste des Patients
                        </h3>
                        <p class="text-sm text-surface-600 dark:text-surface-400">
                            {{ totalRecords || patients.length }} patient(s) au total
                        </p>
                    </div>
                     <div class="sm:w-auto col-6" data-tour="patients-list.search">
                        <label
                            class="block text-sm md:text-base font-medium text-surface-700 dark:text-surface-300 mb-2">
                            Rechercher un patient
                        </label>
                        <IconField class="w-full relative">
                            <InputIcon class="fas fa-search text-surface-400" />
                            <InputText v-model="searchQuery" placeholder="Nom, prénom, téléphone, adresse..."
                                class="w-full p-3 md:p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all" />
                        </IconField>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="m-2 p-2 border-rounded-1 overflow-x-auto" data-tour="patients-list.table">
                <DataTable :value="patients" dataKey="id" :loading="loading" :paginator="true" lazy
                    :rows="rowsPerPage" :rowsPerPageOptions="[5, 10, 20, 50]" :first="first"
                    :totalRecords="totalRecords" @page="handlePage" @sort="handleSort"
                    :sortField="sortField" :sortOrder="sortOrder"
                    :rowClass="rowClass" class="min-w-[500px] md:min-w-0 rounded-none border-0" :pt="{
                        table: 'rounded-none',
                        thead: 'bg-surface-50 dark:bg-surface-900/50',
                        headerCell: ({ state }) => ({
                            class: [
                                'py-4 px-5 text-left font-semibold text-surface-700 dark:text-surface-300',
                                'border-b border-surface-200 dark:border-surface-700',
                                'bg-gradient-to-b from-surface-50 to-surface-100/50 dark:from-surface-900/50 dark:to-surface-800',
                                state.sorted && 'bg-primary-50 dark:bg-primary-900/20'
                            ]
                        }),
                        bodyCell: {
                            class: 'py-4 px-5 border-b border-surface-100 dark:border-surface-800'
                        },
                        row: ({ data }) => ({
                            class: [
                                'hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors',
                                data.derniereConsultation?.statut === 'URGENT' && 'bg-red-50/50 dark:bg-red-900/10'
                            ]
                        }),
                        paginator: {
                            class: 'px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
                        }
                    }"> 
                    <Column field="fullname" header="Nom & Prénom" sortable>
                        <template #body="{ data }">
                            <div class="flex items-center gap-3">
                                <PatientAvatar :patient="data" size-class="w-10 h-10" text-class="font-semibold" />
                                <div>
                                    <span class="font-semibold text-surface-900 dark:text-surface-100">
                                        {{ data.fullname || `${data.prenom ?? ''} ${data.nom ?? ''}`.trim() || data.nom }}
                                    </span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <Tag :value="data.sexe" :severity="data.sexe === 'M' ? 'info' : 'secondary'"
                                            class="px-2 py-0.5 text-xs rounded-full" />
                                        <span class="text-xs text-surface-500">
                                            {{ formatAge(data.dateNaissance) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </Column>

                    <!-- <Column field="sexe" header="Sexe" sortable headerClass="hidden md:table-cell"
                        bodyClass="hidden md:table-cell">
                        <template #body="{ data }">
                            <Tag :value="data.sexe"
                                :severity="data.sexe === 'M' ? 'info' : 'secondary'"
                                class="px-3 py-1 rounded-full font-medium" />
                        </template>
                    </Column> -->

                    <Column field="telephone" header="Téléphone" sortable headerClass="hidden md:table-cell"
                        bodyClass="hidden md:table-cell">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-phone text-surface-400"></i>
                                <span class="font-mono text-surface-900 dark:text-surface-100">{{ shouldHidePatientPhoneForMedecin ? 'Masqué par l\'administrateur' : data.telephone }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="adresse" header="Adresse" sortable headerClass="hidden lg:table-cell"
                        bodyClass="hidden lg:table-cell">
                        <template #body="{ data }">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-map-marker text-surface-400"></i>
                                <span class="text-surface-700 dark:text-surface-300 truncate max-w-[200px]">{{ data.adresse || '—' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Dernière consultation" sortField="derniereConsultation.date" sortable
                        headerClass="hidden xl:table-cell" bodyClass="hidden xl:table-cell">
                        <template #body="{ data }">
                            <div v-if="data.derniereConsultation" class="space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-surface-900 dark:text-surface-100">
                                        {{ formatConsultationDate(data.derniereConsultation.date) }}
                                    </span>
                                    <Tag :value="data.derniereConsultation.statut"
                                        :severity="getConsultationSeverity(data.derniereConsultation.statut)"
                                        class="px-2 py-0.5 text-xs rounded-full" />
                                </div>
                                <p class="text-sm text-surface-600 dark:text-surface-400 line-clamp-2">
                                    {{ data.derniereConsultation.motif || '—' }}
                                </p>
                            </div>
                            <div v-else
                                class="flex flex-col items-center justify-center p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg">
                                <i class="pi pi-info-circle text-surface-400 mb-2"></i>
                                <span class="text-sm text-surface-500">Aucune consultation</span>
                            </div>
                        </template>
                    </Column>

                    <Column header="Actions" :style="{ minWidth: '200px' }">
                        <template #body="{ data }">
                            <div class="flex flex-wrap items-center gap-2" :data-tour="data.id === patients[0]?.id ? 'patients-list.row-actions' : null">
                                <Button icon="pi pi-eye" severity="info" text rounded
                                    v-tooltip.top="'Voir dossier médical'"
                                    class="hover:bg-blue-50 dark:hover:bg-blue-900/20" @click="openDossier(data)" />
                                <Button v-if="!isMedecin" icon="fas fa-stethoscope" severity="success" text rounded
                                    v-tooltip.top="'Nouvelle consultation'"
                                    class="hover:bg-green-50 dark:hover:bg-green-900/20" @click="openConsultation(data)"
                                    :loading="consultationLoading[data.id] === true" />
                                <Button icon="fas fa-calendar-plus" severity="help" text rounded
                                    v-tooltip.top="'Nouveau rendez-vous'"
                                    class="hover:bg-purple-50 dark:hover:bg-purple-900/20"
                                    @click="openRendezVous(data)" />
                                <Button icon="pi pi-pencil" severity="secondary" text rounded
                                    v-tooltip.top="'Modifier patient'"
                                    class="hover:bg-surface-100 dark:hover:bg-surface-700"
                                    @click="openEditPatient(data)" />
                            </div>
                        </template>
                    </Column>

                    <template #footer>
                        <div class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800 flex items-center justify-between">
                            <div class="text-sm text-surface-600 dark:text-surface-400">
                                {{ totalRecords || patients.length }} patient(s) retrouvés (s)
                            </div>
                            <div class="flex items-center gap-3" data-tour="patients-list.export">
                                <Button icon="pi pi-download" severity="secondary" text size="small" label="Exporter"
                                    class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                                    @click="printPatients" />
                            </div>
                        </div>
                    </template>

                    <template #empty>
                        <div class="text-center py-16">
                            <div
                                class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                                <i class="fas fa-user-injured text-4xl text-surface-400"></i>
                            </div>
                            <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">
                                Aucun patient trouvé
                            </h4>
                            <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                                {{ searchQuery ? 'Aucun résultat ne correspond à votre recherche.' : 'Commencez par ajouter votre premier patient.' }}
                            </p>
                            <div class="flex gap-3 justify-center">
                                <Button v-if="!searchQuery" icon="fas fa-plus" label="Ajouter un patient"
                                    @click="openCreatePatient"
                                    class="bg-gradient-to-r from-primary-500 to-primary-600 border-0" />
                                <Button v-else icon="pi pi-filter-slash" label="Réinitialiser la recherche"
                                    severity="secondary" outlined @click="searchQuery = ''" />
                            </div>
                        </div>
                    </template>

                    <template #loading>
                        <div class="flex items-center justify-center py-16">
                            <div class="text-center">
                                <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
                                <p class="text-surface-600 dark:text-surface-400">Chargement des patients...</p>
                            </div>
                        </div>
                    </template>
                </DataTable>
            </div>

        </div>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-0 md:mb-8 mt-6" data-tour="patients-list.stats">
            <div
                class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-4 sm:p-5 border border-blue-200/50 dark:border-blue-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Total Patients</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ totalRecords || patients.length }}</p>
                    </div>
                    <i class="fas fa-users text-2xl text-blue-500"></i>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-4 sm:p-5 border border-green-200/50 dark:border-green-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-700 dark:text-green-300 font-medium">Consultations aujourd'hui</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-2">--</p>
                    </div>
                    <i class="fas fa-stethoscope text-2xl text-green-500"></i>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-4 sm:p-5 border border-amber-200/50 dark:border-amber-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">Rendez-vous à venir</p>
                        <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">--</p>
                    </div>
                    <i class="fas fa-calendar-day text-2xl text-amber-500"></i>
                </div>
            </div>

            <div
                class="bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl p-4 sm:p-5 border border-purple-200/50 dark:border-purple-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-purple-700 dark:text-purple-300 font-medium">Nouveaux ce mois</p>
                        <p class="text-2xl font-bold text-purple-900 dark:text-purple-100 mt-2">--</p>
                    </div>
                    <i class="fas fa-chart-line text-2xl text-purple-500"></i>
                </div>
            </div>
        </div>

        <!-- Dialogs -->
        <Dialog v-model:visible="showPatientDialog" modal :style="{ width: '45rem' }" :pt="{
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
                        <i :class="[
                            'fas',
                            editingPatient ? 'fa-user-edit text-primary-600 dark:text-primary-400' : 'fa-user-plus text-primary-600 dark:text-primary-400'
                        ]"></i>
                    </div>
                    <div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">
                            {{ editingPatient ? 'Modifier le patient' : 'Ajouter un patient' }}
                        </h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1"> {{ editingPatient ? 'Mettez à jour les informations du patient' : 'Créez un nouveau dossier patient' }}
                        </p>
                    </div>
                </div>
            </template>
            <div data-tour="patients-list.dialog.patient">
                <FormPatient :patient="editingPatient" @saved="handlePatientSaved" @cancel="showPatientDialog = false"
                    class="mt-2" />
            </div>
        </Dialog>

        <Dialog v-model:visible="showConsultationDialog" modal :style="{ width: '50rem' }" :pt="{
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
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">
                            Nouvelle consultation
                        </h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            {{ consultationPatient?.fullname || consultationPatient?.nom || 'Nouveau patient' }}
                        </p>
                    </div>
                </div>
            </template>
            <div data-tour="patients-list.dialog.consultation">
                <FormCreateConsultation :patient="consultationPatient" :patient-id="consultationPatient?.id"
                    @saved="handleConsultationSaved" @cancel="showConsultationDialog = false" />
            </div>
        </Dialog>

        <Dialog v-model:visible="showActiveConsultWarn" modal :style="{ width: '35rem' }" :pt="{
            root: 'rounded-2xl',
            header: 'bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900 dark:to-surface-800 px-6 py-4 border-b',
            content: 'p-0 mt-4'
        }">
            <div class="p-6" data-tour="patients-list.dialog.active-warning">
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
                    <Button label="Compris" severity="secondary" @click="closeActiveConsultWarn"
                        class="rounded-xl px-5" />
                    <Button v-if="!activeConsultInfo.hasFiche" label="Annuler la consultation" icon="pi pi-times"
                        severity="danger" @click="cancelActiveConsultation" class="rounded-xl px-5" />
                </div>
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
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">
                            Nouveau rendez-vous
                        </h4>
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">
                            {{ rdvPatient?.fullname || rdvPatient?.nom || 'Nouveau patient' }}
                        </p>
                    </div>
                </div>
            </template>
            <div data-tour="patients-list.dialog.rdv">
                <FormRendezVous :patient="rdvPatient" :patient-id="rdvPatient?.id" @saved="handleRdvSaved"
                    @cancel="showRdvDialog = false" />
            </div>
        </Dialog>
    </section>
</template>

<style scoped>
:deep(.row-highlight),
:deep(.row-highlight > td) {
    animation: flash-green 0.6s ease-in-out 0s 4 alternate;
    background-color: #d1fae5 !important;
}

@keyframes flash-green {
    0% {
        background-color: #d1fae5;
    }

    100% {
        background-color: #bbf7d0;
    }
}
</style>
