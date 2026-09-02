<script setup>
import CaisseInvoiceDialogs from '@/components/caisse/CaisseInvoiceDialogs.vue';
import ConsultationDetailsDialog from '@/components/consultations/ConsultationDetailsDialog.vue';
import FactureModal from '@/components/consultations/FactureModal.vue';
import { useInvoiceBillingActions } from '@/composables/useInvoiceBillingActions';
import { cancelConsultation, fetchConsultationDetails, fetchConsultationInvoice, updateConsultationInvoice } from '@/services/consultations';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { useAuthStore } from '@/stores/auth';
import { logAppError } from '@/utils/appLogger';
import { buildConsultationContextMenuItems } from '@/utils/consultationRow';
import { buildFactureContextMenuItems, computeFactureStatus, formatFactureFcfa, isUnpaidFacture } from '@/utils/factureRow';
import { canUserModifyInvoice } from '@/utils/invoiceModificationAccess';
import Column from 'primevue/column';
import ConfirmPopup from 'primevue/confirmpopup';
import ContextMenu from 'primevue/contextmenu';
import DataTable from 'primevue/datatable';
import Tab from 'primevue/tab';
import TabList from 'primevue/tablist';
import TabPanel from 'primevue/tabpanel';
import TabPanels from 'primevue/tabpanels';
import Tag from 'primevue/tag';
import Tabs from 'primevue/tabs';
import ToggleButton from 'primevue/togglebutton';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    rdvs: {
        type: Array,
        default: () => []
    },
    paiements: {
        type: Array,
        default: () => []
    },
    factures: {
        type: Array,
        default: () => []
    },
    consultations: {
        type: Array,
        default: () => []
    },
    showConsultations: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['refresh']);

const auth = useAuthStore();
const confirm = useConfirm();
const toast = useToast();
const token = localStorage.getItem('token');

const allowReceptionInvoiceModification = ref(false);

const tabs = computed(() => {
    const base = [
        { id: 'rdv', label: 'Rendez-vous', icon: 'pi pi-calendar' },
        { id: 'paiements', label: 'Paiements', icon: 'pi pi-credit-card' },
        { id: 'factures', label: 'Factures', icon: 'pi pi-file' },
        { id: 'actes', label: 'Actes médicaux', icon: 'pi pi-list-check' }
    ];

    if (props.showConsultations) {
        base.push({ id: 'consultations', label: 'Consultations', icon: 'pi pi-folder-open' });
    }

    return base;
});

const activeTab = ref('rdv');
const showUnpaidOnly = ref(false);

const canModifyInvoiceByRole = computed(() => canUserModifyInvoice(auth.user, { allowReceptionInvoiceModification: allowReceptionInvoiceModification.value }));

const {
    payDialogVisible,
    selectedFacture,
    payForm,
    classicPaymentOptions,
    insuranceCoveredAmount,
    invoiceInsuranceRate,
    patientAlreadyPaidAmount,
    patientOutstandingAmount,
    invoiceHasInsurance,
    insuranceStatusLabel,
    maxClientPaymentAmount,
    remainingAfterPay,
    canResetInvoicePayments,
    payLoading,
    payTabs,
    activePayTabId,
    priorReliquatTotal,
    activePayTabMode,
    resetPaymentDialogVisible,
    resetPaymentsLoading,
    validateDialogVisible,
    validateLoading,
    factureDialogVisible,
    factureLines,
    factureDate,
    factureTime,
    factureSaving,
    factureTotal,
    soinsList,
    previewDialogVisible,
    previewLoading,
    previewData,
    previewDialogTab,
    previewPayments,
    previewServicesTotal,
    formatFcfa,
    previewPaymentModeTag,
    previewPaymentRoleTag,
    handlePayAction,
    openPreviewDialog,
    printInvoice,
    submitPayment,
    confirmValidate,
    resetSelectedDevisPayments,
    selectPayTab,
    onPayDialogVisibleUpdate
} = useInvoiceBillingActions({
    onSettled: () => emit('refresh')
});

const factureContextMenu = ref(null);
const contextMenuFacture = ref(null);

const consultationContextMenu = ref(null);
const contextMenuConsultation = ref(null);

const detailsDialogVisible = ref(false);
const detailsLoading = ref(false);
const detailData = ref(null);

const editFactureDialogVisible = ref(false);
const editFactureConsultation = ref(null);
const editFactureLoading = ref(false);
const editFactureSaving = ref(false);
const editFactureLines = ref([]);
const editFactureDate = ref('');
const editFactureTime = ref('');

const cancelingConsultationId = ref(null);

const factureContextMenuItems = computed(() =>
    buildFactureContextMenuItems(contextMenuFacture.value, {
        onPay: (row) => handlePayAction(row),
        onPreview: (row) => openPreviewDialog(row),
        onPrint: (row) => printInvoice(row)
    })
);

const consultationContextMenuItems = computed(() =>
    buildConsultationContextMenuItems(
        contextMenuConsultation.value,
        {
            onDetails: (consultation) => openConsultationDetails(consultation),
            onCancel: (consultation) => askCancelConsultation(consultation),
            onEditInvoice: (consultation) => openEditFacture(consultation),
            onPayFacture: (facture) => handlePayAction(facture),
            onPreviewFacture: (facture) => openPreviewDialog(facture),
            onPrintFacture: (facture) => printInvoice(facture)
        },
        {
            canModifyInvoice: canModifyInvoiceByRole.value,
            factures: props.factures
        }
    )
);

const openFactureContextMenu = (event, facture) => {
    contextMenuFacture.value = facture;
    factureContextMenu.value?.show(event);
};

const openConsultationContextMenu = (event, consultation) => {
    contextMenuConsultation.value = consultation;
    consultationContextMenu.value?.show(event);
};

const displayedFactures = computed(() => {
    const list = Array.isArray(props.factures) ? props.factures : [];
    if (!showUnpaidOnly.value) return list;
    return list.filter((facture) => isUnpaidFacture(facture));
});

const medicalActs = computed(() => {
    const rows = (props.consultations || []).flatMap((consultation) =>
        (consultation.actes || []).map((acte) => ({
            ...acte,
            date: consultation.date,
            medecin: consultation.medecin,
            consultationId: consultation.id,
            label: acte.description || acte.type || 'Acte médical'
        }))
    );

    return rows.sort((left, right) => {
        const leftTime = new Date(left.date || 0).getTime();
        const rightTime = new Date(right.date || 0).getTime();
        return rightTime - leftTime;
    });
});

const medicalActsTotal = computed(() => medicalActs.value.reduce((sum, acte) => sum + Number(acte.montant ?? 0), 0));

const totalPaye = computed(() => props.paiements.reduce((sum, p) => sum + getPaiementMontant(p), 0));

const totalImpaye = computed(() => (Array.isArray(props.factures) ? props.factures : []).filter((f) => isUnpaidFacture(f)).reduce((sum, f) => sum + (Number(f.reste ?? f.montant ?? 0) || 0), 0));

const openConsultationDetails = async (consultation) => {
    if (!consultation?.id) return;
    detailsDialogVisible.value = true;
    detailsLoading.value = true;
    detailData.value = null;
    try {
        detailData.value = await fetchConsultationDetails(consultation.id, token);
    } catch (error) {
        logAppError('Erreur lors du chargement des détails de consultation', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les détails.', life: 3000 });
        detailsDialogVisible.value = false;
    } finally {
        detailsLoading.value = false;
    }
};

const askCancelConsultation = (consultation) => {
    confirm.require({
        group: 'cancel-consultation-dossier',
        message: 'Annuler cette consultation ? Cette action est irréversible.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, annuler',
        rejectLabel: 'Non',
        acceptClass: 'p-button-danger',
        accept: () => handleCancelConsultation(consultation)
    });
};

const handleCancelConsultation = async (consultation) => {
    if (!consultation?.id) return;
    cancelingConsultationId.value = consultation.id;
    try {
        await cancelConsultation(consultation.id, token);
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'Consultation supprimée.', life: 2500 });
        emit('refresh');
    } catch (error) {
        logAppError('Annulation impossible', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'annuler la consultation.", life: 3000 });
    } finally {
        cancelingConsultationId.value = null;
    }
};

const openEditFacture = async (consultation) => {
    if (!consultation?.id || !consultation.factModifiable) return;
    editFactureConsultation.value = consultation;
    editFactureDialogVisible.value = true;
    editFactureLoading.value = true;
    try {
        const invoice = await fetchConsultationInvoice(consultation.id, token);
        editFactureLines.value = invoice.lines;
        editFactureDate.value = invoice.date || '';
        editFactureTime.value = invoice.time || '';
    } catch (error) {
        logAppError('Erreur lors du chargement de la facture', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger la facture.', life: 3000 });
        editFactureDialogVisible.value = false;
    } finally {
        editFactureLoading.value = false;
    }
};

const closeEditFactureModal = (visible) => {
    editFactureDialogVisible.value = visible;
    if (!visible) {
        editFactureConsultation.value = null;
        editFactureLines.value = [];
        editFactureDate.value = '';
        editFactureTime.value = '';
    }
};

const handleSaveEditFacture = async (payload) => {
    if (!editFactureConsultation.value?.id) return;
    editFactureSaving.value = true;
    try {
        await updateConsultationInvoice(editFactureConsultation.value.id, payload, token);
        toast.add({ severity: 'success', summary: 'Facture mise à jour', detail: 'La facture a été enregistrée.', life: 2500 });
        editFactureDialogVisible.value = false;
        emit('refresh');
    } catch (error) {
        logAppError('Erreur lors de la sauvegarde de la facture', error);
        toast.add({ severity: 'error', summary: 'Erreur', detail: "Impossible d'enregistrer la facture.", life: 3000 });
    } finally {
        editFactureSaving.value = false;
    }
};

onMounted(async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        allowReceptionInvoiceModification.value = settings?.allowReceptionInvoiceModification === true;
    } catch (error) {
        logAppError('Erreur chargement paramètres facture', error);
        allowReceptionInvoiceModification.value = false;
    }
});

function formatDate(date) {
    if (!date) return '--';
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function getRdvStatus(rdv) {
    return rdv.statut || rdv.status || '—';
}

function getRdvLabel(rdv) {
    return rdv.type || rdv.motif || rdv.salle || 'Rendez-vous';
}

function getRdvDate(rdv) {
    return rdv.dateRdv || rdv.date || rdv.dateCreation || null;
}

function getRdvMedecin(rdv) {
    return rdv.medecinNom || rdv.medecin || '--';
}

function getRdvSmsReminder(rdv) {
    return rdv?.smsReminder || null;
}

function getSmsSeverity(reminder) {
    if (!reminder) return 'contrast';

    const status = String(reminder.status || '').toLowerCase();
    if (status === 'sent') return 'success';
    if (status === 'failed') return 'danger';
    if (status === 'sending') return 'info';
    return reminder?.isAutomatic ? 'warning' : 'secondary';
}

function formatDateTime(date) {
    if (!date) return '--';
    return new Date(date).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function getPaiementLabel(paiement) {
    return paiement.motif || paiement.libelle || paiement.designation || `Paiement #${paiement.id ?? ''}`.trim();
}

function getPaiementDate(paiement) {
    return paiement.date || paiement.datePaiement || paiement.createdAt || null;
}

function getPaiementMontant(paiement) {
    return Number(paiement.montant ?? paiement.amount ?? 0);
}

function getPaiementMode(paiement) {
    return paiement.mode || paiement.modePaiement || paiement.methode || '—';
}

function getFactureLabel(facture) {
    return facture.libelle || facture.designation || facture.motif || `Facture #${facture.id ?? ''}`.trim();
}

function getFactureDate(facture) {
    return facture.date || facture.dateFacture || facture.dateCreation || facture.createdAt || null;
}

function getConsultationDate(consultation) {
    return consultation.date || consultation.createdAt || consultation.created_at || null;
}

function getConsultationMedecin(consultation) {
    return consultation.medecin || consultation.medecinNom || '—';
}

function getConsultationMontant(consultation) {
    return Number(consultation.factureMontant ?? consultation.montant ?? 0);
}

function getConsultationStatut(consultation) {
    return consultation.statut === 1 || consultation.state === 1 ? 'Clôturée' : 'En cours';
}

function getConsultationStatusSeverity(stat) {
    return stat === 'Clôturée' ? 'success' : 'warning';
}

function getRDVStatusSeverity(status) {
    const severities = {
        Terminé: 'success',
        Confirmé: 'info',
        Planifié: 'warning',
        Annulé: 'danger',
        Reporté: 'secondary'
    };
    return severities[status] || 'info';
}

function getRDVStatusIcon(status) {
    const icons = {
        Terminé: 'pi pi-check-circle',
        Confirmé: 'pi pi-verified',
        Planifié: 'pi pi-calendar-plus',
        Annulé: 'pi pi-times-circle',
        Reporté: 'pi pi-calendar-times'
    };
    return icons[status] || 'pi pi-calendar';
}

function getRDVStatusColor(status) {
    const colors = {
        Terminé: { bg: 'bg-emerald-100 dark:bg-emerald-900/30', text: 'text-emerald-600 dark:text-emerald-400' },
        Confirmé: { bg: 'bg-blue-100 dark:bg-blue-900/30', text: 'text-blue-600 dark:text-blue-400' },
        Planifié: { bg: 'bg-amber-100 dark:bg-amber-900/30', text: 'text-amber-600 dark:text-amber-400' },
        Annulé: { bg: 'bg-red-100 dark:bg-red-900/30', text: 'text-red-600 dark:text-red-400' },
        Reporté: { bg: 'bg-surface-100 dark:bg-surface-700', text: 'text-surface-600 dark:text-surface-400' }
    };
    return colors[status] || { bg: 'bg-surface-100', text: 'text-surface-600' };
}
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden backdrop-blur-sm">
        <ConfirmPopup group="cancel-consultation-dossier" />
        <ContextMenu ref="factureContextMenu" :model="factureContextMenuItems" />
        <ContextMenu ref="consultationContextMenu" :model="consultationContextMenuItems" />

        <Tabs :value="activeTab" @update:value="activeTab = $event">
            <TabList class="flex flex-wrap gap-2 border-b border-surface-200/50 dark:border-surface-700/50" data-tour="patients-dossier.finance-tabs">
                <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
                    <span class="flex items-center gap-2">
                        <i :class="tab.icon"></i>
                        <span>{{ tab.label }}</span>
                    </span>
                </Tab>
            </TabList>
            <TabPanels class="p-5" data-tour="patients-dossier.finance-content">
                <TabPanel value="rdv">
                    <div v-if="rdvs.length" class="space-y-4">
                        <div v-for="rdv in rdvs" :key="rdv.id" class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-primary-300/50 dark:hover:border-primary-700/50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div :class="['p-2 rounded-lg', getRDVStatusColor(getRdvStatus(rdv)).bg]">
                                        <i :class="[getRDVStatusIcon(getRdvStatus(rdv)), getRDVStatusColor(getRdvStatus(rdv)).text]"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-surface-900 dark:text-surface-100">{{ getRdvLabel(rdv) }}</div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400">
                                            {{ formatDate(getRdvDate(rdv)) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <Tag :value="getRdvStatus(rdv)" :severity="getRDVStatusSeverity(getRdvStatus(rdv))" class="px-3 py-1 rounded-full" />
                                    <div class="text-sm text-surface-600 dark:text-surface-400 mt-1">{{ getRdvMedecin(rdv) }}</div>
                                </div>
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <Tag v-if="getRdvSmsReminder(rdv)" :value="`SMS: ${getRdvSmsReminder(rdv).label}`" :severity="getSmsSeverity(getRdvSmsReminder(rdv))" class="px-3 py-1 rounded-full" />
                                <span v-if="getRdvSmsReminder(rdv)?.sendAt" class="text-xs text-surface-500 dark:text-surface-400"> Programmation: {{ formatDateTime(getRdvSmsReminder(rdv).sendAt) }} </span>
                                <span v-if="getRdvSmsReminder(rdv)?.sentAt" class="text-xs text-surface-500 dark:text-surface-400"> Envoi: {{ formatDateTime(getRdvSmsReminder(rdv).sentAt) }} </span>
                            </div>
                            <div v-if="rdv.notes || getRdvSmsReminder(rdv)?.message || getRdvSmsReminder(rdv)?.lastError" class="mt-3 pt-3 border-t border-surface-200/50 dark:border-surface-700/50 space-y-2">
                                <p class="text-sm text-surface-700 dark:text-surface-300">{{ rdv.notes }}</p>
                                <p v-if="getRdvSmsReminder(rdv)?.message" class="text-sm text-surface-700 dark:text-surface-300">SMS: {{ getRdvSmsReminder(rdv).message }}</p>
                                <p v-if="getRdvSmsReminder(rdv)?.lastError" class="text-sm text-red-600 dark:text-red-400">Erreur SMS: {{ getRdvSmsReminder(rdv).lastError }}</p>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                            <i class="pi pi-calendar text-3xl text-surface-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300">Aucun rendez-vous</h4>
                        <p class="mt-1 max-w-md text-sm text-surface-500 dark:text-surface-400">Ce patient n’a pas encore de rendez-vous enregistré.</p>
                    </div>
                </TabPanel>

                <TabPanel value="paiements">
                    <div v-if="paiements.length" class="space-y-4">
                        <div v-for="paiement in paiements" :key="paiement.id" class="p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-surface-300/50 dark:hover:border-surface-600/50 transition-colors">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/30">
                                        <i class="pi pi-check-circle text-emerald-600 dark:text-emerald-400"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-surface-900 dark:text-surface-100">{{ getPaiementLabel(paiement) }}</div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400">
                                            {{ formatDate(getPaiementDate(paiement)) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-lg font-bold text-emerald-600 dark:text-emerald-400">{{ getPaiementMontant(paiement) }} F CFA</div>
                                    <div class="text-sm text-surface-600 dark:text-surface-400">{{ getPaiementMode(paiement) }}</div>
                                </div>
                            </div>
                            <div v-if="paiement.notes" class="mt-3 pt-3 border-t border-surface-200/50 dark:border-surface-700/50">
                                <p class="text-sm text-surface-700 dark:text-surface-300">{{ paiement.notes }}</p>
                            </div>
                        </div>

                        <div class="mt-6 border-t border-surface-200/50 pt-6 dark:border-surface-700/50">
                            <div class="grid grid-cols-2 gap-4">
                                <div class="rounded-xl border border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 p-3 text-center dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                                    <div class="text-sm text-emerald-700 dark:text-emerald-300">Total payé</div>
                                    <div class="text-xl font-bold text-emerald-900 dark:text-emerald-100">{{ totalPaye }} F CFA</div>
                                </div>
                                <div class="rounded-xl border border-red-200/50 bg-gradient-to-br from-red-50 to-red-100/50 p-3 text-center dark:border-red-800/50 dark:from-red-900/20 dark:to-red-800/20">
                                    <div class="text-sm text-red-700 dark:text-red-300">Impayés</div>
                                    <div class="text-xl font-bold text-red-900 dark:text-red-100">{{ totalImpaye }} F CFA</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                            <i class="pi pi-credit-card text-3xl text-surface-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300">Aucun paiement</h4>
                        <p class="mt-1 max-w-md text-sm text-surface-500 dark:text-surface-400">Aucun paiement n’a encore été enregistré pour ce patient.</p>
                    </div>
                </TabPanel>

                <TabPanel value="factures">
                    <div v-if="factures.length" class="space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-sm text-surface-500 dark:text-surface-400">Clic droit sur une facture pour Payer, Voir ou Imprimer.</p>
                            <ToggleButton v-model="showUnpaidOnly" onLabel="Impayées uniquement" offLabel="Toutes les factures" onIcon="pi pi-filter" offIcon="pi pi-list" class="w-56" data-tour="patients-dossier.factures-unpaid-toggle" />
                        </div>

                        <template v-if="displayedFactures.length">
                            <div
                                v-for="facture in displayedFactures"
                                :key="facture.id"
                                class="cursor-context-menu rounded-xl border border-surface-200/50 p-4 transition-colors hover:border-surface-300/50 dark:border-surface-700/50 dark:hover:border-surface-600/50"
                                @contextmenu.prevent="openFactureContextMenu($event, facture)"
                            >
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="shrink-0 rounded-lg bg-surface-100 p-2 dark:bg-surface-700">
                                            <i class="pi pi-file text-surface-600 dark:text-surface-300"></i>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate font-semibold text-surface-900 dark:text-surface-100">{{ getFactureLabel(facture) }}</div>
                                            <div class="text-sm text-surface-600 dark:text-surface-400">
                                                {{ formatDate(getFactureDate(facture)) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <div class="text-lg font-bold text-surface-900 dark:text-surface-100">
                                            {{ formatFactureFcfa(facture.montant) }}
                                        </div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400">Reste {{ formatFactureFcfa(facture.reste) }}</div>
                                        <Tag class="mt-1" :value="computeFactureStatus(facture).label" :severity="computeFactureStatus(facture).severity" />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                            <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                                <i class="pi pi-filter text-3xl text-surface-400"></i>
                            </div>
                            <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300">Aucune facture impayée</h4>
                            <p class="mt-1 max-w-md text-sm text-surface-500 dark:text-surface-400">Ce patient n’a pas de facture en attente de règlement.</p>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                            <i class="pi pi-file text-3xl text-surface-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300">Aucune facture</h4>
                        <p class="mt-1 max-w-md text-sm text-surface-500 dark:text-surface-400">Aucune facture n’est encore associée à ce patient.</p>
                    </div>
                </TabPanel>

                <TabPanel value="actes">
                    <div v-if="medicalActs.length" class="space-y-4">
                        <p class="text-sm text-surface-500 dark:text-surface-400">{{ medicalActs.length }} acte(s) · Total {{ formatFactureFcfa(medicalActsTotal) }}</p>
                        <DataTable :value="medicalActs" dataKey="id" paginator :rows="8" responsiveLayout="scroll" stripedRows class="text-sm">
                            <Column field="date" header="Date" sortable>
                                <template #body="{ data }">
                                    {{ formatDateTime(data.date) }}
                                </template>
                            </Column>
                            <Column field="label" header="Description" sortable>
                                <template #body="{ data }">
                                    <div class="font-medium text-surface-900 dark:text-surface-100">{{ data.label }}</div>
                                    <div v-if="data.type && data.type !== data.label" class="text-xs text-surface-500 dark:text-surface-400">
                                        {{ data.type }}
                                    </div>
                                </template>
                            </Column>
                            <Column field="dent" header="Dent" sortable>
                                <template #body="{ data }">
                                    {{ data.dent || '—' }}
                                </template>
                            </Column>
                            <Column field="medecin" header="Médecin" sortable>
                                <template #body="{ data }">
                                    {{ data.medecin || '—' }}
                                </template>
                            </Column>
                            <Column field="quantite" header="Quantité" sortable />
                            <Column field="montant" header="Montant" sortable>
                                <template #body="{ data }">
                                    {{ formatFactureFcfa(data.montant) }}
                                </template>
                            </Column>
                        </DataTable>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                            <i class="pi pi-list-check text-3xl text-surface-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300">Aucun acte médical</h4>
                        <p class="mt-1 max-w-md text-sm text-surface-500 dark:text-surface-400">Aucun acte médical n’a encore été enregistré pour ce patient.</p>
                    </div>
                </TabPanel>

                <TabPanel v-if="showConsultations" value="consultations">
                    <div v-if="consultations.length" class="space-y-4">
                        <p class="text-sm text-surface-500 dark:text-surface-400">Clic droit sur une consultation pour les actions disponibles.</p>
                        <div
                            v-for="consultation in consultations"
                            :key="consultation.id"
                            class="cursor-context-menu rounded-xl border border-surface-200/50 p-4 transition-colors hover:border-surface-300/50 dark:border-surface-700/50 dark:hover:border-surface-600/50"
                            @contextmenu.prevent="openConsultationContextMenu($event, consultation)"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-lg bg-surface-100 p-2 dark:bg-surface-700">
                                        <i class="pi pi-folder-open text-surface-600 dark:text-surface-300"></i>
                                    </div>
                                    <div>
                                        <div class="font-semibold text-surface-900 dark:text-surface-100">Consultation #{{ consultation.id }}</div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400">
                                            {{ formatDate(getConsultationDate(consultation)) }}
                                        </div>
                                        <div class="text-sm text-surface-600 dark:text-surface-400">
                                            {{ getConsultationMedecin(consultation) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <Tag :value="getConsultationStatut(consultation)" :severity="getConsultationStatusSeverity(getConsultationStatut(consultation))" class="rounded-full px-3 py-1" />
                                    <div class="mt-2 text-lg font-bold text-surface-900 dark:text-surface-100">{{ getConsultationMontant(consultation) }} F CFA</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                        <div class="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-800">
                            <i class="pi pi-folder-open text-3xl text-surface-400"></i>
                        </div>
                        <h4 class="text-lg font-semibold text-surface-700 dark:text-surface-300">Aucune consultation</h4>
                        <p class="mt-1 max-w-md text-sm text-surface-500 dark:text-surface-400">Aucune consultation n’est encore associée à ce patient.</p>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>

        <ConsultationDetailsDialog :visible="detailsDialogVisible" :details="detailData" :loading="detailsLoading" @update:visible="(val) => (detailsDialogVisible = val)" />

        <FactureModal
            :visible="editFactureDialogVisible"
            :lines="editFactureLines"
            :date="editFactureDate"
            :time="editFactureTime"
            :soins="soinsList"
            :loading="editFactureLoading"
            :saving="editFactureSaving"
            @update:visible="closeEditFactureModal"
            @save="handleSaveEditFacture"
        />

        <CaisseInvoiceDialogs
            :pay-dialog-visible="payDialogVisible"
            :selected-facture="selectedFacture"
            :pay-form="payForm"
            :classic-payment-options="classicPaymentOptions"
            :insurance-covered-amount="insuranceCoveredAmount"
            :insurance-rate="invoiceInsuranceRate"
            :patient-already-paid-amount="patientAlreadyPaidAmount"
            :patient-outstanding-amount="patientOutstandingAmount"
            :invoice-has-insurance="invoiceHasInsurance"
            :insurance-status-label="insuranceStatusLabel"
            :max-client-payment-amount="maxClientPaymentAmount"
            :remaining-after-pay="remainingAfterPay"
            :can-reset-invoice-payments="canResetInvoicePayments"
            :pay-loading="payLoading"
            :pay-tabs="payTabs"
            :active-pay-tab-id="activePayTabId"
            :prior-reliquat-total="priorReliquatTotal"
            :active-pay-tab-mode="activePayTabMode"
            :reset-payment-dialog-visible="resetPaymentDialogVisible"
            :reset-payments-loading="resetPaymentsLoading"
            :validate-dialog-visible="validateDialogVisible"
            :validate-loading="validateLoading"
            :facture-dialog-visible="factureDialogVisible"
            :facture-lines="factureLines"
            :facture-date="factureDate"
            :facture-time="factureTime"
            :facture-saving="factureSaving"
            :facture-total="factureTotal"
            :soins-list="soinsList"
            :preview-dialog-visible="previewDialogVisible"
            :preview-loading="previewLoading"
            :preview-data="previewData"
            :preview-dialog-tab="previewDialogTab"
            :preview-payments="previewPayments"
            :preview-services-total="previewServicesTotal"
            :format-fcfa="formatFcfa"
            :preview-payment-mode-tag="previewPaymentModeTag"
            :preview-payment-role-tag="previewPaymentRoleTag"
            @update:payDialogVisible="onPayDialogVisibleUpdate"
            @update:activePayTabId="selectPayTab"
            @update:resetPaymentDialogVisible="resetPaymentDialogVisible = $event"
            @update:validateDialogVisible="validateDialogVisible = $event"
            @update:factureDialogVisible="factureDialogVisible = $event"
            @update:factureDate="factureDate = $event"
            @update:factureTime="factureTime = $event"
            @update:previewDialogVisible="previewDialogVisible = $event"
            @update:previewDialogTab="previewDialogTab = $event"
            @submit-payment="submitPayment"
            @confirm-reset="resetSelectedDevisPayments"
            @confirm-validate="confirmValidate"
            @print-invoice="printInvoice()"
        />
    </div>
</template>
