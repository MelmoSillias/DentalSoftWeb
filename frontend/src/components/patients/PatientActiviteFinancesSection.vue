<script setup>
import CaisseInvoiceDialogs from '@/components/caisse/CaisseInvoiceDialogs.vue';
import ConsultationDetailsDialog from '@/components/consultations/ConsultationDetailsDialog.vue';
import FactureModal from '@/components/consultations/FactureModal.vue';
import PatientCabinetServicesPanel from '@/components/patients/PatientCabinetServicesPanel.vue';
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
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
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
    servicesCabinet: {
        type: Array,
        default: () => []
    },
    patientId: {
        type: [Number, String],
        default: null
    },
    patientName: {
        type: String,
        default: ''
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

const activeTab = ref('rdv');
const showUnpaidOnly = ref(false);

const rdvSearch = ref('');
const rdvStatusFilter = ref(null);
const paiementSearch = ref('');
const paiementModeFilter = ref(null);
const factureSearch = ref('');
const actesSearch = ref('');
const consultationSearch = ref('');
const consultationStatusFilter = ref(null);

const canModifyInvoiceByRole = computed(() => canUserModifyInvoice(auth.user, { allowReceptionInvoiceModification: allowReceptionInvoiceModification.value }));

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

const tabs = computed(() => {
    const base = [
        { id: 'rdv', label: 'Rendez-vous', icon: 'pi pi-calendar', badge: props.rdvs?.length || null },
        { id: 'paiements', label: 'Paiements', icon: 'pi pi-credit-card', badge: props.paiements?.length || null },
        { id: 'factures', label: 'Factures', icon: 'pi pi-file', badge: props.factures?.length || null },
        { id: 'actes', label: 'Actes', icon: 'pi pi-list-check', badge: medicalActs.value?.length || null },
        { id: 'services-cabinet', label: 'Services cabinet', icon: 'pi pi-building', badge: props.servicesCabinet?.length || null }
    ];

    if (props.showConsultations) {
        base.splice(4, 0, { id: 'consultations', label: 'Consultations', icon: 'pi pi-folder-open', badge: props.consultations?.length || null });
    }

    return base;
});

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

const medicalActsTotal = computed(() => medicalActs.value.reduce((sum, acte) => sum + Number(acte.montant ?? 0), 0));

const totalPaye = computed(() => props.paiements.reduce((sum, p) => sum + getPaiementMontant(p), 0));

const totalImpaye = computed(() => (Array.isArray(props.factures) ? props.factures : []).filter((f) => isUnpaidFacture(f)).reduce((sum, f) => sum + (Number(f.reste ?? f.montant ?? 0) || 0), 0));

const rdvStatusOptions = computed(() => {
    const values = [...new Set((props.rdvs || []).map((rdv) => getRdvStatus(rdv)).filter((v) => v && v !== '—'))];
    return [{ label: 'Tous les statuts', value: null }, ...values.map((value) => ({ label: value, value }))];
});

const paiementModeOptions = computed(() => {
    const values = [...new Set((props.paiements || []).map((p) => getPaiementMode(p)).filter((v) => v && v !== '—'))];
    return [{ label: 'Tous les modes', value: null }, ...values.map((value) => ({ label: value, value }))];
});

const consultationStatusOptions = [
    { label: 'Tous les statuts', value: null },
    { label: 'En cours', value: 'En cours' },
    { label: 'Clôturée', value: 'Clôturée' }
];

const filteredRdvs = computed(() => {
    const query = rdvSearch.value.trim().toLowerCase();
    return (props.rdvs || []).filter((rdv) => {
        if (rdvStatusFilter.value && getRdvStatus(rdv) !== rdvStatusFilter.value) return false;
        if (!query) return true;
        const haystack = [getRdvLabel(rdv), getRdvMedecin(rdv), getRdvStatus(rdv), rdv.notes].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(query);
    });
});

const filteredPaiements = computed(() => {
    const query = paiementSearch.value.trim().toLowerCase();
    return (props.paiements || []).filter((paiement) => {
        if (paiementModeFilter.value && getPaiementMode(paiement) !== paiementModeFilter.value) return false;
        if (!query) return true;
        const haystack = [getPaiementLabel(paiement), getPaiementMode(paiement), paiement.notes].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(query);
    });
});

const displayedFactures = computed(() => {
    const list = Array.isArray(props.factures) ? props.factures : [];
    const query = factureSearch.value.trim().toLowerCase();
    return list.filter((facture) => {
        if (showUnpaidOnly.value && !isUnpaidFacture(facture)) return false;
        if (!query) return true;
        const haystack = [getFactureLabel(facture), computeFactureStatus(facture).label].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(query);
    });
});

const filteredActes = computed(() => {
    const query = actesSearch.value.trim().toLowerCase();
    if (!query) return medicalActs.value;
    return medicalActs.value.filter((acte) => {
        const haystack = [acte.label, acte.type, acte.dent, acte.medecin].filter(Boolean).join(' ').toLowerCase();
        return haystack.includes(query);
    });
});

const filteredConsultations = computed(() => {
    const query = consultationSearch.value.trim().toLowerCase();
    return (props.consultations || []).filter((consultation) => {
        const statut = getConsultationStatut(consultation);
        if (consultationStatusFilter.value && statut !== consultationStatusFilter.value) return false;
        if (!query) return true;
        const haystack = [`#${consultation.id}`, getConsultationMedecin(consultation), statut].join(' ').toLowerCase();
        return haystack.includes(query);
    });
});

const rdvKpi = computed(() => {
    const list = filteredRdvs.value;
    const byStatus = (status) => list.filter((rdv) => getRdvStatus(rdv) === status).length;
    return {
        total: list.length,
        planifies: byStatus('Planifié') + byStatus('Confirmé'),
        termines: byStatus('Terminé'),
        annules: byStatus('Annulé') + byStatus('Reporté')
    };
});

const paiementsKpi = computed(() => {
    const list = filteredPaiements.value;
    const total = list.reduce((sum, p) => sum + getPaiementMontant(p), 0);
    return {
        count: list.length,
        total,
        modes: new Set(list.map((p) => getPaiementMode(p)).filter((m) => m && m !== '—')).size,
        impaye: totalImpaye.value
    };
});

const facturesKpi = computed(() => {
    const list = displayedFactures.value;
    const montant = list.reduce((sum, f) => sum + (Number(f.montant) || 0), 0);
    const reste = list.reduce((sum, f) => sum + (Number(f.reste) || 0), 0);
    const unpaid = list.filter((f) => isUnpaidFacture(f)).length;
    return { count: list.length, montant, reste, unpaid };
});

const actesKpi = computed(() => {
    const list = filteredActes.value;
    const total = list.reduce((sum, a) => sum + (Number(a.montant) || 0), 0);
    return {
        count: list.length,
        total,
        medecins: new Set(list.map((a) => a.medecin).filter(Boolean)).size
    };
});

const consultationsKpi = computed(() => {
    const list = filteredConsultations.value;
    const enCours = list.filter((c) => getConsultationStatut(c) === 'En cours').length;
    const cloturees = list.filter((c) => getConsultationStatut(c) === 'Clôturée').length;
    const montant = list.reduce((sum, c) => sum + getConsultationMontant(c), 0);
    return { count: list.length, enCours, cloturees, montant };
});

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
</script>

<template>
    <div class="page-section page-section--flush">
        <ConfirmPopup group="cancel-consultation-dossier" />
        <ContextMenu ref="factureContextMenu" :model="factureContextMenuItems" />
        <ContextMenu ref="consultationContextMenu" :model="consultationContextMenuItems" />

        <Tabs :value="activeTab" @update:value="activeTab = $event" class="dossier-folder__tabs">
            <TabList data-tour="patients-dossier.finance-tabs">
                <Tab v-for="tab in tabs" :key="tab.id" :value="tab.id">
                    <span class="dossier-folder__tab-label">
                        <i :class="tab.icon"></i>
                        <span class="hidden sm:inline">{{ tab.label }}</span>
                        <span v-if="tab.badge" class="dossier-folder__tab-badge">{{ tab.badge }}</span>
                    </span>
                </Tab>
            </TabList>
            <TabPanels class="!p-2 md:!p-3" data-tour="patients-dossier.finance-content">
                <!-- RDV -->
                <TabPanel value="rdv">
                    <div class="space-y-3">
                        <div class="page-kpi-grid page-kpi-grid--compact">
                            <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Total</p>
                                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ rdvKpi.total }}</p>
                                </div>
                                <i class="pi pi-calendar page-kpi-icon text-blue-500"></i>
                            </div>
                            <div class="page-kpi-card border-amber-200/50 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:border-amber-800/50 dark:from-amber-900/20 dark:to-amber-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">À venir</p>
                                    <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ rdvKpi.planifies }}</p>
                                </div>
                                <i class="pi pi-clock page-kpi-icon text-amber-500"></i>
                            </div>
                            <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Terminés</p>
                                    <p class="page-kpi-value text-emerald-900 dark:text-emerald-100">{{ rdvKpi.termines }}</p>
                                </div>
                                <i class="pi pi-check-circle page-kpi-icon text-emerald-500"></i>
                            </div>
                            <div class="page-kpi-card border-rose-200/50 bg-gradient-to-br from-rose-50 to-rose-100/50 dark:border-rose-800/50 dark:from-rose-900/20 dark:to-rose-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-rose-700 dark:text-rose-300">Annulés / reportés</p>
                                    <p class="page-kpi-value text-rose-900 dark:text-rose-100">{{ rdvKpi.annules }}</p>
                                </div>
                                <i class="pi pi-times-circle page-kpi-icon text-rose-500"></i>
                            </div>
                        </div>

                        <div class="page-filters !justify-start">
                            <div class="page-filter-item max-w-xs">
                                <label>Recherche</label>
                                <IconField>
                                    <InputIcon class="pi pi-search" />
                                    <InputText v-model="rdvSearch" placeholder="Motif, médecin…" class="w-full" />
                                </IconField>
                            </div>
                            <div class="page-filter-item">
                                <label>Statut</label>
                                <Select v-model="rdvStatusFilter" :options="rdvStatusOptions" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                        </div>

                        <div v-if="filteredRdvs.length" class="page-table-scroll">
                            <DataTable :value="filteredRdvs" dataKey="id" paginator :rows="8" :rowsPerPageOptions="[5, 8, 15]" responsiveLayout="scroll" stripedRows size="small" class="text-sm">
                                <Column header="Date" sortable sortField="dateRdv" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        {{ formatDate(getRdvDate(data)) }}
                                    </template>
                                </Column>
                                <Column header="Motif" style="min-width: 10rem">
                                    <template #body="{ data }">
                                        <div class="font-medium">{{ getRdvLabel(data) }}</div>
                                        <div v-if="data.notes" class="text-xs text-surface-500 dark:text-surface-400 line-clamp-1">{{ data.notes }}</div>
                                    </template>
                                </Column>
                                <Column header="Médecin" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ getRdvMedecin(data) }}
                                    </template>
                                </Column>
                                <Column header="Statut" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        <Tag :value="getRdvStatus(data)" :severity="getRDVStatusSeverity(getRdvStatus(data))" />
                                    </template>
                                </Column>
                                <Column header="SMS" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        <Tag v-if="getRdvSmsReminder(data)" :value="getRdvSmsReminder(data).label" :severity="getSmsSeverity(getRdvSmsReminder(data))" />
                                        <span v-else class="text-surface-400">—</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
                            <div class="dossier-state__icon"><i class="pi pi-calendar"></i></div>
                            <h4 class="dossier-state__title">{{ rdvs.length ? 'Aucun résultat' : 'Aucun rendez-vous' }}</h4>
                            <p class="dossier-state__text">
                                {{ rdvs.length ? 'Aucun rendez-vous ne correspond aux filtres.' : 'Ce patient n’a pas encore de rendez-vous enregistré.' }}
                            </p>
                        </div>
                    </div>
                </TabPanel>

                <!-- Paiements -->
                <TabPanel value="paiements">
                    <div class="space-y-3">
                        <div class="page-kpi-grid page-kpi-grid--compact">
                            <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Paiements</p>
                                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ paiementsKpi.count }}</p>
                                </div>
                                <i class="pi pi-list page-kpi-icon text-blue-500"></i>
                            </div>
                            <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Total payé</p>
                                    <p class="page-kpi-value truncate text-emerald-900 dark:text-emerald-100">{{ formatFactureFcfa(paiementsKpi.total) }}</p>
                                </div>
                                <i class="pi pi-check-circle page-kpi-icon text-emerald-500"></i>
                            </div>
                            <div class="page-kpi-card border-slate-200/50 bg-gradient-to-br from-slate-50 to-slate-100/50 dark:border-slate-800/50 dark:from-slate-900/20 dark:to-slate-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-slate-600 dark:text-slate-300">Modes</p>
                                    <p class="page-kpi-value text-slate-900 dark:text-surface-100">{{ paiementsKpi.modes }}</p>
                                </div>
                                <i class="pi pi-credit-card page-kpi-icon text-slate-500"></i>
                            </div>
                            <div class="page-kpi-card border-rose-200/50 bg-gradient-to-br from-rose-50 to-rose-100/50 dark:border-rose-800/50 dark:from-rose-900/20 dark:to-rose-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-rose-700 dark:text-rose-300">Impayés</p>
                                    <p class="page-kpi-value truncate text-rose-900 dark:text-rose-100">{{ formatFactureFcfa(paiementsKpi.impaye) }}</p>
                                </div>
                                <i class="pi pi-exclamation-circle page-kpi-icon text-rose-500"></i>
                            </div>
                        </div>

                        <div class="page-filters !justify-start">
                            <div class="page-filter-item max-w-xs">
                                <label>Recherche</label>
                                <IconField>
                                    <InputIcon class="pi pi-search" />
                                    <InputText v-model="paiementSearch" placeholder="Libellé, mode…" class="w-full" />
                                </IconField>
                            </div>
                            <div class="page-filter-item">
                                <label>Mode</label>
                                <Select v-model="paiementModeFilter" :options="paiementModeOptions" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                        </div>

                        <div v-if="filteredPaiements.length" class="page-table-scroll">
                            <DataTable :value="filteredPaiements" dataKey="id" paginator :rows="8" :rowsPerPageOptions="[5, 8, 15]" responsiveLayout="scroll" stripedRows size="small" class="text-sm">
                                <Column header="Date" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        {{ formatDate(getPaiementDate(data)) }}
                                    </template>
                                </Column>
                                <Column header="Libellé" style="min-width: 10rem">
                                    <template #body="{ data }">
                                        <div class="font-medium">{{ getPaiementLabel(data) }}</div>
                                        <div v-if="data.notes" class="text-xs text-surface-500 dark:text-surface-400 line-clamp-1">{{ data.notes }}</div>
                                    </template>
                                </Column>
                                <Column header="Mode" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        {{ getPaiementMode(data) }}
                                    </template>
                                </Column>
                                <Column header="Montant" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">{{ formatFactureFcfa(getPaiementMontant(data)) }}</span>
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
                            <div class="dossier-state__icon"><i class="pi pi-credit-card"></i></div>
                            <h4 class="dossier-state__title">{{ paiements.length ? 'Aucun résultat' : 'Aucun paiement' }}</h4>
                            <p class="dossier-state__text">
                                {{ paiements.length ? 'Aucun paiement ne correspond aux filtres.' : 'Aucun paiement n’a encore été enregistré pour ce patient.' }}
                            </p>
                        </div>
                    </div>
                </TabPanel>

                <!-- Factures -->
                <TabPanel value="factures">
                    <div class="space-y-3">
                        <div class="page-kpi-grid page-kpi-grid--compact">
                            <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Factures</p>
                                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ facturesKpi.count }}</p>
                                </div>
                                <i class="pi pi-file page-kpi-icon text-blue-500"></i>
                            </div>
                            <div class="page-kpi-card border-slate-200/50 bg-gradient-to-br from-slate-50 to-slate-100/50 dark:border-slate-800/50 dark:from-slate-900/20 dark:to-slate-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-slate-600 dark:text-slate-300">Montant</p>
                                    <p class="page-kpi-value truncate text-slate-900 dark:text-surface-100">{{ formatFactureFcfa(facturesKpi.montant) }}</p>
                                </div>
                                <i class="pi pi-money-bill page-kpi-icon text-slate-500"></i>
                            </div>
                            <div class="page-kpi-card border-amber-200/50 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:border-amber-800/50 dark:from-amber-900/20 dark:to-amber-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">Reste</p>
                                    <p class="page-kpi-value truncate text-amber-900 dark:text-amber-100">{{ formatFactureFcfa(facturesKpi.reste) }}</p>
                                </div>
                                <i class="pi pi-wallet page-kpi-icon text-amber-500"></i>
                            </div>
                            <div class="page-kpi-card border-rose-200/50 bg-gradient-to-br from-rose-50 to-rose-100/50 dark:border-rose-800/50 dark:from-rose-900/20 dark:to-rose-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-rose-700 dark:text-rose-300">Impayées</p>
                                    <p class="page-kpi-value text-rose-900 dark:text-rose-100">{{ facturesKpi.unpaid }}</p>
                                </div>
                                <i class="pi pi-exclamation-triangle page-kpi-icon text-rose-500"></i>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-end justify-between gap-2">
                            <div class="page-filters !justify-start !w-auto flex-1">
                                <div class="page-filter-item max-w-xs">
                                    <label>Recherche</label>
                                    <IconField>
                                        <InputIcon class="pi pi-search" />
                                        <InputText v-model="factureSearch" placeholder="Libellé…" class="w-full" />
                                    </IconField>
                                </div>
                            </div>
                            <ToggleButton
                                v-model="showUnpaidOnly"
                                onLabel="Impayées"
                                offLabel="Toutes"
                                onIcon="pi pi-filter"
                                offIcon="pi pi-list"
                                class="w-36"
                                data-tour="patients-dossier.factures-unpaid-toggle"
                            />
                        </div>

                        <p v-if="factures.length" class="text-xs text-surface-500 dark:text-surface-400 m-0">Clic droit sur une facture pour Payer, Voir ou Imprimer.</p>

                        <div v-if="displayedFactures.length" class="page-table-scroll">
                            <DataTable
                                :value="displayedFactures"
                                dataKey="id"
                                paginator
                                :rows="8"
                                :rowsPerPageOptions="[5, 8, 15]"
                                responsiveLayout="scroll"
                                stripedRows
                                size="small"
                                class="text-sm"
                                rowHover
                                @row-contextmenu="openFactureContextMenu($event.originalEvent, $event.data)"
                            >
                                <Column header="Date" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        {{ formatDate(getFactureDate(data)) }}
                                    </template>
                                </Column>
                                <Column header="Libellé" style="min-width: 10rem">
                                    <template #body="{ data }">
                                        <span class="font-medium">{{ getFactureLabel(data) }}</span>
                                    </template>
                                </Column>
                                <Column header="Montant" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ formatFactureFcfa(data.montant) }}
                                    </template>
                                </Column>
                                <Column header="Reste" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        {{ formatFactureFcfa(data.reste) }}
                                    </template>
                                </Column>
                                <Column header="Statut" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        <Tag :value="computeFactureStatus(data).label" :severity="computeFactureStatus(data).severity" />
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
                            <div class="dossier-state__icon"><i class="pi pi-file"></i></div>
                            <h4 class="dossier-state__title">
                                {{ factures.length ? (showUnpaidOnly ? 'Aucune facture impayée' : 'Aucun résultat') : 'Aucune facture' }}
                            </h4>
                            <p class="dossier-state__text">
                                {{
                                    factures.length
                                        ? showUnpaidOnly
                                            ? 'Ce patient n’a pas de facture en attente de règlement.'
                                            : 'Aucune facture ne correspond à la recherche.'
                                        : 'Aucune facture n’est encore associée à ce patient.'
                                }}
                            </p>
                        </div>
                    </div>
                </TabPanel>

                <!-- Actes -->
                <TabPanel value="actes">
                    <div class="space-y-3">
                        <div class="page-kpi-grid page-kpi-grid--compact">
                            <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Actes</p>
                                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ actesKpi.count }}</p>
                                </div>
                                <i class="pi pi-list-check page-kpi-icon text-blue-500"></i>
                            </div>
                            <div class="page-kpi-card border-violet-200/50 bg-gradient-to-br from-violet-50 to-violet-100/50 dark:border-violet-800/50 dark:from-violet-900/20 dark:to-violet-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-violet-700 dark:text-violet-300">Total</p>
                                    <p class="page-kpi-value truncate text-violet-900 dark:text-violet-100">{{ formatFactureFcfa(actesKpi.total) }}</p>
                                </div>
                                <i class="pi pi-money-bill page-kpi-icon text-violet-500"></i>
                            </div>
                            <div class="page-kpi-card border-slate-200/50 bg-gradient-to-br from-slate-50 to-slate-100/50 dark:border-slate-800/50 dark:from-slate-900/20 dark:to-slate-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-slate-600 dark:text-slate-300">Médecins</p>
                                    <p class="page-kpi-value text-slate-900 dark:text-surface-100">{{ actesKpi.medecins }}</p>
                                </div>
                                <i class="pi pi-user page-kpi-icon text-slate-500"></i>
                            </div>
                            <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Tous actes</p>
                                    <p class="page-kpi-value truncate text-emerald-900 dark:text-emerald-100">{{ formatFactureFcfa(medicalActsTotal) }}</p>
                                </div>
                                <i class="pi pi-chart-bar page-kpi-icon text-emerald-500"></i>
                            </div>
                        </div>

                        <div class="page-filters !justify-start">
                            <div class="page-filter-item max-w-xs">
                                <label>Recherche</label>
                                <IconField>
                                    <InputIcon class="pi pi-search" />
                                    <InputText v-model="actesSearch" placeholder="Description, dent, médecin…" class="w-full" />
                                </IconField>
                            </div>
                        </div>

                        <div v-if="filteredActes.length" class="page-table-scroll">
                            <DataTable :value="filteredActes" dataKey="id" paginator :rows="8" :rowsPerPageOptions="[5, 8, 15]" responsiveLayout="scroll" stripedRows size="small" class="text-sm">
                                <Column field="date" header="Date" sortable style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ formatDateTime(data.date) }}
                                    </template>
                                </Column>
                                <Column field="label" header="Description" sortable style="min-width: 10rem">
                                    <template #body="{ data }">
                                        <div class="font-medium">{{ data.label }}</div>
                                        <div v-if="data.type && data.type !== data.label" class="text-xs text-surface-500 dark:text-surface-400">{{ data.type }}</div>
                                    </template>
                                </Column>
                                <Column field="dent" header="Dent" sortable style="min-width: 5rem">
                                    <template #body="{ data }">
                                        {{ data.dent || '—' }}
                                    </template>
                                </Column>
                                <Column field="medecin" header="Médecin" sortable style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ data.medecin || '—' }}
                                    </template>
                                </Column>
                                <Column field="quantite" header="Qté" sortable style="min-width: 4rem" />
                                <Column field="montant" header="Montant" sortable style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ formatFactureFcfa(data.montant) }}
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
                            <div class="dossier-state__icon"><i class="pi pi-list-check"></i></div>
                            <h4 class="dossier-state__title">{{ medicalActs.length ? 'Aucun résultat' : 'Aucun acte médical' }}</h4>
                            <p class="dossier-state__text">
                                {{ medicalActs.length ? 'Aucun acte ne correspond à la recherche.' : 'Aucun acte médical n’a encore été enregistré pour ce patient.' }}
                            </p>
                        </div>
                    </div>
                </TabPanel>

                <!-- Consultations -->
                <TabPanel v-if="showConsultations" value="consultations">
                    <div class="space-y-3">
                        <div class="page-kpi-grid page-kpi-grid--compact">
                            <div class="page-kpi-card border-blue-200/50 bg-gradient-to-br from-blue-50 to-blue-100/50 dark:border-blue-800/50 dark:from-blue-900/20 dark:to-blue-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-blue-700 dark:text-blue-300">Total</p>
                                    <p class="page-kpi-value text-blue-900 dark:text-blue-100">{{ consultationsKpi.count }}</p>
                                </div>
                                <i class="pi pi-folder-open page-kpi-icon text-blue-500"></i>
                            </div>
                            <div class="page-kpi-card border-amber-200/50 bg-gradient-to-br from-amber-50 to-amber-100/50 dark:border-amber-800/50 dark:from-amber-900/20 dark:to-amber-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">En cours</p>
                                    <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ consultationsKpi.enCours }}</p>
                                </div>
                                <i class="pi pi-clock page-kpi-icon text-amber-500"></i>
                            </div>
                            <div class="page-kpi-card border-emerald-200/50 bg-gradient-to-br from-emerald-50 to-emerald-100/50 dark:border-emerald-800/50 dark:from-emerald-900/20 dark:to-emerald-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Clôturées</p>
                                    <p class="page-kpi-value text-emerald-900 dark:text-emerald-100">{{ consultationsKpi.cloturees }}</p>
                                </div>
                                <i class="pi pi-check-circle page-kpi-icon text-emerald-500"></i>
                            </div>
                            <div class="page-kpi-card border-violet-200/50 bg-gradient-to-br from-violet-50 to-violet-100/50 dark:border-violet-800/50 dark:from-violet-900/20 dark:to-violet-800/20">
                                <div class="min-w-0">
                                    <p class="page-kpi-label text-violet-700 dark:text-violet-300">Montant</p>
                                    <p class="page-kpi-value truncate text-violet-900 dark:text-violet-100">{{ formatFactureFcfa(consultationsKpi.montant) }}</p>
                                </div>
                                <i class="pi pi-money-bill page-kpi-icon text-violet-500"></i>
                            </div>
                        </div>

                        <div class="page-filters !justify-start">
                            <div class="page-filter-item max-w-xs">
                                <label>Recherche</label>
                                <IconField>
                                    <InputIcon class="pi pi-search" />
                                    <InputText v-model="consultationSearch" placeholder="N°, médecin…" class="w-full" />
                                </IconField>
                            </div>
                            <div class="page-filter-item">
                                <label>Statut</label>
                                <Select v-model="consultationStatusFilter" :options="consultationStatusOptions" optionLabel="label" optionValue="value" class="w-full" />
                            </div>
                        </div>

                        <p v-if="consultations.length" class="text-xs text-surface-500 dark:text-surface-400 m-0">Clic droit sur une consultation pour les actions disponibles.</p>

                        <div v-if="filteredConsultations.length" class="page-table-scroll">
                            <DataTable
                                :value="filteredConsultations"
                                dataKey="id"
                                paginator
                                :rows="8"
                                :rowsPerPageOptions="[5, 8, 15]"
                                responsiveLayout="scroll"
                                stripedRows
                                size="small"
                                class="text-sm"
                                rowHover
                                @row-contextmenu="openConsultationContextMenu($event.originalEvent, $event.data)"
                            >
                                <Column header="Date" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        {{ formatDate(getConsultationDate(data)) }}
                                    </template>
                                </Column>
                                <Column header="Consultation" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        <span class="font-medium">#{{ data.id }}</span>
                                    </template>
                                </Column>
                                <Column header="Médecin" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ getConsultationMedecin(data) }}
                                    </template>
                                </Column>
                                <Column header="Statut" style="min-width: 7rem">
                                    <template #body="{ data }">
                                        <Tag :value="getConsultationStatut(data)" :severity="getConsultationStatusSeverity(getConsultationStatut(data))" />
                                    </template>
                                </Column>
                                <Column header="Montant" style="min-width: 8rem">
                                    <template #body="{ data }">
                                        {{ formatFactureFcfa(getConsultationMontant(data)) }}
                                    </template>
                                </Column>
                            </DataTable>
                        </div>
                        <div v-else class="dossier-state dossier-state--dashed py-8" style="box-shadow: none">
                            <div class="dossier-state__icon"><i class="pi pi-folder-open"></i></div>
                            <h4 class="dossier-state__title">{{ consultations.length ? 'Aucun résultat' : 'Aucune consultation' }}</h4>
                            <p class="dossier-state__text">
                                {{ consultations.length ? 'Aucune consultation ne correspond aux filtres.' : 'Aucune consultation n’est encore associée à ce patient.' }}
                            </p>
                        </div>
                    </div>
                </TabPanel>

                <!-- Services cabinet -->
                <TabPanel value="services-cabinet">
                    <PatientCabinetServicesPanel
                        compact
                        :patient-id="patientId"
                        :patient-name="patientName"
                        :services="servicesCabinet"
                        @refresh="emit('refresh')"
                    />
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
