<script setup>
import { logAppError } from '@/utils/appLogger';

import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import QuickClotureConsultationDialog from '@/components/consultations/QuickClotureConsultationDialog.vue';
import { activateConsultationsTourMock, deactivateConsultationsTourMock, resetConsultationsTourMockData, resolveConsultationsTourMockScenario } from '@/services/consultationsTourMock';
import { useGuidedTour } from '@/composables/useGuidedTour';
import { openConsultationFiche } from '@/composables/useFicheMedicaleAccess';
import CreateConsultationDialog from '@/components/patients/CreateConsultationDialog.vue';
import CabinetServiceDialog from '@/components/patients/CabinetServiceDialog.vue';
import { cancelConsultation, fetchPendingConsultations, defaultSoinList, normalizeSoinList } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { activatePatientsTourMock, deactivatePatientsTourMock, resetPatientsTourMockData } from '@/services/patientsTourMock';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import ConfirmPopup from 'primevue/confirmpopup';
import Menu from 'primevue/menu';
import { useConfirm } from 'primevue/useconfirm';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const toast = useToast();
const confirmPopup = useConfirm();
const token = localStorage.getItem('token');
const auth = useAuthStore();

const consultations = ref([]);
const loading = ref(false);
const canceling = ref({});
const openCreateConsultationDialog = ref(false);
const consultationPatient = ref(null);
const quickMenus = {};
const quickDialogVisible = ref(false);
const quickDialogConsultation = ref(null);
const allowReceptionQuickClose = ref(true);
const hidePatientPhoneForMedecins = ref(false);
const soinsList = ref(normalizeSoinList(defaultSoinList));
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

const loadPending = async () => {
    loading.value = true;
    try {
        consultations.value = await fetchPendingConsultations(token);
    } catch (error) {
        logAppError('Erreur lors du chargement des consultations en cours', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les consultations en cours.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const loadQuickClosePolicy = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        allowReceptionQuickClose.value = settings?.allowReceptionConsultationQuickActions !== false && settings?.allowReceptionQuickCloseConsultation !== false;
        hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
        soinsList.value = normalizeSoinList(settings?.soinsList);
    } catch (error) {
        logAppError('Erreur chargement politique de clôture rapide', error);
        allowReceptionQuickClose.value = true;
        hidePatientPhoneForMedecins.value = false;
        soinsList.value = normalizeSoinList(defaultSoinList);
    }
};

onMounted(() => {
    loadQuickClosePolicy();
    loadPending();
});

onBeforeUnmount(() => {
    deactivateConsultationsTourMock();
    deactivatePatientsTourMock();
    guidedTourDemoActive = false;
    resetTourDialogs();
});

const sortedConsultations = computed(() => {
    return [...consultations.value].sort((a, b) => {
        const aTime = a.createdAt ? new Date(a.createdAt).getTime() : 0;
        const bTime = b.createdAt ? new Date(b.createdAt).getTime() : 0;
        return aTime - bTime;
    });
});

const formatDateTime = (value) => {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
};

const formatSince = (value) => {
    if (!value) return '';
    const now = Date.now();
    const t = new Date(value).getTime();
    if (Number.isNaN(t)) return '';
    const diffMinutes = Math.max(0, Math.round((now - t) / 60000));
    if (diffMinutes < 60) return `${diffMinutes} min d'attente`;
    const hours = Math.floor(diffMinutes / 60);
    const mins = diffMinutes % 60;
    return `${hours} H ${mins.toString().padStart(2, '0')} d'attente`;
};

const cardTone = (index) => {
    if (index === 0) return 'accent-oldest';
    if (index === 1) return 'accent-old';
    return 'accent-default';
};

const goToConsultation = (consultation) => {
    openConsultationFiche(consultation, router);
};

const isLinked = (consultation) => Boolean(consultation.ficheId);
const patientHasFiche = (consultation) => Boolean(consultation.hasFiche || consultation.lastFicheId);
const isClosed = (consultation) => Number(consultation?.state) === 1;
const isAdmin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_ADMIN')));
const isMedecin = computed(() => Boolean(auth.user?.roles?.includes('ROLE_MEDECIN')));
const isReception = computed(() => Boolean(auth.user?.roles?.includes('ROLE_RECEPTION') || auth.user?.roles?.includes('ROLE_RECEPTIONNISTE')));
const isRestrictedMedecin = computed(() => isMedecin.value && !isAdmin.value);
const shouldHidePatientPhoneForMedecin = computed(() => isRestrictedMedecin.value && hidePatientPhoneForMedecins.value);
const canUseQuickActions = computed(() => !isReception.value || allowReceptionQuickClose.value);

const medecinLabel = (consultation) => {
    const value = consultation?.medecin;
    if (!value) return '—';
    if (typeof value === 'string') return value;
    const fullName = `${value.prenom ?? ''} ${value.nom ?? ''}`.trim();
    return value.label || value.fullName || value.name || fullName || '—';
};

const showActions = {
    openFiche: (c) => !isClosed(c),
    cancel: (c) => (isLinked(c) ? isAdmin.value : true)
};

const setCanceling = (id, value) => {
    canceling.value = { ...canceling.value, [id]: value };
};

const handleCancel = async (consultation) => {
    if (!consultation?.id) return;
    setCanceling(consultation.id, true);
    try {
        await cancelConsultation(consultation.id, token);
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'Consultation supprimée.', life: 2500 });
        await loadPending();
    } catch (error) {
        logAppError('Annulation impossible', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'annuler la consultation.", life: 3000 });
    } finally {
        setCanceling(consultation.id, false);
    }
};

const handleOpenFiche = (consultation) => {
    if (!consultation || isClosed(consultation)) return;
    goToConsultation(consultation);
};

const cabinetServiceVisible = ref(false);
const cabinetServicePatient = ref(null);
const openCabinetService = (consultation) => {
    const patientId = consultation?.patientId || consultation?.patient?.id;
    if (!patientId) {
        return;
    }
    cabinetServicePatient.value = {
        id: patientId,
        name: consultation?.patientName || consultation?.patient || ''
    };
    cabinetServiceVisible.value = true;
};

const handleCancelWithConfirm = (event, consultation) => {
    confirmPopup.require({
        group: 'cards-cancel-consultation',
        target: event.currentTarget,
        message: 'Annuler cette consultation en cours ?',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Confirmer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: () => handleCancel(consultation)
    });
};

const setQuickMenuRef = (id, el) => {
    if (!id) return;
    if (!el) {
        delete quickMenus[id];
        return;
    }
    quickMenus[id] = el;
};

const openQuickDialog = (consultation) => {
    if (!consultation?.id || isClosed(consultation)) return;
    quickDialogConsultation.value = consultation;
    quickDialogVisible.value = true;
};

const quickActionItems = (consultation) => {
    const closed = isClosed(consultation);

    return [
        {
            label: 'Clôture rapide',
            icon: 'pi pi-bolt',
            disabled: closed,
            command: () => openQuickDialog(consultation)
        }
    ];
};

const toggleQuickActions = (event, consultation) => {
    if (!consultation?.id) return;
    const menu = quickMenus[consultation.id];
    if (!menu) return;
    menu.toggle(event);
};

const handleQuickDialogDone = async () => {
    quickDialogVisible.value = false;
    quickDialogConsultation.value = null;
    await loadPending();
};

const firstConsultation = computed(() => sortedConsultations.value[0] || null);
const linkedConsultation = computed(() => sortedConsultations.value.find((consultation) => isLinked(consultation)) || null);
const freshConsultation = computed(() => sortedConsultations.value.find((consultation) => !isLinked(consultation) && !patientHasFiche(consultation)) || null);

const cloneValue = (value) => {
    if (value === undefined) return undefined;
    if (value === null) return null;
    return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) =>
    new Promise((resolve) => {
        window.setTimeout(resolve, ms);
    });

const resetTourDialogs = () => {
    openCreateConsultationDialog.value = false;
    consultationPatient.value = null;
    quickDialogVisible.value = false;
    quickDialogConsultation.value = null;
};

const capturePageState = () => ({
    consultations: cloneValue(consultations.value),
    canceling: cloneValue(canceling.value)
});

const restorePageState = async (state) => {
    if (!state) return;
    consultations.value = cloneValue(state.consultations) || [];
    canceling.value = cloneValue(state.canceling) || {};
    await nextTick();
};

const prepareGuidedTourDemo = async ({ taskId = 'overview', variantId = null } = {}) => {
    guidedTourPageState = capturePageState();
    const scenario = resolveConsultationsTourMockScenario(taskId, variantId);
    activatePatientsTourMock('static');
    resetPatientsTourMockData('static');
    activateConsultationsTourMock(scenario);
    resetConsultationsTourMockData(scenario);
    guidedTourDemoActive = true;

    await loadPending();
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
    consultationPatient.value = null;
    openCreateConsultationDialog.value = true;
    await nextTick();
};

const openTourQuickDialog = async () => {
    const consultation = firstConsultation.value;
    if (!consultation) return;
    resetTourDialogs();
    await nextTick();
    await waitForTourUi(220);
    quickDialogConsultation.value = consultation;
    quickDialogVisible.value = true;
    await nextTick();
    await waitForTourUi(120);

    if (!quickDialogVisible.value) {
        quickDialogVisible.value = true;
        await nextTick();
    }
};

const { isGuidedTourStarting } = useGuidedTour({
    routeName: 'consultations-cards',
    isLoading: () => loading.value,
    hasOpenDialogs: () => openCreateConsultationDialog.value || quickDialogVisible.value,
    prepareDemo: prepareGuidedTourDemo,
    cleanupDemo: cleanupGuidedTourDemo,
    getStepContext: () => {
        const consultation = firstConsultation.value;
        return {
            hasConsultations: sortedConsultations.value.length > 0,
            isMedecin: isMedecin.value,
            openCreateConsultationDialog: openTourCreateConsultationDialog,
            openQuickDialog: openTourQuickDialog,
            closeAllDialogs: resetTourDialogs,
            firstConsultationHasOpenFicheAction: consultation ? showActions.openFiche(consultation) : false,
            firstConsultationCanCancel: consultation ? showActions.cancel(consultation) : false,
            hasLinkedCase: Boolean(linkedConsultation.value),
            hasFreshCase: Boolean(freshConsultation.value),
            canOpenCreateDialog: !isMedecin.value
        };
    },
    loadingMessage: 'Attendez la fin du chargement de la file d attente avant de lancer le tour.',
    dialogsMessage: 'Fermez les fenetres ouvertes avant de lancer le tour.',
    errorMessage: 'Impossible de lancer le tour de la file d attente.'
});

function getBorderColor(index) {
    if (index === 0) return 'emerald'; // Plus ancien
    if (index < 3) return 'amber'; // Ancien
    return 'surface'; // Récent
}

function getPriorityColor(index) {
    if (index === 0) return 'red'; // Plus ancien - urgent
    if (index < 3) return 'amber'; // Ancien - attention
    return 'green'; // Récent - normal
}

function getProgressBarClass(index) {
    if (index === 0) return 'bg-gradient-to-r from-red-500 to-red-600';
    if (index < 3) return 'bg-gradient-to-r from-amber-500 to-amber-600';
    return 'bg-gradient-to-r from-green-500 to-green-600';
}

function getWaitTimePercentage(createdAt) {
    const created = new Date(createdAt);
    const now = new Date();
    const diffHours = (now - created) / (1000 * 60 * 60);

    // Plus de 1 heure = 100%
    if (diffHours >= 1) return 100;
    // Entre 0 et 1 heure = proportionnel
    return Math.min(100, Math.round((diffHours / 1) * 100));
}

const viewMode = ref('queue');

const viewOptions = [
    { label: 'Cartes', value: 'cards', icon: 'pi pi-th-large' },
    { label: 'File d’attente', value: 'queue', icon: 'pi pi-bars' }
];

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Consultations' }, { label: "File d'attente" }];
</script>

<template>
    <PageShell>
        <template #header>
            <PageHeader
                title="Consultations en cours"
                subtitle="Gestion des consultations ouvertes et en attente"
                icon="fas fa-stethoscope"
                tour-id="consultations-cards.header"
                :breadcrumb-items="breadcrumbItems"
                :breadcrumb-home="breadcrumbHome"
            >
                <template #actions>
                    <div class="flex flex-wrap items-center justify-end gap-1.5">
                        <SelectButton v-model="viewMode" :options="viewOptions" optionLabel="label" optionValue="value" class="rounded-xl">
                            <template #option="slotProps">
                                <div class="flex items-center gap-1.5 px-1 sm:px-2">
                                    <i :class="slotProps.option.icon"></i>
                                    <span class="hidden sm:inline">{{ slotProps.option.label }}</span>
                                </div>
                            </template>
                        </SelectButton>
                        <Button
                            data-tour="consultations-cards.refresh"
                            icon="pi pi-refresh"
                            aria-label="Rafraîchir"
                            v-tooltip.top="'Rafraîchir'"
                            :loading="loading"
                            outlined
                            class="rounded-xl !px-2.5"
                            @click="loadPending"
                        />
                    </div>
                </template>
            </PageHeader>
        </template>

        <template #toolbar>
            <div data-tour="consultations-cards.stats" class="page-kpi-card bg-gradient-to-br from-amber-50 to-amber-100/50 dark:from-amber-900/20 dark:to-amber-800/20 border-amber-200/50 dark:border-amber-800/50">
                <div>
                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">File D'attente</p>
                    <p class="page-kpi-value text-amber-900 dark:text-amber-100">
                        {{ sortedConsultations.length }}
                        <span class="text-sm font-normal text-amber-600 dark:text-amber-400 ml-1">en attente</span>
                    </p>
                </div>
                <i class="fas fa-clock page-kpi-icon text-amber-500 animate-pulse"></i>
            </div>
        </template>

        <PageSection plain>
            <!-- Empty State -->
            <div
                v-if="!loading && !sortedConsultations.length"
                data-tour="consultations-cards.empty-state"
                class="text-center py-16 rounded-xl border-2 border-dashed border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-br from-surface-50/50 to-surface-0/30 dark:from-surface-800/30 dark:to-surface-900/20"
            >
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-surface-100 dark:bg-surface-800 mb-6">
                    <i class="fas fa-check-circle text-4xl text-surface-400"></i>
                </div>
                <h4 class="text-xl font-semibold text-surface-700 dark:text-surface-300 mb-3">Aucune consultation en cours</h4>
                <p class="text-surface-600 dark:text-surface-400 mb-8 max-w-md mx-auto">Toutes les consultations ont été traitées ou clôturées.</p>
                <Button v-if="!isMedecin" data-tour="consultations-cards.empty-create-button" icon="fas fa-plus" label="Créer une consultation" severity="secondary" @click="openCreateConsultationDialog = true" outlined class="rounded-xl" />
            </div>

            <!-- Consultations Grid -->
            <div v-else>
                <div v-if="viewMode === 'cards'">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 w-full" v-if="sortedConsultations.length > 0">
                        <div
                            v-for="(consultation, idx) in sortedConsultations"
                            :key="consultation.id"
                            :data-tour="idx === 0 ? 'consultations-cards.case-last-fiche' : idx === 1 ? 'consultations-cards.case-linked' : idx === 2 ? 'consultations-cards.case-new' : null"
                            class="queue-card relative overflow-hidden rounded-2xl transition-all duration-300 hover:shadow-2xl hover:-translate-y-1 flex flex-col h-full group cursor-pointer"
                            :class="'queue-card--' + getBorderColor(idx)"
                            @dblclick="handleOpenFiche(consultation)"
                        >
                            <!-- Priority Indicator -->
                            <div class="absolute top-0 left-0 w-2 h-full" :class="'bg-gradient-to-b to-' + getPriorityColor(idx) + '-500 from-' + getPriorityColor(idx) + '-600'"></div>

                            <!-- Card Header -->
                            <div class="p-5 pt-6">
                                <div class="flex items-start justify-between gap-3 mb-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3 mb-3" :data-tour="idx === 0 ? 'consultations-cards.patient-block' : null">
                                            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-primary-100 to-primary-200 dark:from-primary-900/30 dark:to-primary-800/30 flex items-center justify-center">
                                                <i class="fas fa-user-md text-primary-600 dark:text-primary-400"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h3 class="text-lg font-bold text-surface-900 dark:text-surface-100 truncate">
                                                        {{ consultation.patientName || consultation.patient || 'Patientinconnu' }}
                                                    </h3>
                                                    <Tag v-if="consultation.statut" :value="consultation.statut" severity="info" class="px-3 py-1 rounded-full font-medium" />
                                                    <Tag v-if="consultation.hasInsurance" value="Assuré" severity="success" icon="pi pi-shield" class="px-3 py-1 rounded-full font-medium" />
                                                </div>
                                                <div class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400">
                                                    <i class="pi pi-phone"></i>
                                                    <span>{{ shouldHidePatientPhoneForMedecin ? "Masqué par l'administrateur" : consultation.patientPhone || 'Téléphone non renseigné' }}</span>
                                                </div>
                                                <div class="flex items-center gap-2 text-sm text-surface-500 dark:text-surface-400 mt-1">
                                                    <i class="fas fa-user-md"></i>
                                                    <span>{{ medecinLabel(consultation) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Timeline Info -->
                                        <div class="space-y-3 mt-4" :data-tour="idx === 0 ? 'consultations-cards.timeline' : null">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                                        <i class="pi pi-calendar text-blue-600 dark:text-blue-400 text-sm"></i>
                                                    </div>
                                                    <span class="text-sm font-medium text-surface-700 dark:text-surface-300"> Ouverte le </span>
                                                </div>
                                                <span class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                                    {{ formatDateTime(consultation.createdAt) }}
                                                </span>
                                            </div>

                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-2">
                                                    <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                                        <i class="pi pi-clock text-amber-600 dark:text-amber-400 text-sm"></i>
                                                    </div>
                                                    <span class="text-sm font-medium text-surface-700 dark:text-surface-300"> Ancienneté </span>
                                                </div>
                                                <span class="text-sm font-bold text-amber-600 dark:text-amber-400">
                                                    {{ formatSince(consultation.createdAt) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Indicator -->
                                <div class="mt-4" :data-tour="idx === 0 ? 'consultations-cards.progress' : null">
                                    <div class="flex justify-between text-xs text-surface-500 dark:text-surface-400 mb-1">
                                        <span>Temps d'attente</span>
                                        <span>{{ getWaitTimePercentage(consultation.createdAt) }}%</span>
                                    </div>
                                    <div class="h-2 bg-surface-200 dark:bg-surface-700 rounded-full overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-1000" :class="getProgressBarClass(idx)" :style="{ width: getWaitTimePercentage(consultation.createdAt) + '%' }"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Card Footer - Actions -->
                            <div class="mt-auto p-4 border-t border-surface-100 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-800/30">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="canUseQuickActions"
                                        :data-tour="idx === 0 ? 'consultations-cards.quick-actions' : null"
                                        icon="pi pi-bolt"
                                        label="Actions rapides"
                                        severity="contrast"
                                        size="small"
                                        outlined
                                        class="rounded-xl px-4 py-2 text-sm font-medium"
                                        :disabled="isClosed(consultation)"
                                        @click="toggleQuickActions($event, consultation)"
                                    />
                                    <Menu v-if="canUseQuickActions" :ref="(el) => setQuickMenuRef(consultation.id, el)" :model="quickActionItems(consultation)" popup>
                                        <template #start>
                                            <div class="px-3 pt-3 pb-2 text-xs font-semibold uppercase tracking-wide text-surface-500">Actions rapides</div>
                                        </template>
                                    </Menu>

                                    <Button
                                        v-if="showActions.openFiche(consultation)"
                                        :data-tour="idx === 0 ? 'consultations-cards.continue-action' : null"
                                        label="Ouvrir fiche médicale du patient"
                                        icon="pi pi-folder-open"
                                        severity="secondary"
                                        size="small"
                                        class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                        @click.stop="handleOpenFiche(consultation)"
                                    />
                                    <Button
                                        icon="pi pi-building"
                                        severity="warn"
                                        size="small"
                                        outlined
                                        label="Service cabinet"
                                        @click.stop="openCabinetService(consultation)"
                                    />
                                    <Button
                                        v-if="showActions.cancel(consultation)"
                                        :data-tour="idx === 0 ? 'consultations-cards.cancel-action' : null"
                                        label="Annuler"
                                        icon="pi pi-times"
                                        severity="danger"
                                        size="small"
                                        outlined
                                        :loading="canceling[consultation.id] === true"
                                        class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                        @click="handleCancelWithConfirm($event, consultation)"
                                    />
                                </div>
                            </div>

                            <!-- Hover Overlay -->
                            <div class="absolute inset-0 bg-gradient-to-t from-primary-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none"></div>
                        </div>
                    </div>
                </div>

                <!-- VIEW: QUEUE -->
                <div v-else class="space-y-3">
                    <div
                        v-for="(consultation, idx) in sortedConsultations"
                        :key="consultation.id"
                        class="queue-card queue-card--row flex items-center justify-between p-4 rounded-xl shadow-sm hover:shadow-md transition cursor-pointer"
                        @dblclick="handleOpenFiche(consultation)"
                    >
                        <!-- Gauche -->
                        <div class="flex items-center gap-4">
                            <!-- Position -->
                            <div
                                class="w-10 h-10 flex items-center justify-center rounded-full font-bold text-white"
                                :class="{
                                    'bg-red-500': idx === 0,
                                    'bg-amber-500': idx < 3,
                                    'bg-green-500': idx >= 3
                                }"
                            >
                                {{ idx + 1 }}
                            </div>

                            <!-- Infos -->
                            <div>
                                <p class="font-semibold text-surface-900 dark:text-surface-100">
                                    {{ consultation.patientName || consultation.patient }}
                                </p>

                                <div class="text-sm text-surface-500 flex gap-3">
                                    <span>{{ formatSince(consultation.createdAt) }}</span>
                                    <span>•</span>
                                    <span>{{ medecinLabel(consultation) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Droite -->
                        <div class="flex items-center gap-2">
                            <Button
                                v-if="canUseQuickActions"
                                :data-tour="idx === 0 ? 'consultations-cards.quick-actions' : null"
                                icon="pi pi-bolt"
                                severity="contrast"
                                size="small"
                                outlined
                                class="rounded-xl px-4 py-2 text-sm font-medium"
                                :disabled="isClosed(consultation)"
                                @click="toggleQuickActions($event, consultation)"
                            />
                            <Menu v-if="canUseQuickActions" :ref="(el) => setQuickMenuRef(consultation.id, el)" :model="quickActionItems(consultation)" popup>
                                <template #start>
                                    <div class="px-3 pt-3 pb-2 text-xs font-semibold uppercase tracking-wide text-surface-500">Actions rapides</div>
                                </template>
                            </Menu>

                            <Button
                                v-if="showActions.openFiche(consultation)"
                                :data-tour="idx === 0 ? 'consultations-cards.continue-action' : null"
                                icon="pi pi-folder-open"
                                severity="secondary"
                                size="small"
                                v-tooltip.top="'Ouvrir fiche médicale du patient'"
                                class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                @click.stop="handleOpenFiche(consultation)"
                            />
                            <Button
                                icon="pi pi-building"
                                severity="warn"
                                size="small"
                                outlined
                                v-tooltip.top="'Enregistrer un service cabinet'"
                                @click.stop="openCabinetService(consultation)"
                            />
                            <Button
                                v-if="showActions.cancel(consultation)"
                                :data-tour="idx === 0 ? 'consultations-cards.cancel-action' : null"
                                icon="pi pi-times"
                                severity="danger"
                                size="small"
                                outlined
                                :loading="canceling[consultation.id] === true"
                                class="rounded-xl px-4 py-2 text-sm font-medium transition-all hover:scale-[1.02]"
                                @click="handleCancelWithConfirm($event, consultation)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-16">
                <div class="text-center">
                    <i class="pi pi-spin pi-spinner text-4xl text-primary-500 mb-4"></i>
                    <p class="text-surface-600 dark:text-surface-400">Chargement des consultations...</p>
                </div>
            </div>
        </PageSection>

    <CreateConsultationDialog
        v-if="!isMedecin"
        v-model:visible="openCreateConsultationDialog"
        tour-id="consultations-cards.create-dialog"
        content-tour-id="consultations-cards.dialog.create"
        :subtitle="consultationPatient?.fullname || consultationPatient?.nom || 'Nouveau patient'"
        @saved="
            () => {
                openCreateConsultationDialog = false;
                loadPending();
            }
        "
    />

    <ConfirmPopup group="cards-cancel-consultation" />

    <QuickClotureConsultationDialog
        v-if="canUseQuickActions"
        v-model:visible="quickDialogVisible"
        :consultation="quickDialogConsultation"
        :soins="soinsList"
        tourTarget="consultations-cards.dialog.quick"
        @saved="handleQuickDialogDone"
        @closed="handleQuickDialogDone"
    />
    <CabinetServiceDialog v-model:visible="cabinetServiceVisible" :patient-id="cabinetServicePatient?.id" :patient-name="cabinetServicePatient?.name || ''" />
    </PageShell>
</template>

<style scoped>
.queue-card {
    background: var(--surface-card);
    border: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    box-shadow: 0 1px 2px color-mix(in srgb, var(--text-color) 4%, transparent);
}

.queue-card--emerald {
    border-color: color-mix(in srgb, #34d399 50%, var(--surface-border));
}

.queue-card--amber {
    border-color: color-mix(in srgb, #fbbf24 45%, var(--surface-border));
}

.queue-card--surface {
    border-color: color-mix(in srgb, var(--surface-border) 80%, transparent);
}

/* Animation pour les cartes */
@keyframes pulse-glow {
    0%,
    100% {
        box-shadow: 0 0 20px -10px rgba(16, 185, 129, 0.5);
    }

    50% {
        box-shadow: 0 0 30px -5px rgba(16, 185, 129, 0.8);
    }
}

/* Priorité haute - animation de pulsation */
.grid-cols-1 > div:first-child,
.grid-cols-2 > div:nth-child(-n + 1),
.grid-cols-3 > div:nth-child(-n + 1) {
    animation: pulse-glow 2s ease-in-out infinite;
}

/* Transition pour les cartes */
.card-transition-enter-active {
    transition: all 0.3s ease-out;
}

.card-transition-leave-active {
    transition: all 0.2s ease-in;
}

.card-transition-enter-from,
.card-transition-leave-to {
    opacity: 0;
    transform: scale(0.9) translateY(10px);
}
</style>
