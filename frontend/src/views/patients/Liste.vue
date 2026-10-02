<script setup>
import { logAppError } from '@/utils/appLogger';

import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import ActionsPatient from '@/components/patients/ActionsPatient.vue';
import CreateConsultationDialog from '@/components/patients/CreateConsultationDialog.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import PatientAvatar from '@/components/patients/PatientAvatar.vue';
import FormRendezVous from '@/components/patients/FormRendezVous.vue';
import CabinetServiceDialog from '@/components/patients/CabinetServiceDialog.vue';
import PatientsReferralStats from '@/components/patients/PatientsReferralStats.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import { usePatients } from '@/composables/usePatients';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { fetchPatientsOverviewStats } from '@/services/patients';
import { activatePatientsTourMock, deactivatePatientsTourMock, getPatientsTourMockActivePatient, getPatientsTourMockPatientIdForScenario, resetPatientsTourMockData, resolvePatientsTourMockScenario } from '@/services/patientsTourMock';
import { useAuthStore } from '@/stores/auth';
import { useAssurancesStore } from '@/stores/assurances';
import { useGuidedTour } from '@/composables/useGuidedTour';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { InputIcon } from 'primevue';

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Patients' }, { label: 'Liste' }];

const toast = useToast();
const router = useRouter();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const { patients, totalRecords, loading, fetchPatients, fetchPatientsByMedecin, normalizePatient, checkConsultationActive, deleteConsultation, deletePatient, fetchPatientsTrash, restorePatient } = usePatients();
const auth = useAuthStore();
const assurancesStore = useAssurancesStore();
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
const loadErrorMessage = ref('');
const initializingPage = ref(true);
const statsLoading = ref(false);
const overviewStats = ref({
    totalPatients: 0,
    consultationsToday: 0,
    upcomingAppointments: 0,
    newPatientsThisMonth: 0,
    referrals: []
});
let guidedTourTableState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;
let syncingTourState = false;

const showPatientDialog = ref(false);
const showConsultationDialog = ref(false);
const showRdvDialog = ref(false);
const showCabinetServiceDialog = ref(false);
const cabinetServicePatient = ref(null);
const showActiveConsultWarn = ref(false);
const showTrashDialog = ref(false);
const showDeletePatientDialog = ref(false);

const editingPatient = ref(null);
const consultationPatient = ref(null);
const rdvPatient = ref(null);
const activeConsultWarnPatient = ref(null);
const activeConsultInfo = ref({ hasActive: false, consultationId: null, hasFiche: false });
const patientToDelete = ref(null);
const deletingPatientId = ref(null);
const restoringPatientId = ref(null);
const patientFormRef = ref(null);
const rdvFormRef = ref(null);

const trashPatients = ref([]);
const trashTotalRecords = ref(0);
const trashLoading = ref(false);
const trashSearch = ref('');
const trashFirst = ref(0);
const trashRowsPerPage = ref(10);
let trashSearchTimeout = null;

const setConsultationLoading = (key, value) => {
    if (key === undefined || key === null) return;
    consultationLoading.value = { ...consultationLoading.value, [key]: value };
};

let searchTimeout = null;

const wait = (ms = 220) =>
    new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const loadPatients = async ({ page = 1, limit = rowsPerPage.value, q = searchQuery.value, sort = sortField.value, order = sortOrder.value, asPageLoad = false } = {}) => {
    const orderValue = order === 1 ? 'asc' : order === -1 ? 'desc' : null;
    try {
        if (isMedecin.value) {
            await fetchPatientsByMedecin(token, { page, limit, q, sortField: sort, sortOrder: orderValue });
        } else {
            await fetchPatients(token, { page, limit, q, sortField: sort, sortOrder: orderValue });
        }
        if (asPageLoad) {
            loadErrorMessage.value = '';
        }
        return true;
    } catch (error) {
        logAppError('Erreur lors de la récupération des patients:', error);
        if (asPageLoad) {
            loadErrorMessage.value = 'Impossible de charger la liste des patients.';
        }
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de récupérer les patients.', life: 3000 });
        return false;
    }
};

const loadVisibilityPolicy = async ({ asPageLoad = false } = {}) => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
        return true;
    } catch (error) {
        logAppError('Erreur chargement politique visibilité patients', error);
        hidePatientPhoneForMedecins.value = false;
        if (asPageLoad) {
            loadErrorMessage.value = 'Impossible de charger les paramètres de visibilité des patients.';
        }
        return false;
    }
};

const loadOverviewStats = async () => {
    statsLoading.value = true;
    try {
        overviewStats.value = await fetchPatientsOverviewStats(token, { medecinOnly: isMedecin.value });
    } catch (error) {
        logAppError('Erreur chargement statistiques patients', error);
    } finally {
        statsLoading.value = false;
    }
};

const initializePage = async () => {
    initializingPage.value = true;
    loadErrorMessage.value = '';
    try {
        const [visibilityOk, patientsOk] = await Promise.all([loadVisibilityPolicy({ asPageLoad: true }), loadPatients({ page: 1, limit: rowsPerPage.value, asPageLoad: true }), loadOverviewStats(), assurancesStore.load(token).catch(() => [])]);
        if (!visibilityOk && !patientsOk && !loadErrorMessage.value) {
            loadErrorMessage.value = 'Impossible de charger les données de la page patients.';
        }
    } finally {
        initializingPage.value = false;
    }
};

const retryLoadPage = async () => {
    await initializePage();
};

onMounted(async () => {
    await initializePage();
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
        URGENT: 'danger',
        NORMAL: 'success',
        CONTROLE: 'info',
        SUIVI: 'warning'
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
    loadOverviewStats();
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
        logAppError('Erreur lors de la vérification des consultations actives', error);
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
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'La consultation en cours a été supprimée.', life: 3000 });
        const patient = activeConsultWarnPatient.value;
        closeActiveConsultWarn();
        await loadPatients();
        if (patient) {
            openConsultation(patient);
        }
    } catch (error) {
        logAppError('Erreur lors de la suppression de la consultation active', error);
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

const patientActionMenu = ref(null);
const patientActionMenuTarget = ref(null);

const patientActionMenuItems = computed(() => {
    const patient = patientActionMenuTarget.value;
    if (!patient) return [];

    return [
        {
            label: 'Voir dossier médical',
            icon: 'pi pi-eye',
            command: () => openDossier(patient)
        },
        {
            label: 'Nouveau rendez-vous',
            icon: 'fas fa-calendar-plus',
            command: () => openRendezVous(patient)
        },
        {
            label: 'Service cabinet',
            icon: 'pi pi-building',
            command: () => openCabinetService(patient)
        },
        { separator: true },
        {
            label: 'Supprimer (corbeille)',
            icon: 'pi pi-trash',
            class: 'text-red-500',
            command: () => openDeletePatientDialog(patient)
        }
    ];
});

const togglePatientActionMenu = (event, patient) => {
    patientActionMenuTarget.value = patient;
    nextTick(() => patientActionMenu.value?.toggle(event));
};

const openDeletePatientDialog = (patient) => {
    patientToDelete.value = patient;
    showDeletePatientDialog.value = true;
};

const closeDeletePatientDialog = () => {
    showDeletePatientDialog.value = false;
    patientToDelete.value = null;
};

const confirmDeletePatient = async () => {
    if (!patientToDelete.value?.id || deletingPatientId.value) return;

    deletingPatientId.value = patientToDelete.value.id;
    try {
        const res = await deletePatient(patientToDelete.value.id, token);
        if (!res?.success) {
            throw new Error(res?.message || 'Suppression impossible');
        }

        toast.add({ severity: 'success', summary: 'Corbeille', detail: 'Patient déplacé dans la corbeille.', life: 3000 });
        closeDeletePatientDialog();
        await loadPatients({
            page: Math.floor(first.value / rowsPerPage.value) + 1,
            limit: rowsPerPage.value,
            q: searchQuery.value,
            sort: sortField.value,
            order: sortOrder.value
        });

        if (showTrashDialog.value) {
            await loadTrashPatients({
                page: Math.floor(trashFirst.value / trashRowsPerPage.value) + 1,
                limit: trashRowsPerPage.value,
                q: trashSearch.value
            });
        }
        await loadOverviewStats();
    } catch (error) {
        logAppError('Erreur suppression patient', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer ce patient.', life: 3000 });
    } finally {
        deletingPatientId.value = null;
    }
};

const loadTrashPatients = async ({ page = 1, limit = trashRowsPerPage.value, q = trashSearch.value } = {}) => {
    trashLoading.value = true;
    try {
        const res = await fetchPatientsTrash(token, { page, limit, q });
        trashPatients.value = Array.isArray(res?.items) ? res.items : [];
        trashTotalRecords.value = res?.total ?? trashPatients.value.length;
    } catch (error) {
        logAppError('Erreur chargement corbeille', error);
        toast.add({ severity: 'error', summary: 'Corbeille', detail: 'Impossible de charger la corbeille.', life: 3000 });
    } finally {
        trashLoading.value = false;
    }
};

const openTrashDialog = async () => {
    showTrashDialog.value = true;
    trashFirst.value = 0;
    await loadTrashPatients({ page: 1, limit: trashRowsPerPage.value, q: trashSearch.value });
};

const handleTrashPage = (event) => {
    trashFirst.value = event.first;
    trashRowsPerPage.value = event.rows;
    const page = Math.floor(event.first / event.rows) + 1;
    loadTrashPatients({ page, limit: event.rows, q: trashSearch.value });
};

const restorePatientFromTrash = async (patient) => {
    if (!patient?.id || restoringPatientId.value) return;

    restoringPatientId.value = patient.id;
    try {
        const res = await restorePatient(patient.id, token);
        if (!res?.success) {
            throw new Error(res?.message || 'Restauration impossible');
        }

        toast.add({ severity: 'success', summary: 'Corbeille', detail: 'Patient restauré avec succès.', life: 3000 });
        await loadTrashPatients({
            page: Math.floor(trashFirst.value / trashRowsPerPage.value) + 1,
            limit: trashRowsPerPage.value,
            q: trashSearch.value
        });
        await loadPatients({
            page: Math.floor(first.value / rowsPerPage.value) + 1,
            limit: rowsPerPage.value,
            q: searchQuery.value,
            sort: sortField.value,
            order: sortOrder.value
        });
        await loadOverviewStats();
    } catch (error) {
        logAppError('Erreur restauration patient', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de restaurer ce patient.', life: 3000 });
    } finally {
        restoringPatientId.value = null;
    }
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

watch(trashSearch, () => {
    trashFirst.value = 0;
    if (!showTrashDialog.value) return;
    if (trashSearchTimeout) clearTimeout(trashSearchTimeout);
    trashSearchTimeout = setTimeout(() => {
        loadTrashPatients({ page: 1, limit: trashRowsPerPage.value, q: trashSearch.value });
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

const hasOpenPatientDialog = computed(() => showPatientDialog.value || showConsultationDialog.value || showRdvDialog.value || showCabinetServiceDialog.value || showActiveConsultWarn.value);

const openCabinetService = (patient) => {
    cabinetServicePatient.value = patient;
    showCabinetServiceDialog.value = true;
};

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

const prepareGuidedTourDemo = async ({ taskId = 'overview', variantId = null } = {}) => {
    guidedTourTableState = captureTableState();
    const scenario = resolvePatientsTourMockScenario(taskId, variantId, 'static');

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

const findTourDuplicateConsultationPatient = () => patients.value.find((patient) => Number(patient?.derniereConsultation?.statut) === 0) || patients.value[0] || null;

const openTourConsultationWarning = async (variantId = 'blocked-no-fiche') => {
    resetTourDialogs();
    await nextTick();
    await wait();

    const patient = getPatientsTourMockActivePatient() || findTourDuplicateConsultationPatient();
    if (!patient?.id) {
        return;
    }

    if (variantId === 'blocked-with-fiche') {
        activeConsultInfo.value = {
            hasActive: true,
            consultationId: 5001,
            hasFiche: true
        };
        activeConsultWarnPatient.value = patient;
        showActiveConsultWarn.value = true;
        await nextTick();
        await wait();
        return;
    }

    await openConsultation(patient);
    await nextTick();
    await wait();
};

const openTourEditPatientDialog = async () => {
    const patient = patients.value[0] || null;
    if (!patient) return;
    resetTourDialogs();
    await nextTick();
    editingPatient.value = patient;
    showPatientDialog.value = true;
    await nextTick();
};

const switchPatientFormTab = async (tab = 'personal') => {
    patientFormRef.value?.switchTab?.(tab);
    await nextTick();
    await wait();
};

const openTourTrashDialog = async () => {
    resetTourDialogs();
    await nextTick();
    await openTrashDialog();
    await nextTick();
    await wait();
};

const hasActiveInsuranceTab = () => (assurancesStore.items || []).some((item) => item?.actif !== false);

const { isGuidedTourStarting } = useGuidedTour({
    routeName: 'patients-liste',
    isLoading: () => loading.value || initializingPage.value,
    hasOpenDialogs: () => hasOpenPatientDialog.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => ({
        hasPatients: patients.value.length > 0,
        isMedecin: isMedecin.value,
        hasInsuranceTab: hasActiveInsuranceTab(),
        openCreatePatientDialog: openCreatePatient,
        openEditPatientDialog: openTourEditPatientDialog,
        openRendezVousDialog: () => openRendezVous(),
        openConsultationDialog: () => openConsultation(),
        openDuplicateConsultationDialog: openTourConsultationWarning,
        openTrashDialog: openTourTrashDialog,
        switchPatientFormTab,
        closeAllDialogs: resetTourDialogs
    }),
    loadingMessage: 'Attendez la fin du chargement des patients avant de lancer le tour.',
    dialogsMessage: 'Fermez d abord les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour guide sur la page patients.'
});

onBeforeUnmount(() => {
    if (highlightTimeout) clearTimeout(highlightTimeout);
    if (searchTimeout) clearTimeout(searchTimeout);
    if (trashSearchTimeout) clearTimeout(trashSearchTimeout);
    deactivatePatientsTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});
</script>

<template>
    <PageShell>
        <template #header>
            <PageHeader
                title="Gestion des Patients"
                subtitle="Gérez les dossiers médicaux et les consultations de vos patients"
                icon="fas fa-user-injured"
                tour-id="patients-list.header"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <div class="flex flex-row flex-wrap gap-3 w-full md:w-auto" data-tour="patients-list.toolbar">
                        <Button
                            label="Corbeille"
                            icon="pi pi-trash"
                            severity="secondary"
                            data-tour="patients-list.trash-button"
                            class="sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 px-5 py-2.5 rounded-xl font-medium"
                            @click="openTrashDialog"
                            :pt="{ label: { class: 'hidden sm:inline' } }"
                        />
                        <Button
                            label="Nouveau rendez-vous"
                            icon="fas fa-calendar-plus"
                            severity="warn"
                            data-tour="patients-list.rdv-button"
                            class="sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-blue-500 to-blue-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                            @click="openRendezVous()"
                            :pt="{ label: { class: 'hidden sm:inline' } }"
                        />
                        <Button
                            v-if="!isMedecin"
                            label="Nouvelle consultation"
                            severity="success"
                            icon="fas fa-stethoscope"
                            data-tour="patients-list.consultation-button"
                            class="sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-green-500 to-green-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                            :loading="toolbarConsultLoading"
                            @click="openConsultation()"
                            :pt="{ label: { class: 'hidden sm:inline' } }"
                        />
                        <Button
                            label="Ajouter un patient"
                            icon="fas fa-plus"
                            data-tour="patients-list.add-patient-button"
                            class="sm:w-auto shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                            @click="openCreatePatient"
                            :pt="{ label: { class: 'hidden sm:inline' } }"
                        />
                    </div>
                </template>
            </PageHeader>
        </template>

        <template #toolbar>
            <div v-if="!loadErrorMessage" class="w-full sm:max-w-xl" data-tour="patients-list.search">
                <label class="block text-sm md:text-base font-medium text-surface-700 dark:text-surface-300 mb-2"> Rechercher un patient </label>
                <IconField class="w-full relative">
                    <InputIcon class="fas fa-search text-surface-400" />
                    <InputText
                        v-model="searchQuery"
                        placeholder="Nom, prénom, téléphone, adresse..."
                        class="w-full p-3 md:p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                    />
                </IconField>
            </div>
        </template>

        <div v-if="loadErrorMessage" class="flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                <i class="pi pi-exclamation-triangle text-2xl"></i>
            </div>
            <div class="text-center">
                <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">Chargement interrompu</p>
                <p class="text-sm text-amber-700/90 dark:text-amber-300/90">{{ loadErrorMessage }}</p>
            </div>
            <Button icon="pi pi-refresh" label="Réessayer" severity="warning" @click="retryLoadPage" />
        </div>

        <template v-else>
            <PageSection
                title="Liste des Patients"
                :subtitle="`${totalRecords || patients.length} patient(s) au total`"
                tour-id="patients-list.table"
            >
                <div class="page-table-scroll">
                    <DataTable
                        :value="patients"
                        dataKey="id"
                        :loading="loading || initializingPage"
                        :paginator="true"
                        lazy
                        :rows="rowsPerPage"
                        :rowsPerPageOptions="[5, 10, 20, 50]"
                        :first="first"
                        :totalRecords="totalRecords"
                        @page="handlePage"
                        @sort="handleSort"
                        :sortField="sortField"
                        :sortOrder="sortOrder"
                        :rowClass="rowClass"
                        class="rounded-none border-0"
                        :pt="{
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
                                class: ['hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors', data.derniereConsultation?.statut === 'URGENT' && 'bg-red-50/50 dark:bg-red-900/10']
                            }),
                            paginator: {
                                class: 'px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
                            }
                        }"
                    >
                        <Column field="fullname" header="Nom & Prénom" sortable>
                            <template #body="{ data }">
                                <div class="flex items-center gap-3">
                                    <PatientAvatar :patient="data" size-class="w-10 h-10" text-class="font-semibold" />
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="font-semibold text-surface-900 dark:text-surface-100">
                                                {{ data.fullname || `${data.prenom ?? ''} ${data.nom ?? ''}`.trim() || data.nom }}
                                            </span>
                                            <i v-if="Number(data.impayees || 0) > 0" v-tooltip.top="`Reliquat : ${Number(data.impayees || 0).toLocaleString('fr-FR')} FCFA`" class="pi pi-wallet text-sm text-red-500"></i>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            <Tag :value="data.sexe" :severity="data.sexe === 'M' ? 'info' : 'secondary'" class="px-2 py-0.5 text-xs rounded-full" />
                                            <Tag
                                                v-if="data.insuranceProfile?.assurance?.nom || data.insuranceProfile?.assurance?.code"
                                                :value="`Assuré${data.insuranceProfile?.assurance?.nom ? ` • ${data.insuranceProfile.assurance.nom}` : ''}`"
                                                severity="success"
                                                class="px-2 py-0.5 text-xs rounded-full"
                                            />
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

                        <Column field="telephone" header="Téléphone" sortable headerClass="hidden md:table-cell" bodyClass="hidden md:table-cell">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-phone text-surface-400"></i>
                                    <span class="font-mono text-surface-900 dark:text-surface-100">{{ shouldHidePatientPhoneForMedecin ? "Masqué par l'administrateur" : data.telephone }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column field="adresse" header="Adresse" sortable headerClass="hidden lg:table-cell" bodyClass="hidden lg:table-cell">
                            <template #body="{ data }">
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-map-marker text-surface-400"></i>
                                    <span class="text-surface-700 dark:text-surface-300 truncate max-w-[200px]">{{ data.adresse || '—' }}</span>
                                </div>
                            </template>
                        </Column>

                        <Column header="Dernière consultation" sortField="derniereConsultation.date" sortable headerClass="hidden xl:table-cell" bodyClass="hidden xl:table-cell">
                            <template #body="{ data }">
                                <div v-if="data.derniereConsultation" class="space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="font-semibold text-surface-900 dark:text-surface-100">
                                            {{ formatConsultationDate(data.derniereConsultation.date) }}
                                        </span>
                                        <Tag :value="data.derniereConsultation.statut" :severity="getConsultationSeverity(data.derniereConsultation.statut)" class="px-2 py-0.5 text-xs rounded-full" />
                                    </div>
                                    <p class="text-sm text-surface-600 dark:text-surface-400 line-clamp-2">
                                        {{ data.derniereConsultation.motif || '—' }}
                                    </p>
                                </div>
                                <div v-else class="flex flex-col items-center justify-center p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg">
                                    <i class="pi pi-info-circle text-surface-400 mb-2"></i>
                                    <span class="text-sm text-surface-500">Aucune consultation</span>
                                </div>
                            </template>
                        </Column>

                        <Column header="Actions" :style="{ minWidth: '7rem', width: '7rem' }">
                            <template #body="{ data }">
                                <div class="flex items-center gap-1" :data-tour="data.id === patients[0]?.id ? 'patients-list.row-actions' : null">
                                    <Button
                                        v-if="!isMedecin"
                                        icon="fas fa-stethoscope"
                                        severity="success"
                                        text
                                        rounded
                                        v-tooltip.top="'Nouvelle consultation'"
                                        class="hover:bg-green-50 dark:hover:bg-green-900/20"
                                        @click="openConsultation(data)"
                                        :loading="consultationLoading[data.id] === true"
                                    />
                                    <Button icon="pi pi-pencil" severity="secondary" text rounded v-tooltip.top="'Modifier patient'" class="hover:bg-surface-100 dark:hover:bg-surface-700" @click="openEditPatient(data)" />
                                    <Button
                                        icon="pi pi-ellipsis-v"
                                        severity="secondary"
                                        text
                                        rounded
                                        v-tooltip.top="'Autres actions'"
                                        class="hover:bg-surface-100 dark:hover:bg-surface-700"
                                        aria-haspopup="true"
                                        aria-controls="patient-row-actions-menu"
                                        @click="togglePatientActionMenu($event, data)"
                                    />
                                </div>
                            </template>
                        </Column>

                        <template #footer>
                            <div class="px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800 flex items-center justify-between">
                                <div class="text-sm text-surface-600 dark:text-surface-400">{{ totalRecords || patients.length }} patient(s) retrouvés (s)</div>
                                <div class="flex items-center gap-3" data-tour="patients-list.export">
                                    <Button icon="pi pi-download" severity="secondary" text size="small" label="Exporter" class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400" @click="printPatients" />
                                </div>
                            </div>
                        </template>

                        <template #empty>
                            <div class="text-center py-16">
                                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                                    <i class="fas fa-user-injured text-4xl text-surface-400"></i>
                                </div>
                                <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">Aucun patient trouvé</h4>
                                <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                                    {{ searchQuery ? 'Aucun résultat ne correspond à votre recherche.' : 'Commencez par ajouter votre premier patient.' }}
                                </p>
                                <div class="flex gap-3 justify-center">
                                    <Button v-if="!searchQuery" icon="fas fa-plus" label="Ajouter un patient" @click="openCreatePatient" class="bg-gradient-to-r from-primary-500 to-primary-600 border-0" />
                                    <Button v-else icon="pi pi-filter-slash" label="Réinitialiser la recherche" severity="secondary" outlined @click="searchQuery = ''" />
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
            </PageSection>

            <!-- Stats Overview -->
            <div class="page-kpi-grid" data-tour="patients-list.stats">
                <div class="page-kpi-card bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 border-blue-200/50 dark:border-blue-800/50">
                    <div>
                        <p class="page-kpi-label text-blue-700 dark:text-blue-300">Total Patients</p>
                        <p class="page-kpi-value text-blue-900 dark:text-blue-100">
                            {{ statsLoading ? '—' : overviewStats.totalPatients || totalRecords || patients.length }}
                        </p>
                    </div>
                    <i class="fas fa-users page-kpi-icon text-blue-500"></i>
                </div>

                <div class="page-kpi-card bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20 border-green-200/50 dark:border-green-800/50">
                    <div>
                        <p class="page-kpi-label text-green-700 dark:text-green-300">Consultations aujourd'hui</p>
                        <p class="page-kpi-value text-green-900 dark:text-green-100">
                            {{ statsLoading ? '—' : overviewStats.consultationsToday }}
                        </p>
                    </div>
                    <i class="fas fa-stethoscope page-kpi-icon text-green-500"></i>
                </div>

                <div class="page-kpi-card bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 border-amber-200/50 dark:border-amber-800/50">
                    <div>
                        <p class="page-kpi-label text-amber-700 dark:text-amber-300">Rendez-vous à venir</p>
                        <p class="page-kpi-value text-amber-900 dark:text-amber-100">
                            {{ statsLoading ? '—' : overviewStats.upcomingAppointments }}
                        </p>
                    </div>
                    <i class="fas fa-calendar-day page-kpi-icon text-amber-500"></i>
                </div>

                <div class="page-kpi-card bg-gradient-to-br from-purple-50 to-purple-100/50 dark:from-purple-900/20 dark:to-purple-800/20 border-purple-200/50 dark:border-purple-800/50">
                    <div>
                        <p class="page-kpi-label text-purple-700 dark:text-purple-300">Nouveaux ce mois</p>
                        <p class="page-kpi-value text-purple-900 dark:text-purple-100">
                            {{ statsLoading ? '—' : overviewStats.newPatientsThisMonth }}
                        </p>
                    </div>
                    <i class="fas fa-chart-line page-kpi-icon text-purple-500"></i>
                </div>
            </div>

            <PatientsReferralStats class="mb-6 md:mb-8" :referrals="overviewStats.referrals" :loading="statsLoading" />
        </template>

        <!-- Dialogs -->
        <Menu id="patient-row-actions-menu" ref="patientActionMenu" :model="patientActionMenuItems" popup />
        <AppDialog
            v-model:visible="showPatientDialog"
            :title="editingPatient ? 'Modifier le patient' : 'Ajouter un patient'"
            :subtitle="editingPatient ? 'Mettez à jour les informations du patient' : 'Créez un nouveau dossier patient'"
            :icon="editingPatient ? 'fas fa-user-edit' : 'fas fa-user-plus'"
            icon-tone="primary"
            size="lg"
            :loading="Boolean(patientFormRef?.loading)"
            @cancel="showPatientDialog = false"
        >
            <div data-tour="patients-list.dialog.patient">
                <FormPatient
                    ref="patientFormRef"
                    hide-actions
                    :patient="editingPatient"
                    @saved="handlePatientSaved"
                    @cancel="showPatientDialog = false"
                />
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 w-full" data-tour="patients-form.actions">
                    <Button type="button" label="Annuler" severity="secondary" text class="rounded-xl px-5" :disabled="Boolean(patientFormRef?.loading)" @click="showPatientDialog = false" />
                    <Button
                        type="button"
                        :label="patientFormRef?.isEdit ? 'Mettre à jour' : 'Créer'"
                        icon="pi pi-check"
                        class="rounded-xl px-5"
                        :loading="Boolean(patientFormRef?.loading)"
                        @click="patientFormRef?.submit()"
                    />
                </div>
            </template>
        </AppDialog>

        <CreateConsultationDialog
            v-model:visible="showConsultationDialog"
            :patient="consultationPatient"
            :patient-id="consultationPatient?.id"
            content-tour-id="patients-list.dialog.consultation"
            @saved="handleConsultationSaved"
        />

        <AppDialog
            v-model:visible="showActiveConsultWarn"
            title="Consultation en cours"
            icon="fas fa-exclamation-triangle"
            icon-tone="warning"
            size="md"
            :show-footer="true"
        >
            <div data-tour="patients-list.dialog.active-warning">
                <p class="text-surface-700 dark:text-surface-300 mb-4">Une consultation est déjà ouverte pour ce patient. Clôturez-la ou continuez-la avant d'en créer une nouvelle.</p>

                <p v-if="!activeConsultInfo.hasFiche" class="text-sm text-surface-600 dark:text-surface-400 mb-4">Si cette consultation a été ouverte par erreur, vous pouvez l annuler directement depuis ce dialogue.</p>

                <div v-if="activeConsultInfo.hasFiche" class="flex items-center gap-2 p-3 bg-surface-50 dark:bg-surface-800/50 rounded-lg mb-4">
                    <i class="pi pi-info-circle text-surface-500"></i>
                    <span class="text-sm text-surface-600 dark:text-surface-400"> Cette consultation est liée à une fiche : elle ne peut pas être supprimée. </span>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2 w-full">
                    <Button label="Compris" severity="secondary" @click="closeActiveConsultWarn" class="rounded-xl px-5" />
                    <Button v-if="!activeConsultInfo.hasFiche" label="Annuler la consultation" icon="pi pi-times" severity="danger" @click="cancelActiveConsultation" class="rounded-xl px-5" />
                </div>
            </template>
        </AppDialog>

        <CabinetServiceDialog
            v-model:visible="showCabinetServiceDialog"
            :patient-id="cabinetServicePatient?.id"
            :patient-name="cabinetServicePatient?.fullname || `${cabinetServicePatient?.nom || ''} ${cabinetServicePatient?.prenom || ''}`.trim()"
        />
        <AppDialog
            v-model:visible="showRdvDialog"
            title="Nouveau rendez-vous"
            :subtitle="rdvPatient?.fullname || rdvPatient?.nom || 'Nouveau patient'"
            icon="fas fa-calendar-plus"
            icon-tone="info"
            size="lg"
            :loading="Boolean(rdvFormRef?.loading)"
            @cancel="showRdvDialog = false"
        >
            <div data-tour="patients-list.dialog.rdv">
                <FormRendezVous
                    ref="rdvFormRef"
                    hide-actions
                    :patient="rdvPatient"
                    :patient-id="rdvPatient?.id"
                    @saved="handleRdvSaved"
                    @cancel="showRdvDialog = false"
                />
            </div>
            <template #footer>
                <div class="flex justify-end gap-2 w-full" data-tour="patients-form-rdv.actions">
                    <Button type="button" label="Annuler" severity="secondary" text class="rounded-xl px-5" :disabled="Boolean(rdvFormRef?.loading)" @click="showRdvDialog = false" />
                    <Button type="button" label="Créer" icon="pi pi-check" class="rounded-xl px-5" :loading="Boolean(rdvFormRef?.loading)" @click="rdvFormRef?.submit()" />
                </div>
            </template>
        </AppDialog>

        <AppDialog
            v-model:visible="showDeletePatientDialog"
            title="Supprimer le patient"
            subtitle="Le patient sera déplacé dans la corbeille"
            icon="pi pi-trash"
            icon-tone="danger"
            size="sm"
            :loading="deletingPatientId === patientToDelete?.id"
            cancel-label="Annuler"
            confirm-label="Supprimer"
            confirm-icon="pi pi-trash"
            confirm-severity="danger"
            @cancel="closeDeletePatientDialog"
            @confirm="confirmDeletePatient"
        >
            <p class="text-surface-700 dark:text-surface-300">
                Voulez-vous déplacer
                <span class="font-semibold">{{ patientToDelete?.fullname || patientToDelete?.nom }}</span>
                vers la corbeille ?
            </p>
        </AppDialog>

        <AppDialog
            v-model:visible="showTrashDialog"
            title="Corbeille des patients"
            subtitle="Restaurez un patient supprimé par erreur"
            icon="pi pi-trash"
            icon-tone="neutral"
            size="xl"
            maximizable
            :show-footer="false"
        >
            <div class="space-y-4" data-tour="patients-list.dialog.trash">
                <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <div class="w-full sm:max-w-md">
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">Rechercher dans la corbeille</label>
                        <IconField class="w-full relative">
                            <InputIcon class="fas fa-search text-surface-400" />
                            <InputText v-model="trashSearch" placeholder="Nom, prénom, téléphone..." class="w-full p-3 rounded-xl" />
                        </IconField>
                    </div>
                </div>

                <DataTable
                    :value="trashPatients"
                    dataKey="id"
                    :loading="trashLoading"
                    :paginator="true"
                    lazy
                    :rows="trashRowsPerPage"
                    :rowsPerPageOptions="[5, 10, 20]"
                    :first="trashFirst"
                    :totalRecords="trashTotalRecords"
                    @page="handleTrashPage"
                    class="rounded-xl border border-surface-200 dark:border-surface-700"
                >
                    <Column field="fullname" header="Patient">
                        <template #body="{ data }">
                            <div class="flex flex-col">
                                <span class="font-semibold text-surface-900 dark:text-surface-100">{{ data.fullname || `${data.prenom ?? ''} ${data.nom ?? ''}`.trim() }}</span>
                                <span class="text-xs text-surface-500">{{ data.telephone || '—' }}</span>
                            </div>
                        </template>
                    </Column>

                    <Column field="deletedAt" header="Supprimé le">
                        <template #body="{ data }">
                            <span class="text-sm text-surface-700 dark:text-surface-300">{{ data.deletedAt || '—' }}</span>
                        </template>
                    </Column>

                    <Column header="Actions" :style="{ width: '180px' }">
                        <template #body="{ data }">
                            <Button label="Restaurer" icon="pi pi-replay" severity="success" text :loading="restoringPatientId === data.id" @click="restorePatientFromTrash(data)" />
                        </template>
                    </Column>

                    <template #empty>
                        <div class="py-10 text-center text-surface-500">Aucun patient dans la corbeille.</div>
                    </template>
                </DataTable>
            </div>
        </AppDialog>
    </PageShell>
</template>

<style scoped>
:deep(.row-highlight),
:deep(.row-highlight > td) {
    animation: flash-green 0.6s ease-in-out 0s 4 alternate;
    background-color: #d1fae5 !important;
}

:deep(.appdark .row-highlight),
:deep(.appdark .row-highlight > td) {
    animation: flash-green-dark 0.6s ease-in-out 0s 4 alternate;
    background-color: #064e3b !important;
}

@keyframes flash-green {
    0% {
        background-color: #d1fae5;
    }

    100% {
        background-color: #bbf7d0;
    }
}

@keyframes flash-green-dark {
    0% {
        background-color: #064e3b;
    }

    100% {
        background-color: #047857;
    }
}
</style>
