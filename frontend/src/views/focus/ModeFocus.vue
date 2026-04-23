<script setup>
import ConsultationDetailsDialog from '@/components/consultations/ConsultationDetailsDialog.vue';
import FactureModal from '@/components/consultations/FactureModal.vue';
import QuickClotureConsultationDialog from '@/components/consultations/QuickClotureConsultationDialog.vue';
import EmbeddedConsultationFiche from '@/components/focus/EmbeddedConsultationFiche.vue';
import DossierPatientInfoCard from '@/components/patients/DossierPatientInfoCard.vue';
import { useFocusRealtime } from '@/composables/useFocusRealtime';
import { defaultSoinList, fetchConsultationDetails, fetchConsultationInvoice, fetchConsultationsByDate, normalizeSoinList, updateConsultationInvoice, cancelConsultation } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { fetchPatientById, normalizePatient } from '@/services/patients';
import { useAuthStore } from '@/stores/auth';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import ConfirmPopup from 'primevue/confirmpopup';
import DataView from 'primevue/dataview';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted,  onBeforeUnmount, ref, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';

const auth = useAuthStore();
const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');

const loading = ref(false);
const consultations = ref([]);
const allowReceptionQuickClose = ref(true);
const soinsList = ref([...defaultSoinList]);
const selectedMode = ref('reception');
const showCompletedSecretary = ref(false);
const showCompletedMedecin = ref(false);
const selectedConsultationId = ref(null);
const selectedPatient = ref(null);
const detailsDialogVisible = ref(false);
const detailsLoading = ref(false);
const detailData = ref(null);
const factureDialogVisible = ref(false);
const factureLoading = ref(false);
const factureSaving = ref(false);
const factureLines = ref([]);
const factureConsultation = ref(null);
const quickDialogVisible = ref(false);
const quickDialogConsultation = ref(null);
const quickDialogActionMode = ref('continue');
const actionChoiceByConsultation = ref({});
const initialized = ref(false);

const roles = computed(() => auth.user?.roles || []);
const isAdmin = computed(() => roles.value.includes('ROLE_ADMIN'));
const isMedecin = computed(() => roles.value.includes('ROLE_MEDECIN'));
const isReception = computed(() => roles.value.includes('ROLE_RECEPTION') || roles.value.includes('ROLE_RECEPTIONNISTE') || roles.value.includes('ROLE_SECRETAIRE'));
const availableModes = computed(() => {
    if (isAdmin.value) {
        return [
            { label: 'Secretaire', value: 'reception' },
            { label: 'Dentiste', value: 'medecin' }
        ];
    }
    if (isMedecin.value) {
        return [{ label: 'Dentiste', value: 'medecin' }];
    }
    return [{ label: 'Secretaire', value: 'reception' }];
});

if (isMedecin.value && !isAdmin.value) {
    selectedMode.value = 'medecin';
}

const todayApiDate = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const parseDateTime = (value) => {
    if (!value) return null;
    if (value instanceof Date) return value;
    if (/^\d{2}\/\d{2}\/\d{4}\s\d{2}:\d{2}$/.test(String(value))) {
        const [datePart, timePart] = String(value).split(' ');
        const [day, month, year] = datePart.split('/').map(Number);
        const [hours, minutes] = timePart.split(':').map(Number);
        return new Date(year, month - 1, day, hours, minutes, 0, 0);
    }
    const parsed = new Date(value);
    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatTime = (value) => {
    const parsed = parseDateTime(value);
    return parsed ? parsed.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '--:--';
};

const patientLabel = (consultation) => {
    if (!consultation) return 'Patient';
    if (typeof consultation.patientName === 'string' && consultation.patientName.trim()) return consultation.patientName;
    if (typeof consultation.patient === 'string' && consultation.patient.trim()) return consultation.patient;
    const patient = consultation.patient || {};
    return `${patient.prenom ?? ''} ${patient.nom ?? ''}`.trim() || patient.nom || 'Patient';
};

const medecinLabel = (consultation) => {
    const medecin = consultation?.medecin;
    if (!medecin) return 'Non assigne';
    if (typeof medecin === 'string') return medecin;
    return medecin.label || medecin.fullName || medecin.name || `${medecin.prenom ?? ''} ${medecin.nom ?? ''}`.trim() || 'Non assigne';
};

const initialsFromName = (value) => String(value || '')
    .split(' ')
    .filter(Boolean)
    .map((item) => item.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase();

const currentConsultation = computed(() => consultations.value.find((item) => item.id === selectedConsultationId.value) || null);

const secretaryRows = computed(() => {
    const source = [...consultations.value].sort((left, right) => {
        const leftTime = parseDateTime(left.createdAt)?.getTime() || 0;
        const rightTime = parseDateTime(right.createdAt)?.getTime() || 0;
        return leftTime - rightTime;
    });

    if (showCompletedSecretary.value) {
        return source;
    }

    return source.filter((item) => Number(item.state) !== 1);
});

const medecinQueue = computed(() => {
    const source = [...consultations.value].sort((left, right) => {
        const leftTime = parseDateTime(left.createdAt)?.getTime() || 0;
        const rightTime = parseDateTime(right.createdAt)?.getTime() || 0;
        return leftTime - rightTime;
    });

    if (showCompletedMedecin.value) {
        return source;
    }

    return source.filter((item) => Number(item.state) !== 1);
});

const selectedActionChoice = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return null;
    return actionChoiceByConsultation.value[consultation.id] || null;
});

const requiresChoice = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return false;
    if (consultation.ficheId) return false;
    return Boolean(consultation.hasFiche || consultation.lastFicheId);
});

const selectedEmbeddedMode = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return 'continue';
    if (consultation.ficheId) return 'continue';
    if (!requiresChoice.value) return 'new-fiche';
    return selectedActionChoice.value === 'new-fiche' ? 'new-fiche' : 'continue';
});

const selectedEmbeddedFicheId = computed(() => {
    const consultation = currentConsultation.value;
    if (!consultation) return null;
    if (consultation.ficheId) return consultation.ficheId;
    if (selectedActionChoice.value === 'continue-last') return consultation.lastFicheId || null;
    return null;
});

const selectedChoiceLabel = computed(() => {
    if (selectedActionChoice.value === 'continue-last') return 'Reprise de la derniere fiche';
    if (selectedActionChoice.value === 'new-fiche') return 'Nouvelle fiche choisie';
    if (currentConsultation.value?.ficheId) return 'Fiche liee en cours';
    if (!requiresChoice.value) return 'Nouvelle fiche automatique';
    return '';
});

const currentConsultationClosed = computed(() => Number(currentConsultation.value?.state) === 1);

const focusStats = computed(() => {
    const total = consultations.value.length;
    const closed = consultations.value.filter((item) => Number(item.state) === 1).length;
    const pending = total - closed;
    return { total, closed, pending };
});

const queueItemClass = (consultation) => {
    if (Number(consultation.state) === 1) {
        return 'border-green-400/70 bg-green-50/70 dark:bg-green-950/20';
    }
    if (consultation.id === selectedConsultationId.value) {
        return 'border-primary-500 ring-2 ring-primary-400/60 ring-offset-2 ring-offset-surface-0 shadow-xl shadow-primary-500/20 bg-primary-50 dark:bg-primary-950/20 dark:ring-offset-surface-900';
    }
    return 'border-surface-200/70 bg-surface-0/90 dark:border-surface-700/70 dark:bg-surface-800/80';
};

const normalizePatientForCard = (payload = {}) => {
    const normalized = normalizePatient(payload || {});
    const fullName = `${normalized.nom || ''} ${normalized.prenom || ''}`.trim();
    return {
        ...payload,
        ...normalized,
        initials: initialsFromName(fullName || payload.fullname || payload.nom),
        numeroDossier: payload.numeroDossier || payload.numero_dossier || payload.code || `PAT-${normalized.id || '--'}`,
        age: normalized.age ?? (normalized.dateNaissance ? Math.max(0, Math.floor((Date.now() - new Date(normalized.dateNaissance).getTime()) / (1000 * 60 * 60 * 24 * 365.25))) : 0),
        groupeSanguin: normalized.groupeSanguin || '--',
        telephone: normalized.telephone || '--',
        email: normalized.email || '--',
        adresse: normalized.adresse || '--',
        antecedents: Array.isArray(payload.antecedents) ? payload.antecedents : [],
        allergies: Array.isArray(payload.allergies) ? payload.allergies : []
    };
};

const loadSettings = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        allowReceptionQuickClose.value = settings?.allowReceptionQuickCloseConsultation !== false;
        soinsList.value = normalizeSoinList(settings?.soinsList);
    } catch (_) {
        allowReceptionQuickClose.value = true;
        soinsList.value = [...defaultSoinList];
    }
};

const syncSelection = () => {
    if (selectedConsultationId.value && consultations.value.some((item) => item.id === selectedConsultationId.value)) {
        return;
    }

    selectedConsultationId.value = null;
    selectedPatient.value = null;
};

const clearSelection = () => {
    selectedConsultationId.value = null;
    selectedPatient.value = null;
};

const loadConsultations = async () => {
    loading.value = true;
    try {
        consultations.value = await fetchConsultationsByDate(todayApiDate(), token);
        syncSelection();
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les consultations du jour.', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const loadSelectedPatient = async () => {
    const consultation = currentConsultation.value;
    if (!consultation?.patientId) {
        selectedPatient.value = null;
        return;
    }

    try {
        const patient = await fetchPatientById(consultation.patientId, token);
        selectedPatient.value = normalizePatientForCard(patient);
    } catch (_) {
        selectedPatient.value = null;
    }
};

const openDetails = async (consultation) => {
    if (!consultation?.id) return;
    detailsDialogVisible.value = true;
    detailsLoading.value = true;
    detailData.value = null;
    try {
        detailData.value = await fetchConsultationDetails(consultation.id, token);
    } catch (_) {
        detailsDialogVisible.value = false;
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les détails.', life: 3000 });
    } finally {
        detailsLoading.value = false;
    }
};

const openFacture = async (consultation) => {
    if (!consultation?.id) return;
    factureConsultation.value = consultation;
    factureDialogVisible.value = true;
    factureLoading.value = true;
    try {
        factureLines.value = await fetchConsultationInvoice(consultation.id, token);
    } catch (_) {
        factureDialogVisible.value = false;
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la facture.', life: 3000 });
    } finally {
        factureLoading.value = false;
    }
};

const handleSaveFacture = async (lines) => {
    if (!factureConsultation.value?.id) return;
    factureSaving.value = true;
    try {
        await updateConsultationInvoice(factureConsultation.value.id, lines, token);
        toast.add({ severity: 'success', summary: 'Facture mise a jour', life: 2200 });
        factureDialogVisible.value = false;
        await loadConsultations();
    } catch (_) {
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

const resolveQuickActionMode = (consultation) => {
    if (!consultation) return 'continue';
    if (consultation.ficheId) return 'continue';
    if (consultation.hasFiche || consultation.lastFicheId) return 'continue-last';
    return 'new-fiche';
};

const openQuickDialog = (consultation) => {
    if (!consultation?.id || Number(consultation.state) === 1) return;
    quickDialogConsultation.value = consultation;
    quickDialogActionMode.value = resolveQuickActionMode(consultation);
    quickDialogVisible.value = true;
};

const handleQuickDialogDone = async () => {
    quickDialogVisible.value = false;
    quickDialogConsultation.value = null;
    await loadConsultations();
};

const openMedicalWorkspace = (consultation, choice = null) => {
    if (!consultation?.id) return;
    selectedConsultationId.value = consultation.id;
    if (choice) {
        actionChoiceByConsultation.value = {
            ...actionChoiceByConsultation.value,
            [consultation.id]: choice
        };
    }
    selectedMode.value = 'medecin';
};

const handleCancel = async (consultation) => {
    if (!consultation?.id) return;
    try {
        await cancelConsultation(consultation.id, token);
        toast.add({ severity: 'success', summary: 'Consultation annulee', life: 2200 });
        await loadConsultations();
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'annuler la consultation.", life: 3000 });
    }
};

const askCancel = (event, consultation) => {
    confirm.require({
        group: 'focus-cancel-consultation',
        target: event?.currentTarget || event?.target,
        message: 'Annuler cette consultation ? Cette action est irréversible.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, annuler',
        rejectLabel: 'Non',
        acceptClass: 'p-button-danger',
        accept: () => handleCancel(consultation)
    });
};

const onFocusRealtimeEvent = async () => {
    await loadConsultations();
    await loadSelectedPatient();
};

const { realtimeEnabled } = useFocusRealtime(onFocusRealtimeEvent);

watch(
    () => selectedConsultationId.value,
    () => {
        loadSelectedPatient();
    },
    { immediate: true }
);

watch(
    () => currentConsultation.value?.id,
    (id) => {
        if (!id) return;
        if (!actionChoiceByConsultation.value[id] && currentConsultation.value?.ficheId) {
            actionChoiceByConsultation.value = {
                ...actionChoiceByConsultation.value,
                [id]: 'continue-last'
            };
        }
    },
    { immediate: true }
);

const initializeFocusPage = async () => {
    if (initialized.value) {
        return;
    }

    initialized.value = true;
    await loadSettings();
    await loadConsultations();
};

onMounted(() => {
    initializeFocusPage();
    if (useLayout().isSidebarActive) useLayout().toggleMenu();
});

onBeforeUnmount(() => {
     if (!useLayout().isSidebarActive) useLayout().toggleMenu();
});

const formatFactureState = (consultation) => {
    if (consultation?.factState === null || typeof consultation?.factState === 'undefined') {
        return { label: 'Aucune facture', severity: 'contrast' };
    }
    if (Number(consultation.factState) === 0) {
        return { label: 'Facture ouverte', severity: 'warn' };
    }
    return { label: 'Facture reglee', severity: 'success' };
};

const patientHistoryState = (consultation) => {
    if (consultation?.hasFiche || consultation?.lastFicheId || consultation?.ficheId) {
        return { label: 'Ancien patient', severity: 'info' };
    }
    return { label: 'Nouveau patient', severity: 'contrast' };
};
</script>

<template>
    <section class="min-h-screen p-0 ml-4 transition-colors duration-300">
        <Toast />
        <ConfirmPopup group="focus-cancel-consultation" />

        <div class="mx-auto flex max-w-[1900px] flex-col gap-6">
            <div class="rounded-[28px] border border-surface-200/60 bg-surface-0/90 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/75">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                    <div class="space-y-3">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-[20px] bg-gradient-to-br from-primary-500 to-sky-500 text-white shadow-lg">
                                <i class="pi pi-bolt text-2xl"></i>
                            </div>
                            <div>
                                <h1 class="text-3xl font-bold tracking-tight text-surface-900 dark:text-surface-50">Mode Focus</h1>
                                <p class="text-sm text-surface-600 dark:text-surface-300">Vue concentree sur le flux du jour, sans sortir de l'espace de travail.</p>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <Tag :value="`${focusStats.pending} en attente`" severity="info" />
                            <Tag :value="`${focusStats.closed} terminees`" severity="success" />
                            <Tag :value="`${focusStats.total} total`" severity="contrast" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center md:justify-end">
                        <div v-if="availableModes.length > 1" class="rounded-2xl border border-surface-200/60 p-1 dark:border-surface-700/60">
                            <div class="flex gap-1">
                                <Button
                                    v-for="mode in availableModes"
                                    :key="mode.value"
                                    :label="mode.label"
                                    :severity="selectedMode === mode.value ? 'contrast' : 'secondary'"
                                    :outlined="selectedMode !== mode.value"
                                    class="rounded-xl"
                                    @click="selectedMode = mode.value"
                                />
                            </div>
                        </div>

                        <label class="flex items-center gap-2 rounded-2xl border border-surface-200/60 px-4 py-3 text-sm dark:border-surface-700/60">
                            <Checkbox v-model="realtimeEnabled" binary inputId="focus-realtime" />
                            <span class="font-medium text-surface-700 dark:text-surface-200">Temps reel</span>
                        </label>

                        <Button
                            icon="pi pi-refresh"
                            label="Rafraichir"
                            :loading="loading"
                            outlined
                            class="rounded-2xl"
                            @click="loadConsultations"
                        />
                    </div>
                </div>
            </div>

            <div v-if="selectedMode === 'reception'" class="rounded-[28px] border border-surface-200/60 bg-surface-card p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/78">
                <div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">Consultations du jour</h2>
                        <p class="text-sm text-surface-600 dark:text-surface-300">Vue secretaire avec actions intégrées, facture et paiements en lecture rapide.</p>
                    </div>

                    <label class="flex items-center gap-2 rounded-2xl border border-surface-200/60 px-4 py-3 text-sm dark:border-surface-700/60">
                        <Checkbox v-model="showCompletedSecretary" binary inputId="focus-secretary-completed" />
                        <span class="font-medium text-surface-700 dark:text-surface-200">Afficher les terminees</span>
                    </label>
                </div>

                <DataView :value="secretaryRows" dataKey="id" :paginator="secretaryRows.length > 10" :rows="10">
                    <template #empty>
                        <div class="rounded-3xl border border-dashed border-surface-300/80 bg-surface-50/80 p-12 text-center dark:border-surface-700 dark:bg-surface-800/40">
                            <i class="pi pi-inbox text-4xl text-surface-400"></i>
                            <p class="mt-4 text-base font-medium text-surface-700 dark:text-surface-200">Aucune consultation a afficher pour aujourd'hui.</p>
                        </div>
                    </template>

                    <template #list="slotProps">
                        <div class="grid gap-4">
                            <article
                                v-for="consultation in slotProps.items"
                                :key="consultation.id"
                                class="rounded-[26px] border p-5 shadow-sm transition-all duration-200"
                                :class="queueItemClass(consultation)"
                            >
                                <div class="grid gap-5 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.9fr)_auto] xl:items-start">
                                    <div class="space-y-4">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <div class="rounded-2xl bg-surface-100 px-3 py-2 text-sm font-semibold text-surface-700 dark:bg-surface-800 dark:text-surface-200">
                                                {{ formatTime(consultation.createdAt) }}
                                            </div>
                                            <Tag :value="Number(consultation.state) === 1 ? 'Terminee' : consultation.id === selectedConsultationId ? 'En cours' : 'En attente'" :severity="Number(consultation.state) === 1 ? 'success' : consultation.id === selectedConsultationId ? 'info' : 'warn'" />
                                            <Tag :value="formatFactureState(consultation).label" :severity="formatFactureState(consultation).severity" />
                                        </div>

                                        <div>
                                            <h3 class="text-xl font-semibold text-surface-900 dark:text-surface-50">{{ patientLabel(consultation) }}</h3>
                                            <p class="text-sm text-surface-600 dark:text-surface-300">{{ medecinLabel(consultation) }}</p>
                                        </div>

                                        <div class="grid gap-3 md:grid-cols-2">
                                            <div class="rounded-2xl border border-surface-200/60 bg-surface-50/70 p-4 dark:border-surface-700/60 dark:bg-surface-800/60">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Facture</span>
                                                    <Button icon="pi pi-file-edit" text size="small" @click="openFacture(consultation)" />
                                                </div>
                                                <p class="text-sm text-surface-700 dark:text-surface-200">{{ consultation.factState === null || typeof consultation.factState === 'undefined' ? 'Aucune facture creee pour cette consultation.' : formatFactureState(consultation).label }}</p>
                                            </div>

                                            <div class="rounded-2xl border border-dashed border-surface-200/80 bg-surface-50/40 p-4 dark:border-surface-700/80 dark:bg-surface-800/40">
                                                <div class="mb-2 flex items-center justify-between">
                                                    <span class="text-xs font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Paiements</span>
                                                    <i class="pi pi-wallet text-surface-400"></i>
                                                </div>
                                                <p class="text-sm text-surface-600 dark:text-surface-300">Aucun paiement rattache visible dans cette vue pour le moment.</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="space-y-3">
                                        <div class="rounded-2xl border border-surface-200/60 bg-surface-50/70 p-4 dark:border-surface-700/60 dark:bg-surface-800/60">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Contexte</p>
                                            <p class="mt-2 text-sm text-surface-700 dark:text-surface-200">Patient {{ patientHistoryState(consultation).label.toLowerCase() }}. {{ consultation.ficheId ? 'Fiche deja liee a la consultation.' : (consultation.hasFiche || consultation.lastFicheId ? 'Derniere fiche disponible pour reprise.' : 'Une nouvelle fiche sera ouverte si besoin.') }}</p>
                                        </div>

                                        <div class="rounded-2xl border border-surface-200/60 bg-surface-50/70 p-4 dark:border-surface-700/60 dark:bg-surface-800/60">
                                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-surface-500 dark:text-surface-400">Mode Focus</p>
                                            <p class="mt-2 text-sm text-surface-700 dark:text-surface-200">Toutes les actions restent embarquées sur cet écran, sans redirection vers une autre page.</p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col gap-2 xl:w-[15rem]">
                                        <Button label="Details" icon="pi pi-search" outlined class="rounded-2xl" @click="openDetails(consultation)" />
                                        <Button label="Facture" icon="pi pi-wallet" outlined class="rounded-2xl" @click="openFacture(consultation)" />
                                        <Button
                                            v-if="allowReceptionQuickClose && Number(consultation.state) !== 1"
                                            label="Cloture rapide"
                                            icon="pi pi-bolt"
                                            class="rounded-2xl"
                                            @click="openQuickDialog(consultation)"
                                        />
                                        <Button
                                            v-if="isAdmin && Number(consultation.state) !== 1"
                                            label="Ouvrir cote dentiste"
                                            icon="pi pi-arrow-right"
                                            severity="secondary"
                                            class="rounded-2xl"
                                            @click="openMedicalWorkspace(consultation, consultation.hasFiche || consultation.lastFicheId ? 'continue-last' : 'new-fiche')"
                                        />
                                        <Button
                                            v-if="Number(consultation.state) !== 1"
                                            label="Annuler"
                                            icon="pi pi-times"
                                            severity="danger"
                                            outlined
                                            class="rounded-2xl"
                                            @click="(event) => askCancel(event, consultation)"
                                        />
                                    </div>
                                </div>
                            </article>
                        </div>
                    </template>
                </DataView>
            </div>

            <div v-else class="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)_360px]">
                <div class="space-y-4">
                    <div class="rounded-[28px] border border-surface-50  p-4 shadow-xl backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/78">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-50">Patient selectionne</h2>
                            <Button v-if="currentConsultation" label="Deselectionner" icon="pi pi-times" severity="secondary" outlined size="small" class="rounded-xl" @click="clearSelection" />
                        </div>
                        <DossierPatientInfoCard v-if="selectedPatient" :patient="selectedPatient" :hide-actions="true" />
                        <div v-else class="rounded-3xl border border-dashed border-surface-300/80 bg-surface-50/80 p-6 dark:border-surface-700 dark:bg-surface-800/40">
                            <div class="rounded-2xl border border-dashed border-surface-300/80 bg-surface-0/70 px-5 py-8 text-center dark:border-surface-600/80 dark:bg-surface-900/30">
                                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-200/70 dark:bg-surface-700/70">
                                    <i class="pi pi-user text-xl text-surface-400"></i>
                                </div>
                                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Patient non selectionne</h3>
                                <p class="mt-2 text-sm text-surface-600 dark:text-surface-300">Choisissez une consultation dans la file d'attente pour afficher ici les informations du patient.</p>
                            </div>
                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div class="rounded-2xl border border-dashed border-surface-300/80 bg-surface-0/70 p-4 text-center text-sm text-surface-500 dark:border-surface-600/80 dark:bg-surface-900/30 dark:text-surface-400">
                                    Informations administratives indisponibles
                                </div>
                                <div class="rounded-2xl border border-dashed border-surface-300/80 bg-surface-0/70 p-4 text-center text-sm text-surface-500 dark:border-surface-600/80 dark:bg-surface-900/30 dark:text-surface-400">
                                    Historique medical indisponible
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-if="currentConsultation && !currentConsultationClosed && requiresChoice && !selectedActionChoice" class="rounded-[28px] border border-surface50 p-5 shadow-lg dark:border-amber-800 dark:bg-amber-950/30">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-amber-950 dark:text-amber-100">Choisir une seule fois le demarrage clinique</h2>
                                <p class="text-sm text-amber-900/80 dark:text-amber-100/80">Le patient dispose deja d'une fiche. Choisissez entre reprendre la derniere fiche ou ouvrir une nouvelle fiche, puis la décision restera affichée en haut.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button label="Reprendre la derniere fiche" icon="pi pi-history" class="rounded-2xl" @click="openMedicalWorkspace(currentConsultation, 'continue-last')" />
                                <Button label="Nouvelle fiche" icon="pi pi-plus-circle" outlined class="rounded-2xl" @click="openMedicalWorkspace(currentConsultation, 'new-fiche')" />
                            </div>
                        </div>
                    </div>

                    <div v-if="currentConsultation && selectedPatient" class="rounded-[28px] border border-surface-200/60 bg-surface-50 p-4 shadow-xl backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/78">
                        <EmbeddedConsultationFiche
                            :consultation-id="currentConsultation.id"
                            :fiche-id="selectedEmbeddedFicheId"
                            :mode="selectedEmbeddedMode"
                            :readonly="currentConsultationClosed"
                            :choice-label="selectedChoiceLabel"
                            @patient-loaded="(payload) => { selectedPatient = normalizePatientForCard(payload); }"
                            @closed="async () => { await loadConsultations(); }"
                        />
                    </div>

                    <div v-else class="rounded-[28px] border border-dashed border-surface-300/80 bg-surface-50/80 p-8 shadow-sm dark:border-surface-700 dark:bg-surface-800/40">
                        <div class="rounded-2xl border border-dashed border-surface-300/80 bg-surface-0/70 px-5 py-10 text-center dark:border-surface-600/80 dark:bg-surface-900/30">
                            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-200/70 dark:bg-surface-700/70">
                                <i class="pi pi-file-edit text-xl text-surface-400"></i>
                            </div>
                            <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Patient non selectionne</h3>
                            <p class="mt-2 text-sm text-surface-600 dark:text-surface-300">Le formulaire focus apparaitra ici une fois une consultation choisie dans la file d'attente.</p>
                        </div>
                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                            <div class="rounded-2xl border border-dashed border-surface-300/80 bg-surface-0/70 p-5 text-center text-sm text-surface-500 dark:border-surface-600/80 dark:bg-surface-900/30 dark:text-surface-400">
                                Les onglets du dossier s'afficheront ici
                            </div>
                            <div class="rounded-2xl border border-dashed border-surface-300/80 bg-surface-0/70 p-5 text-center text-sm text-surface-500 dark:border-surface-600/80 dark:bg-surface-900/30 dark:text-surface-400">
                                Les actions de consultation resteront intégrées ici
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[28px] border border-surface-200/60 bg-surface-50 p-5 shadow-xl backdrop-blur-sm dark:border-surface-700/60 dark:bg-surface-900/78">
                    <div class="mb-5 flex flex-col gap-3">
                        <div>
                            <h2 class="text-xl font-semibold text-surface-900 dark:text-surface-50">File d'attente du jour</h2>
                            <p class="text-sm text-surface-600 dark:text-surface-300">Consultations assignees ou non assignees pour la journee courante.</p>
                        </div>
                        <label class="flex items-center gap-2 rounded-2xl border border-surface-200/60 px-4 py-3 text-sm dark:border-surface-700/60">
                            <Checkbox v-model="showCompletedMedecin" binary inputId="focus-medecin-completed" />
                            <span class="font-medium text-surface-700 dark:text-surface-200">Afficher les terminees</span>
                        </label>
                    </div>

                    <div class="space-y-3">
                        <button
                            v-for="consultation in medecinQueue"
                            :key="consultation.id"
                            type="button"
                            class="group w-full rounded-[24px] border p-4 text-left shadow-sm transition-all duration-200"
                            :class="queueItemClass(consultation)"
                            @click="selectedConsultationId = consultation.id"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm font-semibold text-surface-700 dark:text-surface-200">{{ formatTime(consultation.createdAt) }}</span>
                                        <Tag :value="patientHistoryState(consultation).label" :severity="patientHistoryState(consultation).severity" />
                                    </div>
                                    <h3 class="mt-2 text-base font-semibold text-surface-900 dark:text-surface-50">{{ patientLabel(consultation) }}</h3>
                                    <p class="text-sm text-surface-600 dark:text-surface-300">{{ medecinLabel(consultation) }}</p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <Tag :value="Number(consultation.state) === 1 ? 'Terminee' : consultation.id === selectedConsultationId ? 'Selectionnee' : 'En attente'" :severity="Number(consultation.state) === 1 ? 'success' : consultation.id === selectedConsultationId ? 'info' : 'warn'" />
                                    <span v-if="consultation.id === selectedConsultationId" class="rounded-full bg-primary-500/10 px-2.5 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-primary-700 dark:bg-primary-400/15 dark:text-primary-300">Active</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <ConsultationDetailsDialog v-model:visible="detailsDialogVisible" :details="detailData" :loading="detailsLoading" />
            <FactureModal
                :visible="factureDialogVisible"
                :lines="factureLines"
                :loading="factureLoading"
                :saving="factureSaving"
                :soins="soinsList"
                @update:visible="closeFactureModal"
                @save="handleSaveFacture"
            />
            <QuickClotureConsultationDialog
                v-model:visible="quickDialogVisible"
                :consultation="quickDialogConsultation"
                :action-mode="quickDialogActionMode"
                @saved="handleQuickDialogDone"
                @closed="handleQuickDialogDone"
            />
        </div>
    </section>
</template>