<script setup>
import ConsultationDetailsDialog from '@/components/consultations/ConsultationDetailsDialog.vue';
import FactureModal from '@/components/consultations/FactureModal.vue';
import QuickClotureConsultationDialog from '@/components/consultations/QuickClotureConsultationDialog.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';
import PrintDataTablePage from '@/components/print/PrintDataTablePage.vue';
import { usePrinter } from '@/composables/usePrinter';
import {
    activateConsultationsTourMock,
    deactivateConsultationsTourMock,
    resetConsultationsTourMockData
} from '@/services/consultationsTourMock';

import {
    cancelConsultation,
    fetchConsultationDetails,
    fetchConsultationInvoice,
    fetchConsultationsByDate,
    updateConsultationInvoice
} from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { activatePatientsTourMock, deactivatePatientsTourMock, resetPatientsTourMockData } from '@/services/patientsTourMock';

import { useAuthStore } from '@/stores/auth';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createConsultationsTableTour } from '@/tours/consultationsTableTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import { FilterMatchMode } from '@primevue/core/api';
import Button from 'primevue/button';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const confirm = useConfirm();
const toast = useToast();
const auth = useAuthStore();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const consultations = ref([]);
const loading = ref(false);
const selectedDate = ref(new Date());
const filters = ref({
    global: { value: null, matchMode: FilterMatchMode.CONTAINS }
});

const canceling = ref({});
const factureLoading = ref({});
const factureSaving = ref(false);
const factureDialogVisible = ref(false);
const factureLines = ref([]);
const factureConsultation = ref(null);

const detailsDialogVisible = ref(false);
const detailsLoading = ref(false);
const detailData = ref(null);
const detailsLoadingId = ref(null);
const showCreateDialog = ref(false);
const quickMenus = {};
const quickDialogVisible = ref(false);
const quickDialogConsultation = ref(null);
const quickDialogActionMode = ref('continue');
const isGuidedTourStarting = ref(false);
const allowReceptionQuickClose = ref(true);
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const headerTitle = computed(() => `Consultations du ${formatDisplayDate(selectedDate.value)}`);
const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isReception = computed(() => Boolean(auth.user?.roles?.includes('ROLE_RECEPTION') || auth.user?.roles?.includes('ROLE_RECEPTIONNISTE')));
const canUseQuickActions = computed(() => !isReception.value || allowReceptionQuickClose.value);
const totalCountLabel = computed(() => (consultations.value?.length ? `${consultations.value.length} consultation(s)` : ''));

const filterGlobalValue = computed({
    get: () => filters.value.global?.value ?? '',
    set: (val) => {
        filters.value = { ...filters.value, global: { ...filters.value.global, value: val } };
    }
});

const printConsultations = async () => {
    const rows = (consultations.value || []).map((c) => ({
        patient: patientLabel(c) || '—',
        medecin: c?.medecin || '—',
        dateCreation: formatDateTime(c?.createdAt),
        statut: stateLabel(c).label || '—'
    }));

    await printComponent(PrintDataTablePage, {
        title: 'Liste des Consultations',
        subtitle: headerTitle.value,
        columns: [
            { key: 'patient', label: 'Patient' },
            { key: 'medecin', label: 'Médecin' },
            { key: 'dateCreation', label: 'Date création' },
            { key: 'statut', label: 'Statut' }
        ],
        rows
    });
};

const formatDateToApi = (date) => {
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return '';
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    return `${yyyy}-${mm}-${dd}`;
};

const formatDisplayDate = (date) => {
    const d = new Date(date);
    if (Number.isNaN(d.getTime())) return '';
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const yyyy = d.getFullYear();
    return `${dd}/${mm}/${yyyy}`;
};

const formatDateTime = (value) => {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
};

const patientLabel = (row) => {
    if (!row) return '';
    if (typeof row.patientName === 'string' && row.patientName.trim()) return row.patientName;
    if (typeof row.patient === 'string') return row.patient;
    const patientObj = row.patient ?? {};
    const fullname = `${patientObj.prenom ?? ''} ${patientObj.nom ?? ''}`.trim();
    return fullname || patientObj.nom || '';
};

const stateLabel = (row) => {
    if (row?.state === 1) return { label: 'Clôturée', severity: 'success' };
    return { label: 'En cours', severity: 'warning' };
};

const loadConsultations = async () => {
    loading.value = true;
    try {
        const dateParam = formatDateToApi(selectedDate.value);
        consultations.value = await fetchConsultationsByDate(dateParam, token);
    } catch (error) {
        console.error('Erreur lors du chargement des consultations du jour', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les consultations.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const loadQuickClosePolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        allowReceptionQuickClose.value = settings?.allowReceptionQuickCloseConsultation !== false;
    } catch (error) {
        console.error('Erreur chargement politique de clôturation rapide', error);
        allowReceptionQuickClose.value = true;
    }
};

onMounted(() => {
    loadQuickClosePolicy();
    loadConsultations();
    window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
    window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    deactivateConsultationsTourMock();
    deactivatePatientsTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});

const onDateChange = () => {
    loadConsultations();
};

const rowClass = (data) => (data.state === 1 ? 'row-success' : '');

const isLinked = (consultation) => Boolean(consultation?.ficheId);
const patientHasFiche = (consultation) => Boolean(consultation?.hasFiche || consultation?.lastFicheId);
const isClosed = (consultation) => Number(consultation?.state) === 1;

const setQuickMenuRef = (id, el) => {
    if (!id) return;
    if (!el) {
        delete quickMenus[id];
        return;
    }
    quickMenus[id] = el;
};

const openQuickDialog = (consultation, mode) => {
    if (!consultation?.id || isClosed(consultation)) return;
    quickDialogConsultation.value = consultation;
    quickDialogActionMode.value = mode;
    quickDialogVisible.value = true;
};

const quickActionItems = (consultation) => {
    const linked = isLinked(consultation);
    const hasFiche = patientHasFiche(consultation);
    const closed = isClosed(consultation);

    return [
        {
            label: 'Continuer avec la dernière fiche',
            icon: 'pi pi-history',
            disabled: closed || linked || !hasFiche,
            command: () => openQuickDialog(consultation, 'continue-last')
        },
        {
            label: 'Continuer',
            icon: 'pi pi-forward',
            disabled: closed || !linked,
            command: () => openQuickDialog(consultation, 'continue')
        },
        {
            label: 'Nouvelle fiche',
            icon: 'pi pi-plus-circle',
            disabled: closed || linked,
            command: () => openQuickDialog(consultation, 'new-fiche')
        }
    ];
};

const toggleQuickActions = (event, consultation) => {
    if (!consultation?.id) return;
    const menu = quickMenus[consultation.id];
    if (!menu) return;
    menu.toggle(event);
};

const askCancel = (event, consultation) => {
    confirm.require({
        group: 'cancel-consultation',
        target: event?.currentTarget || event?.target,
        message: 'Annuler cette consultation ? Cette action est irréversible.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, annuler',
        rejectLabel: 'Non',
        acceptClass: 'p-button-danger',
        accept: () => handleCancel(consultation)
    });
};

const setCanceling = (id, value) => {
    if (!id) return;
    canceling.value = { ...canceling.value, [id]: value };
};

const handleCancel = async (consultation) => {
    if (!consultation?.id) return;
    setCanceling(consultation.id, true);
    try {
        await cancelConsultation(consultation.id, token);
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'Consultation supprimée.', life: 2500 });
        await loadConsultations();
    } catch (error) {
        console.error('Annulation impossible', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'annuler la consultation.", life: 3000 });
    } finally {
        setCanceling(consultation.id, false);
    }
};

const openDossier = (consultation) => {
    if (!consultation?.patientId) return;
    router.push({ name: 'patients-dossier', params: { patientId: consultation.patientId } });
};

const setFactureLoading = (id, value) => {
    if (!id) return;
    factureLoading.value = { ...factureLoading.value, [id]: value };
};

const openFacture = async (consultation) => {
    if (!consultation?.id) return;
    factureConsultation.value = consultation;
    factureDialogVisible.value = true;
    setFactureLoading(consultation.id, true);
    try {
        factureLines.value = await fetchConsultationInvoice(consultation.id, token);
    } catch (error) {
        console.error('Erreur lors du chargement de la facture', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la facture.', life: 3000 });
        factureDialogVisible.value = false;
    } finally {
        setFactureLoading(consultation.id, false);
    }
};

const handleSaveFacture = async (lines) => {
    if (!factureConsultation.value?.id) return;
    factureSaving.value = true;
    try {
        await updateConsultationInvoice(factureConsultation.value.id, lines, token);
        toast.add({ severity: 'success', summary: 'Facture mise à jour', detail: 'La facture a été enregistrée.', life: 2500 });
        factureDialogVisible.value = false;
        await loadConsultations();
    } catch (error) {
        console.error('Erreur lors de la sauvegarde de la facture', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'enregistrer la facture.", life: 3000 });
    } finally {
        factureSaving.value = false;
    }
};

const closeFactureModal = (visible) => {
    factureDialogVisible.value = visible;
    if (!visible) {
        factureConsultation.value = null;
        factureLines.value = [];
    }
};

const openDetails = async (consultation) => {
    if (!consultation?.id) return;
    detailsDialogVisible.value = true;
    detailsLoading.value = true;
    detailsLoadingId.value = consultation.id;
    detailData.value = null;
    try {
        detailData.value = await fetchConsultationDetails(consultation.id, token);
    } catch (error) {
        console.error('Erreur lors du chargement des détails de consultation', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les détails.', life: 3000 });
        detailsDialogVisible.value = false;
    } finally {
        detailsLoading.value = false;
        detailsLoadingId.value = null;
    }
};

const handleCreateSaved = async () => {
    showCreateDialog.value = false;
    await loadConsultations();
};

const handleQuickDialogDone = async () => {
    quickDialogVisible.value = false;
    quickDialogConsultation.value = null;
    await loadConsultations();
};

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
    window.setTimeout(resolve, ms);
});

const firstOpenConsultation = computed(() => consultations.value.find((c) => !isClosed(c)) || null);
const repriseConsultation = computed(() => consultations.value.find((c) => !isClosed(c) && !isLinked(c) && patientHasFiche(c)) || null);
const linkedConsultation = computed(() => consultations.value.find((c) => !isClosed(c) && isLinked(c)) || null);
const freshConsultation = computed(() => consultations.value.find((c) => !isClosed(c) && !isLinked(c) && !patientHasFiche(c)) || null);
const closedConsultation = computed(() => consultations.value.find((c) => isClosed(c)) || null);
const urgentConsultation = computed(() => consultations.value.find((c) => Boolean(c.urgence)) || null);

const hasOpenDialogs = computed(() => (
    showCreateDialog.value
    || quickDialogVisible.value
    || detailsDialogVisible.value
    || factureDialogVisible.value
));

const resetTourDialogs = () => {
    showCreateDialog.value = false;
    quickDialogVisible.value = false;
    quickDialogConsultation.value = null;
    quickDialogActionMode.value = 'continue';
    detailsDialogVisible.value = false;
    detailsLoading.value = false;
    detailsLoadingId.value = null;
    detailData.value = null;
    factureDialogVisible.value = false;
    factureConsultation.value = null;
    factureLines.value = [];
};

const capturePageState = () => ({
    consultations: cloneValue(consultations.value),
    canceling: cloneValue(canceling.value),
    factureLoading: cloneValue(factureLoading.value),
    selectedDate: selectedDate.value ? new Date(selectedDate.value).toISOString() : null,
    filters: cloneValue(filters.value)
});

const restorePageState = async (state) => {
    if (!state) return;

    consultations.value = cloneValue(state.consultations) || [];
    canceling.value = cloneValue(state.canceling) || {};
    factureLoading.value = cloneValue(state.factureLoading) || {};
    selectedDate.value = state.selectedDate ? new Date(state.selectedDate) : new Date();
    filters.value = cloneValue(state.filters) || {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS }
    };
    await nextTick();
};

const prepareGuidedTourDemo = async () => {
    guidedTourPageState = capturePageState();
    activatePatientsTourMock('static');
    resetPatientsTourMockData('static');
    activateConsultationsTourMock();
    resetConsultationsTourMockData();
    guidedTourDemoActive = true;
    selectedDate.value = new Date();
    filters.value = {
        global: { value: null, matchMode: FilterMatchMode.CONTAINS }
    };

    await loadConsultations();
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
        deactivateConsultationsTourMock();
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

const openTourCreateConsultationDialog = async () => {
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    showCreateDialog.value = true;
    await nextTick();
};

const resolveTourQuickActionMode = (consultation) => {
    if (!consultation) return 'continue';
    if (!isLinked(consultation) && patientHasFiche(consultation)) return 'continue-last';
    if (isLinked(consultation)) return 'continue';
    return 'new-fiche';
};

const openTourQuickDialog = async () => {
    const consultation = repriseConsultation.value || linkedConsultation.value || freshConsultation.value || firstOpenConsultation.value;
    if (!consultation) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi(220);
    quickDialogConsultation.value = consultation;
    quickDialogActionMode.value = resolveTourQuickActionMode(consultation);
    quickDialogVisible.value = true;
    await nextTick();
};

const openTourDetailsDialog = async () => {
    const consultation = closedConsultation.value || firstOpenConsultation.value;
    if (!consultation) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    await openDetails(consultation);
    await nextTick();
};

const openTourFactureDialog = async () => {
    const consultation = closedConsultation.value;
    if (!consultation) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi();
    await openFacture(consultation);
    await nextTick();
};

const handleGuidedTourRequest = async (event) => {
    if (event?.detail?.routeName !== 'consultations-table' || isGuidedTourStarting.value) {
        return;
    }

    if (loading.value) {
        toast.add({
            severity: 'warn',
            summary: 'Aide guidee',
            detail: 'Attendez la fin du chargement des consultations avant de lancer le tour.',
            life: 3000
        });
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
        await cleanupGuidedTourDemo();
        await prepareGuidedTourDemo();
        resetTourDialogs();
        await nextTick();

        const steps = createConsultationsTableTour({
            hasConsultations: consultations.value.length > 0,
            hasOpenConsultation: Boolean(firstOpenConsultation.value),
            hasRepriseCase: Boolean(repriseConsultation.value),
            hasLinkedCase: Boolean(linkedConsultation.value),
            hasFreshCase: Boolean(freshConsultation.value),
            hasClosedCase: Boolean(closedConsultation.value),
            hasUrgentCase: Boolean(urgentConsultation.value),
            isAdmin: isAdmin.value,
            isMedecin: isMedecin.value,
            openCreateConsultationDialog: openTourCreateConsultationDialog,
            openQuickDialog: openTourQuickDialog,
            openDetailsDialog: openTourDetailsDialog,
            openFactureDialog: openTourFactureDialog,
            closeAllDialogs: resetTourDialogs
        });

        await startTourGuide({
            group: 'consultations-table',
            steps,
            onAfterExit: cleanupGuidedTourDemo,
            onFinish: cleanupGuidedTourDemo
        });
    } catch (error) {
        console.error('Erreur lancement guided tour consultations table', error);
        await cleanupGuidedTourDemo();
        toast.add({
            severity: 'error',
            summary: 'Aide guidee',
            detail: 'Impossible de lancer le tour de la table des consultations.',
            life: 3000
        });
    } finally {
        isGuidedTourStarting.value = false;
    }
};

const currentFactureLoading = computed(() => {
    const id = factureConsultation.value?.id;
    return id ? factureLoading.value[id] === true : false;
});

 function formatDateDisplay(date) {
            if (!date) return "Toutes les dates";
            return new Date(date).toLocaleDateString('fr-FR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        
        function getPatientInitials(data) {
            const name = patientLabel(data) || '';
            return name
                .split(' ')
                .map(word => word.charAt(0))
                .join('')
                .toUpperCase()
                .slice(0, 2);
        }
        
        function getMedecinInitials(data) {
            const medecin = data.medecin || '';
            return medecin
                .split(' ')
                .map(word => word.charAt(0))
                .join('')
                .toUpperCase()
                .slice(0, 2);
        }   
        
        function getTimeAgo(date) {
            const now = new Date();
            const then = new Date(date);
            const diffMs = now - then;
            const diffMins = Math.floor(diffMs / (1000 * 60));
            const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
            const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
            
            if (diffMins < 60) return `Il y a ${diffMins} min`;
            if (diffHours < 24) return `Il y a ${diffHours} h`;
            if (diffDays === 1) return 'Hier';
            if (diffDays < 7) return `Il y a ${diffDays} jours`;
            return `Il y a ${Math.floor(diffDays / 7)} semaines`;
        }   
        
        function resetFilters() {
            filterGlobalValue.value = '';
            selectedDate.value = null;
            loadConsultations();
        }
</script>

<template>
    <section class="min-h-screen p-4 md:p-6 lg:p-8 transition-colors duration-300">
        <ConfirmPopup group="cancel-consultation" />
        <!-- Header Section -->
        <div class="mb-6 md:mb-8" data-tour="consultations-table.header">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div class="space-y-2">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-primary-500/10 dark:bg-primary-500/20">
                            <i class="fas fa-clipboard-list text-primary-600 dark:text-primary-400 text-xl"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl lg:text-4xl font-bold text-surface-900 dark:text-surface-50 tracking-tight">
                                Gestion des Consultations
                            </h1>
                            <p class="text-surface-600 dark:text-surface-300 text-sm md:text-base mt-1">
                                {{ headerTitle }}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <Button 
                        v-if="!isMedecin"
                        data-tour="consultations-table.create-button"
                        icon="pi pi-calendar-plus" 
                        label="Nouvelle consultation" 
                        class="shadow-lg hover:shadow-xl transition-all duration-300 bg-gradient-to-r from-primary-500 to-primary-600 border-0 text-white px-5 py-2.5 rounded-xl font-medium"
                        @click="showCreateDialog = true"
                    />
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm mb-6 md:mb-8">
            <!-- Card Header -->
            <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                            Liste des Consultations
                        </h3>
                        <div class="flex items-center gap-3">
                            <div class="flex items-center gap-2 text-sm text-surface-600 dark:text-surface-400">
                                <i class="pi pi-calendar"></i>
                                <span>{{ formatDateDisplay(selectedDate) }}</span>
                            </div>
                            <Tag v-if="totalCountLabel" :value="totalCountLabel" severity="info" class="px-3 py-1 rounded-full font-medium" />
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button 
                            icon="pi pi-download" 
                            severity="secondary" 
                            text 
                            size="small"
                            label="Exporter"
                            class="text-surface-600 dark:text-surface-400 hover:text-primary-600 dark:hover:text-primary-400"
                            @click="printConsultations"
                        />
                        <Button 
                            icon="pi pi-cog" 
                            severity="secondary" 
                            text 
                            size="small"
                            class="text-surface-600 dark:text-surface-400"
                        />
                    </div>
                </div>
            </div>

            <!-- Filters & Controls -->
            <div class="px-5 md:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-surface-0/50 dark:bg-surface-800/30" data-tour="consultations-table.filters">
                <div class="flex flex-col lg:flex-row lg:items-center gap-4">
                    <!-- Search -->
                    <div class="col-6 w-full">
                        <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                            Rechercher une consultation
                        </label>
                        <IconField class="p-input-icon-left w-full">
                            <InputIcon class="pi pi-search text-surface-400"></InputIcon>
                            <InputText 
                                v-model="filterGlobalValue" 
                                placeholder="Patient, médecin, statut..." 
                                class="w-full p-3.5 rounded-xl border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-700/50 focus:ring-2 focus:ring-primary-500/20 transition-all"
                            />
                        </IconField>
                    </div>
                    
                    <!-- Date & Actions -->
                    <div class="flex flex-col sm:flex-row lg:flex-col gap-3 col-6 w-full">
                        <div>
                            <label class="block text-sm font-medium text-surface-700 dark:text-surface-300 mb-2">
                                Date de consultation
                            </label>
                            <div class="flex gap-2">
                                <DatePicker 
                                    v-model="selectedDate" 
                                    dateFormat="dd/mm/yy" 
                                    showIcon
                                    class="flex-1 rounded-xl border-surface-200 dark:border-surface-700 [&_.p-datepicker]:p-3.5 "
                                    @update:modelValue="onDateChange" 
                                />
                                <Button 
                                    icon="pi pi-calendar-times" 
                                    severity="secondary" 
                                    outlined
                                    class="px-3 rounded-xl"
                                    @click="selectedDate = null"
                                />
                                <Button 
                                icon="pi pi-refresh"  
                                :loading="loading" 
                                outlined
                                class="w-full rounded-xl px-5 py-3.5 border-surface-300 dark:border-surface-600 hover:bg-surface-100 dark:hover:bg-surface-700 transition-colors"
                                @click="loadConsultations" 
                            />
                            </div>
                            
                        </div> 
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <DataTable 
                data-tour="consultations-table.table"
                :value="consultations" 
                dataKey="id" 
                :loading="loading" 
                :paginator="true" 
                :rows="10"
                :rowsPerPageOptions="[5, 10, 20, 50]" 
                :filters="filters" 
                v-model:filters="filters" 
                filterDisplay="menu"
                :globalFilterFields="['patientName', 'patient', 'medecin', 'statut']" 
                :rowClass="rowClass"
                class="rounded-none border-0 mx-4"
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
                        class: [
                            'hover:bg-surface-50/50 dark:hover:bg-surface-700/30 transition-colors',
                            data.statut === 'URGENT' && 'bg-red-50/30 dark:bg-red-900/10',
                            data.statut === 'EN_COURS' && 'bg-blue-50/30 dark:bg-blue-900/10'
                        ]
                    }),
                    paginator: {
                        class: 'px-5 py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800'
                    },
                    filterMenu: {
                        class: 'rounded-xl border border-surface-200 dark:border-surface-700 shadow-lg'
                    }
                }"
            >
                <!-- Patient Column -->
                <Column field="patientName" header="Patient" sortable>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-user text-surface-500"></i> 
                        </div>
                    </template>
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-semibold">
                                {{ getPatientInitials(data) }}
                            </div>
                            <div>
                                <span class="font-semibold text-surface-900 dark:text-surface-100">
                                    {{ patientLabel(data) || '—' }}
                                </span>
                                <div v-if="data.patientPhone" class="flex items-center gap-1 mt-1">
                                    <i class="pi pi-phone text-xs text-surface-400"></i>
                                    <span class="text-xs text-surface-500">{{ data.patientPhone }}</span>
                                </div>
                            </div>
                        </div>
                    </template>
                </Column>
                
                <!-- Médecin Column -->
                <Column field="medecin" header="Médecin" sortable>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-user-md text-surface-500"></i> 
                        </div>
                    </template>
                    <template #body="{ data }">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white text-sm">
                                {{ getMedecinInitials(data) }}
                            </div>
                            <span class="font-medium text-surface-900 dark:text-surface-100">
                                {{ data.medecin || '—' }}
                            </span>
                        </div>
                    </template>
                </Column>
                
                <!-- Date Column -->
                <Column field="createdAt" header="Date création" sortable>
                    <template #header>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-calendar text-surface-500"></i> 
                        </div>
                    </template>
                    <template #body="{ data }">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-clock text-surface-400"></i>
                                <span class="font-medium text-surface-900 dark:text-surface-100">
                                    {{ formatDateTime(data.createdAt) }}
                                </span>
                            </div>
                            <!-- <div class="text-xs text-surface-500">
                                {{ getTimeAgo(data.createdAt) }}
                            </div> -->
                        </div>
                    </template>
                </Column>
                
                <!-- Statut Column -->
                <Column header="Statut">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-info-circle text-surface-500"></i> 
                        </div>
                    </template>
                    <template #body="{ data }">
                        <div class="flex flex-col gap-1" :data-tour="data.id === 9202 ? 'consultations-table.status' : null">
                            <Tag 
                                :value="stateLabel(data).label" 
                                :severity="stateLabel(data).severity"
                                class="px-3 py-1.5 rounded-full font-medium shadow-sm"
                            />
                            <div v-if="data.urgence" class="flex items-center gap-1" :data-tour="data.id === 9202 ? 'consultations-table.case-urgent' : null">
                                <i class="pi pi-exclamation-triangle text-xs text-red-500"></i>
                                <span class="text-xs text-red-600 dark:text-red-400">Urgent</span>
                            </div>
                        </div>
                    </template>
                </Column>
                
                <!-- Actions Column -->
                <Column header="Actions" :style="{ minWidth: '220px' }">
                    <template #header>
                        <div class="flex items-center gap-2">
                            <i class="pi pi-cog text-surface-500"></i> 
                        </div>
                    </template>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2" :data-tour="data.id === 9201 ? 'consultations-table.actions' : null">
                            <Button 
                                icon="pi pi-eye" 
                                severity="info" 
                                text 
                                rounded
                                v-tooltip.top="'Voir détails'"
                                class="hover:bg-blue-50 dark:hover:bg-blue-900/20"
                                :loading="detailsLoadingId === data.id"
                                @click="openDetails(data)" 
                            />
                            <Button 
                                icon="pi pi-folder-open" 
                                severity="secondary" 
                                text 
                                rounded
                                v-tooltip.top="'Ouvrir dossier'"
                                class="hover:bg-surface-100 dark:hover:bg-surface-700"
                                @click="openDossier(data)" 
                            />
                            <Button 
                                icon="pi pi-file-edit" 
                                severity="success" 
                                text 
                                rounded
                                v-tooltip.top="'Éditer facture'"
                                class="hover:bg-green-50 dark:hover:bg-green-900/20"
                                :loading="factureLoading[data.id] === true"
                                @click="openFacture(data)" 
                                v-if="isAdmin && data.state === 1"
                            />
                            <Button 
                                icon="pi pi-times" 
                                severity="danger" 
                                text 
                                rounded
                                v-tooltip.top="'Annuler'"
                                class="hover:bg-red-50 dark:hover:bg-red-900/20"
                                :loading="canceling[data.id] === true"
                                @click="askCancel($event, data)" 
                                v-if="data.state !== 1"
                            />

                            <Button
                                v-if="canUseQuickActions"
                                icon="pi pi-bolt"
                                label="Actions rapides"
                                severity="contrast"
                                size="small"
                                outlined
                                class="rounded-xl"
                                :disabled="isClosed(data)"
                                @click="toggleQuickActions($event, data)"
                            />
                            <Menu v-if="canUseQuickActions" :ref="(el) => setQuickMenuRef(data.id, el)" :model="quickActionItems(data)" popup>
                                <template #start>
                                    <div class="px-3 pt-3 pb-2 text-xs font-semibold uppercase tracking-wide text-surface-500">
                                        Actions rapides
                                    </div>
                                </template>
                            </Menu>
                        </div>
                    </template>
                </Column>

                <!-- Empty State -->
                <template #empty>
                    <div class="text-center py-16">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                            <i class="fas fa-clipboard-list text-4xl text-surface-400"></i>
                        </div>
                        <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">
                            Aucune consultation trouvée
                        </h4>
                        <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">
                            {{ filterGlobalValue ? 'Aucun résultat ne correspond à votre recherche.' : 'Aucune consultation n\'a été enregistrée pour cette période.' }}
                        </p>
                        <div class="flex gap-3 justify-center">
                            <Button 
                                v-if="!isMedecin"
                                icon="pi pi-plus" 
                                label="Créer une consultation" 
                                class="bg-gradient-to-r from-primary-500 to-primary-600 border-0"
                                @click="showCreateDialog = true"
                            />
                            <Button 
                                v-if="filterGlobalValue"
                                icon="pi pi-filter-slash" 
                                label="Réinitialiser les filtres" 
                                severity="secondary" 
                                outlined
                                @click="resetFilters" 
                            />
                        </div>
                    </div>
                </template>

                <!-- Loading State -->
                <template #loading>
                    <div class="flex items-center justify-center py-16">
                        <div class="text-center">
                            <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
                            <p class="text-surface-600 dark:text-surface-400">Chargement des consultations...</p>
                        </div>
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog v-model:visible="showCreateDialog" modal :style="{ width: '50rem' }" :pt="{
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
                        <p class="text-sm text-surface-500 dark:text-surface-400 mt-1">Créer une consultation</p>
                    </div>
                </div>
            </template>
            <div data-tour="consultations-table.dialog.create">
                <FormCreateConsultation @saved="handleCreateSaved" @cancel="showCreateDialog = false" />
            </div>
        </Dialog>

        <!-- Stats Overview -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6 md:mb-8" data-tour="consultations-table.stats">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100/50 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl p-5 border border-blue-200/50 dark:border-blue-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-700 dark:text-blue-300 font-medium">Total consultations</p>
                        <p class="text-2xl font-bold text-blue-900 dark:text-blue-100 mt-2">{{ consultations.length }}</p>
                    </div>
                    <i class="fas fa-clipboard-list text-2xl text-blue-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-green-50 to-green-100/50 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl p-5 border border-green-200/50 dark:border-green-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-700 dark:text-green-300 font-medium">Consultations terminées</p>
                        <p class="text-2xl font-bold text-green-900 dark:text-green-100 mt-2">
                            {{ consultations.filter(c => c.state === 1).length }}
                        </p>
                    </div>
                    <i class="fas fa-check-circle text-2xl text-green-500"></i>
                </div>
            </div>
            
            <div class="bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 rounded-2xl p-5 border border-amber-200/50 dark:border-amber-800/50">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-amber-700 dark:text-amber-300 font-medium">En cours</p>
                        <p class="text-2xl font-bold text-amber-900 dark:text-amber-100 mt-2">
                            {{ consultations.filter(c => c.state === 'EN_COURS' || c.state === 'EN_ATTENTE' || c.state == 0 ).length }}
                        </p>
                    </div>
                    <i class="fas fa-clock text-2xl text-amber-500"></i>
                </div>
            </div> 
        </div>

        <!-- Dialogs -->
        <ConsultationDetailsDialog 
            :visible="detailsDialogVisible" 
            :details="detailData" 
            :loading="detailsLoading"
            tourTarget="consultations-table.dialog.details"
            @update:visible="(val) => (detailsDialogVisible = val)" 
        />

        <FactureModal 
            :visible="factureDialogVisible" 
            :lines="factureLines" 
            :loading="currentFactureLoading"
            :saving="factureSaving" 
            tourTarget="consultations-table.dialog.facture"
            @update:visible="closeFactureModal" 
            @save="handleSaveFacture" 
        />

        <QuickClotureConsultationDialog
            v-if="canUseQuickActions"
            v-model:visible="quickDialogVisible"
            :consultation="quickDialogConsultation"
            :action-mode="quickDialogActionMode"
            tourTarget="consultations-table.dialog.quick"
            @saved="handleQuickDialogDone"
            @closed="handleQuickDialogDone"
        />
    </section>
</template>

 

<style scoped>
.row-success :deep(td) {
    background-color: #d4edda !important;
}
</style>
