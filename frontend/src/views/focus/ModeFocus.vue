<script setup>
import ConsultationDetailsDialog from '@/components/consultations/ConsultationDetailsDialog.vue';
import QuickClotureConsultationDialog from '@/components/consultations/QuickClotureConsultationDialog.vue';
import CaisseInvoiceDialogs from '@/components/caisse/CaisseInvoiceDialogs.vue';
import FocusMedecinView from '@/components/focus/FocusMedecinView.vue';
import FocusReceptionView from '@/components/focus/FocusReceptionView.vue';
import FormCreateConsultation from '@/components/patients/FormCreateConsultation.vue';
import FormPatient from '@/components/patients/FormPatient.vue';
import { usePrinter } from '@/composables/usePrinter';
import { useFocusRealtime } from '@/composables/useFocusRealtime';
import { defaultSoinList, fetchConsultationDetails, fetchConsultationInvoice, fetchConsultationsByDate, fetchFocusReceptionData, normalizeSoinList, updateConsultationInvoice, cancelConsultation } from '@/services/consultations';
import {  getDefaultClassicMethod } from '@/utils/paymentMethodUtils';
import { fetchAssurances, fetchFactureDetail, payFacture, resetFacturePayments, validateEmptyFacture } from '@/services/caisseService';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { fetchInvoicePrintData, fetchReceiptPrintData } from '@/services/printService';
import { checkConsultationActive, deleteConsultation } from '@/services/patients';
import { fetchPatientById, normalizePatient } from '@/services/patients';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';
import { sendInvoiceSms } from '@/services/smsService';
import { useAssurancesStore } from '@/stores/assurances';
import { useAuthStore } from '@/stores/auth';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import ConfirmPopup from 'primevue/confirmpopup';
import Dialog from 'primevue/dialog';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { computed, defineAsyncComponent, onMounted,  onBeforeUnmount, ref, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';

const FocusRendezVousView = defineAsyncComponent(() => import('@/views/agenda/RendezVous.vue'));

const auth = useAuthStore();
const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();
const assurancesStore = useAssurancesStore();
const paymentMethodsStore = usePaymentMethodsStore();

const loading = ref(false);
const consultations = ref([]);
const allowReceptionQuickClose = ref(true);
const hidePatientDossierForMedecins = ref(false);
const hidePatientPhoneForMedecins = ref(false);
const soinsList = ref([...defaultSoinList]);
const selectedMode = ref('reception');
const showCompletedSecretary = ref(true);
const showCompletedMedecin = ref(false);
const selectedConsultationId = ref(null);
const selectedPatient = ref(null);
const receptionRecentPatients = ref([]);
const receptionBillingByConsultation = ref({});
const detailsDialogVisible = ref(false);
const detailsLoading = ref(false);
const detailData = ref(null);
const factureDialogVisible = ref(false);
const factureSaving = ref(false);
const factureLines = ref([]);
const factureConsultation = ref(null);
const paymentMethods = ref([]);
const assurances = ref([]);
const payDialogVisible = ref(false);
const selectedFacture = ref(null);
const paymentDialogTab = ref('client');

const todayApiDate = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};


const payForm = ref({
    montant: 0,
    modeId: null,
    date: todayApiDate(),
    time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false }),
    insuranceEnabled: false,
    assuranceId: null,
    insuranceRate: 0
});
const validateDialogVisible = ref(false);
const validateLoading = ref(false);
const pendingFacture = ref(null);
const resetPaymentDialogVisible = ref(false);
const resetPaymentsLoading = ref(false);
const previewDialogVisible = ref(false);
const previewLoading = ref(false);
const previewData = ref(null);
const previewDialogTab = ref('services');
const payLoading = ref(false);
const quickDialogVisible = ref(false);
const quickDialogConsultation = ref(null);
const quickDialogActionMode = ref('continue');
const actionChoiceByConsultation = ref({});
const createPatientDialogVisible = ref(false);
const createConsultationDialogVisible = ref(false);
const createConsultationPreSelectedPatient = ref(null);
const createConsultationLoading = ref({});
const consultationToolbarLoading = ref(false);
const showActiveConsultWarn = ref(false);
const activeConsultWarnPatient = ref(null);
const activeConsultInfo = ref({ hasActive: false, consultationId: null, hasFiche: false });
const editPatientDialogVisible = ref(false);
const patientToEdit = ref(null);
const initialized = ref(false);
const isRealtimeRefreshing = ref(false);
const hasInitialLoadCompleted = ref(false);

const roles = computed(() => auth.user?.roles || []);
const isAdmin = computed(() => roles.value.includes('ROLE_ADMIN'));
const isMedecin = computed(() => roles.value.includes('ROLE_MEDECIN'));
const isReception = computed(() => roles.value.includes('ROLE_RECEPTION') || roles.value.includes('ROLE_RECEPTIONNISTE') || roles.value.includes('ROLE_SECRETAIRE'));
const isRestrictedMedecin = computed(() => isMedecin.value && !isAdmin.value);
const shouldHidePatientDossierForMedecin = computed(() => isRestrictedMedecin.value && hidePatientDossierForMedecins.value);
const shouldHidePatientPhoneForMedecin = computed(() => isRestrictedMedecin.value && hidePatientPhoneForMedecins.value);
const availableModes = computed(() => {
    const rdvMode = { label: 'Rendez-vous', value: 'rdv' };

    if (isAdmin.value) {
        return [
            { label: 'Reception', value: 'reception' },
            { label: 'Dentiste', value: 'medecin' },
            rdvMode
        ];
    }
    if (isMedecin.value) {
        return [{ label: 'Dentiste', value: 'medecin' }, rdvMode];
    }
    return [{ label: 'Reception', value: 'reception' }, rdvMode];
});

const selectedModeBorderClass = computed(() => {
    if (selectedMode.value === 'reception') return 'border-primary-500';
    if (selectedMode.value === 'medecin') return 'border-cyan-500';
    return 'border-amber-500';
});

if (isMedecin.value && !isAdmin.value) {
    selectedMode.value = 'medecin';
}


const initialsFromName = (value) => String(value || '')
    .split(' ')
    .filter(Boolean)
    .map((item) => item.charAt(0))
    .join('')
    .slice(0, 2)
    .toUpperCase();

const resolvePatientPhoto = (value = {}) => (
    value.photo
    || value.photoUrl
    || value.photo_url
    || value.patientPhoto
    || value.patient_photo
    || null
);

const currentConsultation = computed(() => consultations.value.find((item) => item.id === selectedConsultationId.value) || null);
    const isInsurancePayment = (payment) => {
        const role = String(payment?.rolePaiement || '').toLowerCase();
        const mode = String(payment?.mode || '').toLowerCase();

        return role === 'insurance' || mode.includes('assur');
    };

const currentReceptionBilling = computed(() => {
    if (!currentConsultation.value?.id) return null;
    return receptionBillingByConsultation.value?.[currentConsultation.value.id] || null;
});
const currentReceptionInvoiceRow = computed(() => {
    if (!currentConsultation.value || !currentReceptionBilling.value?.invoiceId) {
        return null;
    }

    const payments = Array.isArray(currentReceptionBilling.value.payments) ? currentReceptionBilling.value.payments : [];
    const insurancePayments = payments.filter((payment) => isInsurancePayment(payment));
    const directPayments = payments.filter((payment) => !isInsurancePayment(payment));
    const latestInsurancePayment = insurancePayments[0] || null;
    const total = Number(currentReceptionBilling.value.total ?? 0) || 0;
    const remaining = Number(currentReceptionBilling.value.remaining ?? 0) || 0;

    return {
        id: currentReceptionBilling.value.invoiceId,
        consultation: currentConsultation.value.id,
        date: currentConsultation.value.createdAt,
        montant: total,
        reste: remaining,
        statut: remaining === 0 ? 1 : 0,
        isRegle: remaining === 0,
        patient: currentConsultation.value.patient,
        telephone: currentConsultation.value.patient?.telephone || currentConsultation.value.patientPhone || '',
        insurance: {
            hasInsurance: insurancePayments.length > 0,
            insuranceStatus: insurancePayments.some((payment) => payment?.status === 'pending') ? 'pending' : 'validated',
            assuranceId: null,
            insuranceModeLabel: latestInsurancePayment?.mode ?? null,
            insuranceRate: latestInsurancePayment?.insuranceRate ?? 0,
            insuranceAmount: insurancePayments.reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0),
            insurancePaidAmount: insurancePayments.reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0),
            insurancePendingAmount: insurancePayments.filter((payment) => payment?.status === 'pending').reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0),
            insuranceTransactionId: null,
            insurancePaymentId: latestInsurancePayment?.id ?? null,
            patientPaidAmount: directPayments.reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0),
            patientRemainingAmount: remaining
        }
    };
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

const currentConsultationClosed = computed(() => Number(currentConsultation.value?.state) === 1);

const focusStats = computed(() => {
    const total = consultations.value.length;
    const closed = consultations.value.filter((item) => Number(item.state) === 1).length;
    const pending = total - closed;
    return { total, closed, pending };
});

const showFocusSkeleton = computed(() => loading.value && !hasInitialLoadCompleted.value);

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const activeInvoiceContext = computed(() => {
    if (selectedFacture.value?.id) {
        return selectedFacture.value;
    }

    if (previewData.value?.id) {
        return previewData.value;
    }

    return null;
});

const selectedFactureInsurance = computed(() => activeInvoiceContext.value?.insurance || null);
const invoiceHasInsurance = computed(() => selectedFactureInsurance.value?.hasInsurance === true);
const effectiveInsuranceRate = computed(() => {
    if (invoiceHasInsurance.value) {
        return Number(selectedFactureInsurance.value?.insuranceRate) || 0;
    }
    if (payForm.value.insuranceEnabled) {
        return Number(payForm.value.insuranceRate) || 0;
    }
    return 0;
});
const effectiveInsuranceAmount = computed(() => {
    if (invoiceHasInsurance.value) {
        return Number(selectedFactureInsurance.value?.insuranceAmount) || 0;
    }
    if (!selectedFacture.value || !payForm.value.insuranceEnabled) {
        return 0;
    }
    const baseAmount = Number(selectedFacture.value.reste) || 0;
    return Math.max(0, (baseAmount * effectiveInsuranceRate.value) / 100);
});
const patientAlreadyPaidAmount = computed(() => Number(selectedFactureInsurance.value?.patientPaidAmount) || 0);
const insuranceSectionDisabledReason = computed(() => {
    if (invoiceHasInsurance.value) return 'Une assurance est déjà liée à cette facture.';
    if (patientAlreadyPaidAmount.value > 0) return 'Le paiement assurance n’est plus modifiable car un règlement client existe déjà.';
    if (!selectedFacture.value || (Number(selectedFacture.value.montant) || 0) <= 0 || (Number(selectedFacture.value.reste) || 0) <= 0) return 'Le paiement assurance n’est disponible que pour une facture avec un montant restant dû.';
    return null;
});
const insuranceStatusLabel = computed(() => {
    if (!invoiceHasInsurance.value) return 'Aucune assurance';
    return selectedFactureInsurance.value?.insuranceStatus === 'pending' ? 'Assurance en attente' : 'Assurance enregistrée';
});
const insuranceStatusSeverity = computed(() => {
    if (!invoiceHasInsurance.value) return 'contrast';
    return selectedFactureInsurance.value?.insuranceStatus === 'pending' ? 'warning' : 'success';
});
const previewPayments = computed(() => Array.isArray(previewData.value?.paiements) ? previewData.value.paiements : []);
const previewPaymentRoleTag = (payment) => {
        if (payment?.status === 'pending' && isInsurancePayment(payment)) {
        return payment?.status === 'pending'
            ? { label: 'Assurance en attente', severity: 'warning' }
            : { label: 'Assurance', severity: 'info' };
    }

        if (isInsurancePayment(payment)) {
            return { label: 'Assurance', severity: 'info' };
        }

    return { label: 'Client', severity: 'success' };
};
const previewPaymentModeTag = (payment) => {
        if (payment?.status === 'pending' && isInsurancePayment(payment)) {
        return {
            label: payment?.mode || 'Assurance',
            severity: payment?.status === 'pending' ? 'warning' : 'info'
        };
    }

        if (isInsurancePayment(payment)) {
            return {
                label: payment?.mode || 'Assurance',
                severity: 'info'
            };
        }

    return { label: payment?.mode || '—', severity: 'success' };
};
const previewServicesTotal = computed(() => (previewData.value?.contenus || []).reduce((sum, line) => sum + (Number(line?.total) || 0), 0));
const patientOutstandingAmount = computed(() => {
    if (!selectedFacture.value) return 0;
    if (invoiceHasInsurance.value) return Math.max(0, Number(selectedFacture.value.reste) || 0);
    const total = Number(selectedFacture.value.montant) || 0;
    return Math.max(0, total - patientAlreadyPaidAmount.value - effectiveInsuranceAmount.value);
});
const insuranceHelperMessage = computed(() => 'Le paiement assurance sera créé automatiquement avec une transaction en attente.');
const maxClientPaymentAmount = computed(() => {
    if (!selectedFacture.value) return 0;
    const base = Number(selectedFacture.value.reste) || 0;
    const reservedInsurance = invoiceHasInsurance.value ? 0 : effectiveInsuranceAmount.value;
    return Math.max(0, base - reservedInsurance);
});
const canResetInvoicePayments = computed(() => {
    if (!isAdmin.value || !activeInvoiceContext.value) return false;
    return patientAlreadyPaidAmount.value > 0 || invoiceHasInsurance.value || (Number(activeInvoiceContext.value.reste) || 0) !== (Number(activeInvoiceContext.value.montant) || 0);
});
const remainingAfterPay = computed(() => {
    if (!selectedFacture.value) return 0;
    const reste = Number(selectedFacture.value.reste) || 0;
    const montantPatient = Number(payForm.value.montant) || 0;
    const insuranceAmount = invoiceHasInsurance.value ? 0 : effectiveInsuranceAmount.value;
    return Math.max(0, reste - montantPatient - insuranceAmount);
});
const classicPaymentOptions = computed(() =>
    (paymentMethods.value || [])
        .filter((method) => method?.actif !== false)
        .map((method) => ({ label: method.libelle, value: method.id, disabled: false }))
);
const assuranceOptions = computed(() =>
    (assurances.value || [])
        .filter((item) => item?.actif !== false)
        .map((item) => ({ label: item?.nom || item?.libelle || 'Assurance', value: item?.id }))
);
const selectedAssurance = computed(() =>
    (assurances.value || []).find((item) => Number(item?.id) === Number(payForm.value.assuranceId))
    || (selectedFactureInsurance.value?.insuranceModeLabel ? { nom: selectedFactureInsurance.value.insuranceModeLabel } : null)
);

const resolveAssuranceDefaultRate = (assurance) => {
    if (!assurance) {
        return 0;
    }

    return Math.max(0, Math.min(100, Number(assurance?.defaultRate ?? assurance?.tauxParDefaut ?? 0) || 0));
};
const insuranceCoveredAmount = computed(() => {
    if (invoiceHasInsurance.value) return Number(selectedFactureInsurance.value?.insuranceAmount) || 0;
    return effectiveInsuranceAmount.value;
});
const invoiceAllowsInsurance = computed(() => {
    if (!selectedFacture.value) return false;
    const total = Number(selectedFacture.value.montant) || 0;
    const reste = Number(selectedFacture.value.reste) || 0;
    return total > 0 && reste > 0 && !invoiceHasInsurance.value && patientAlreadyPaidAmount.value <= 0;
});
const requiresClassicPayment = computed(() => (Number(payForm.value.montant) || 0) > 0);
const factureTotal = computed(() => factureLines.value.reduce((sum, line) => sum + (Number(line.prix) || 0) * (Number(line.quantite) || 0), 0));

const normalizePatientForCard = (payload = {}) => {
    const normalized = normalizePatient(payload || {});
    const fullName = `${normalized.nom || ''} ${normalized.prenom || ''}`.trim();
    const maskedPhone = shouldHidePatientPhoneForMedecin.value ? 'Masqué par l\'administrateur' : (normalized.telephone || '--');
    const resolvedPhoto = resolvePatientPhoto(payload) || resolvePatientPhoto(normalized);
    return {
        ...payload,
        ...normalized,
        photo: resolvedPhoto,
        initials: initialsFromName(fullName || payload.fullname || payload.nom),
        numeroDossier: payload.numeroDossier || payload.numero_dossier || payload.code || `PAT-${normalized.id || '--'}`,
        age: normalized.age ?? (normalized.dateNaissance ? Math.max(0, Math.floor((Date.now() - new Date(normalized.dateNaissance).getTime()) / (1000 * 60 * 60 * 24 * 365.25))) : 0),
        groupeSanguin: normalized.groupeSanguin || '--',
        telephone: maskedPhone,
        email: normalized.email || '--',
        adresse: normalized.adresse || '--',
        antecedents: Array.isArray(payload.antecedents) ? payload.antecedents : [],
        allergies: Array.isArray(payload.allergies) ? payload.allergies : []
    };
};

const mergePatientForCard = (payload = {}) => {
    const incoming = normalizePatientForCard(payload);
    const current = selectedPatient.value || {};

    return {
        ...current,
        ...incoming,
        photo: incoming.photo || current.photo || null,
        numeroDossier: incoming.numeroDossier || current.numeroDossier || '--'
    };
};

const handlePatientLoaded = (payload) => {
    selectedPatient.value = mergePatientForCard(payload);
};

const loadSettings = async () => {
    try {
        const settings = await fetchPublicGeneralSettings(token);
        allowReceptionQuickClose.value = settings?.allowReceptionConsultationQuickActions !== false
            && settings?.allowReceptionQuickCloseConsultation !== false;
        hidePatientDossierForMedecins.value = settings?.hidePatientDossierForMedecins === true;
        hidePatientPhoneForMedecins.value = settings?.hidePatientPhoneForMedecins === true;
        soinsList.value = normalizeSoinList(settings?.soinsList);
    } catch (_) {
        allowReceptionQuickClose.value = true;
        hidePatientDossierForMedecins.value = false;
        hidePatientPhoneForMedecins.value = false;
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

const resetReceptionFocusData = () => {
    receptionRecentPatients.value = [];
    receptionBillingByConsultation.value = {};
};

const setCreateConsultationLoading = (patientId, value) => {
    if (patientId === undefined || patientId === null) return;
    createConsultationLoading.value = {
        ...createConsultationLoading.value,
        [patientId]: value
    };
};

const loadConsultations = async ({ silent = false, realtime = false } = {}) => {
    if (selectedMode.value === 'rdv') {
        if (!silent) {
            loading.value = false;
        }
        if (realtime) {
            isRealtimeRefreshing.value = false;
        }
        return;
    }

    if (!silent) {
        loading.value = true;
    }
    if (realtime) {
        isRealtimeRefreshing.value = true;
    }
    try {
        if (selectedMode.value === 'reception') {
            const payload = await fetchFocusReceptionData(todayApiDate(), token);
            consultations.value = payload.consultations;
            receptionRecentPatients.value = payload.recentPatients;
            receptionBillingByConsultation.value = payload.billingByConsultation;
        } else {
            consultations.value = await fetchConsultationsByDate(todayApiDate(), token);
            resetReceptionFocusData();
        }
        syncSelection();
    } catch (_) {
        resetReceptionFocusData();
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de charger les consultations du jour.', life: 3000 });
    } finally {
        if (!silent) {
            loading.value = false;
        }
        if (realtime) {
            isRealtimeRefreshing.value = false;
        }
        if (!hasInitialLoadCompleted.value) {
            hasInitialLoadCompleted.value = true;
        }
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
        selectedPatient.value = mergePatientForCard(patient);
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

const loadPaymentMethods = async () => {
    paymentMethods.value = await paymentMethodsStore.load(token);
};

const loadAssurances = async () => {
    assurances.value = await assurancesStore.load(token);
};

const openPayDialog = async () => {
    if (!currentReceptionInvoiceRow.value) return;
    selectedFacture.value = currentReceptionInvoiceRow.value;
    await Promise.all([loadPaymentMethods(), loadAssurances()]);
    const defaultClassicMethod = getDefaultClassicMethod(paymentMethods.value);
    const existingInsurance = currentReceptionInvoiceRow.value.insurance || null;
    payForm.value = {
        montant: currentReceptionInvoiceRow.value.reste || 0,
        modeId: defaultClassicMethod?.id ?? null,
        date: todayApiDate(),
        time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false }),
        insuranceEnabled: false,
        assuranceId: existingInsurance?.assuranceId ?? null,
        insuranceRate: Number(existingInsurance?.insuranceRate || 0)
    };
    paymentDialogTab.value = existingInsurance?.hasInsurance ? 'client' : 'assurance';
    payDialogVisible.value = true;
};

const openValidateDialog = () => {
    if (!currentReceptionInvoiceRow.value) return;
    pendingFacture.value = currentReceptionInvoiceRow.value;
    validateDialogVisible.value = true;
};

const openModifyDialog = async () => {
    if (!currentConsultation.value?.id) return;
    factureConsultation.value = currentConsultation.value;
    factureDialogVisible.value = true;
    factureLoading.value = true;
    try {
        factureLines.value = await fetchConsultationInvoice(currentConsultation.value.id, token);
    } finally {
        factureLoading.value = false;
    }
};

const openPreviewDialog = async () => {
    if (!currentReceptionInvoiceRow.value?.id) return;
    previewDialogVisible.value = true;
    previewLoading.value = true;
    previewDialogTab.value = 'services';
    selectedFacture.value = currentReceptionInvoiceRow.value;
    try {
        previewData.value = await fetchFactureDetail(currentReceptionInvoiceRow.value.id, token);
    } catch (_) {
        previewDialogVisible.value = false;
        toast.add({ severity: 'error', summary: 'Facture', detail: 'Aperçu indisponible', life: 3500 });
    } finally {
        previewLoading.value = false;
    }
};

const submitPayment = async () => {
    if (!selectedFacture.value) return;
    const isNewInsurancePayment = payForm.value.insuranceEnabled && invoiceAllowsInsurance.value;
    const montant = Number(payForm.value.montant) || 0;
    const insuranceAmount = isNewInsurancePayment ? effectiveInsuranceAmount.value : 0;
    const max = Number(selectedFacture.value.reste) || 0;
    if (montant < 0 || (montant + insuranceAmount) <= 0 || (montant + insuranceAmount) > max) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Montant invalide', life: 3500 });
        return;
    }
    if (payForm.value.insuranceEnabled && !invoiceAllowsInsurance.value) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Assurance non disponible pour cette facture.', life: 3500 });
        return;
    }
    if (isNewInsurancePayment && !payForm.value.assuranceId) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Sélectionnez une assurance.', life: 3500 });
        return;
    }
    if ((requiresClassicPayment.value && !payForm.value.modeId) || !payForm.value.date || !payForm.value.time) {
        toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Informations de paiement incomplètes.', life: 3500 });
        return;
    }
    payLoading.value = true;
    try {
        const canPrintClientReceipt = montant > 0;
        const res = await payFacture(selectedFacture.value.id, {
            montant,
            modeId: payForm.value.modeId,
            date: payForm.value.date,
            time: payForm.value.time,
            insurance_enabled: isNewInsurancePayment,
            assurance_id: payForm.value.assuranceId,
            insurance_rate: payForm.value.insuranceRate,
            insurance_amount: insuranceAmount,
            patient_amount: montant
        }, token);
        payDialogVisible.value = false;
        const paymentId = res?.paiement_id ?? res?.paiementId ?? null;
        toast.add({
            severity: 'success',
            summary: 'Paiement',
            detail: 'Paiement enregistré.',
            life: canPrintClientReceipt ? 10000 : 3000,
            data: canPrintClientReceipt && paymentId
                ? {
                    actionLabel: 'Imprimer le reçu',
                    action: () => printReceiptById(paymentId)
                }
                : undefined
        });
        await loadConsultations();
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Paiement', detail: 'Enregistrement impossible', life: 3500 });
    } finally {
        payLoading.value = false;
    }
};

const resetSelectedDevisPayments = async () => {
    if (!selectedFacture.value) return;
    resetPaymentsLoading.value = true;
    try {
        await resetFacturePayments(selectedFacture.value.id, token);
        resetPaymentDialogVisible.value = false;
        payDialogVisible.value = false;
        previewDialogVisible.value = false;
        toast.add({ severity: 'success', summary: 'Facture', detail: 'Facture réinitialisée.', life: 3000 });
        await loadConsultations();
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Facture', detail: 'Réinitialisation impossible.', life: 3500 });
    } finally {
        resetPaymentsLoading.value = false;
    }
};

const confirmValidate = async () => {
    if (!pendingFacture.value) return;
    validateLoading.value = true;
    try {
        await validateEmptyFacture(pendingFacture.value.id, token);
        validateDialogVisible.value = false;
        toast.add({ severity: 'success', summary: 'Validation', detail: 'Facture vide validée.', life: 3000 });
        await loadConsultations();
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Validation', detail: 'Échec de la validation', life: 3500 });
    } finally {
        validateLoading.value = false;
    }
};

const printInvoice = async () => {
    const invoiceId = previewData.value?.id || currentReceptionInvoiceRow.value?.id;
    if (!invoiceId) return;
    try {
        const res = await fetchInvoicePrintData(invoiceId, token);
        await printComponent(PrintDevisBody, { doc: res.doc, title: res.title || 'Facture' });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Facture', detail: 'Impression indisponible', life: 3500 });
    }
};

const printReceiptById = async (paymentId) => {
    if (!paymentId) return;
    try {
        const res = await fetchReceiptPrintData(paymentId, token);
        await printComponent(
            PrintReceiptBody,
            { paiement: res.paiement },
            { format: [226.77, 255.12], width: '80mm' }
        );
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Reçu', detail: 'Impression indisponible', life: 3500 });
    }
};

const sendInvoiceBySms = async () => {
    if (!currentReceptionInvoiceRow.value?.id) return;
    try {
        const res = await sendInvoiceSms(currentReceptionInvoiceRow.value.id, {}, token);
        toast.add({
            severity: res?.success ? 'success' : 'warn',
            summary: 'SMS Facture',
            detail: res?.success ? 'Facture ajoutée à la file SMS.' : (res?.error || 'Échec de l\'envoi.'),
            life: 3500
        });
    } catch (_) {
        toast.add({ severity: 'error', summary: 'SMS Facture', detail: 'Envoi impossible.', life: 3500 });
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

const handlePatientSaved = async (patient) => {
    createPatientDialogVisible.value = false;
    editPatientDialogVisible.value = false;
    if (patient?.id) {
        selectedPatient.value = mergePatientForCard(patient);
    }
    await loadConsultations();
};

const openCreateConsultationDialog = () => {
    consultationToolbarLoading.value = true;
    try {
        createConsultationPreSelectedPatient.value = null;
        createConsultationDialogVisible.value = true;
    } finally {
        consultationToolbarLoading.value = false;
    }
};

const openCreateConsultationDialogForPatient = async (patient) => {
    const patientId = patient?.id ?? null;
    if (patientId) {
        setCreateConsultationLoading(patientId, true);
    } else {
        consultationToolbarLoading.value = true;
    }

    if (!patient?.id) {
        try {
            createConsultationPreSelectedPatient.value = patient || null;
            createConsultationDialogVisible.value = true;
            return;
        } finally {
            consultationToolbarLoading.value = false;
        }
    }

    try {
        const res = await checkConsultationActive(patient.id, token);
        activeConsultInfo.value = {
            hasActive: Boolean(res?.hasActive),
            consultationId: res?.consultationId ?? null,
            hasFiche: Boolean(res?.hasFiche)
        };

        if (Boolean(res?.hasActive)) {
            activeConsultWarnPatient.value = patient;
            showActiveConsultWarn.value = true;

            if (res?.consultationId) {
                selectedConsultationId.value = res.consultationId;
            }
            return;
        }

        activeConsultWarnPatient.value = null;
        showActiveConsultWarn.value = false;
        createConsultationPreSelectedPatient.value = patient;
        createConsultationDialogVisible.value = true;
    } catch (_) {
        toast.add({ severity: 'warn', summary: 'Vérification', detail: 'Impossible de vérifier les consultations en cours.', life: 2500 });
        return;
    } finally {
        if (patientId) {
            setCreateConsultationLoading(patientId, false);
        } else {
            consultationToolbarLoading.value = false;
        }
    }
};

const closeActiveConsultWarn = () => {
    const patientId = activeConsultWarnPatient.value?.id;
    showActiveConsultWarn.value = false;
    activeConsultWarnPatient.value = null;
    activeConsultInfo.value = { hasActive: false, consultationId: null, hasFiche: false };
    consultationToolbarLoading.value = false;
    if (patientId) {
        setCreateConsultationLoading(patientId, false);
    }
};

const cancelActiveConsultation = async () => {
    if (!activeConsultInfo.value.consultationId) return;
    const patient = activeConsultWarnPatient.value;
    if (patient?.id) {
        setCreateConsultationLoading(patient.id, true);
    }

    try {
        await deleteConsultation(activeConsultInfo.value.consultationId, token);
        toast.add({ severity: 'success', summary: 'Consultation annulée', detail: 'La consultation en cours a été supprimée.', life: 3000 });
        closeActiveConsultWarn();
        await loadConsultations({ silent: true, realtime: true });
        if (patient) {
            await openCreateConsultationDialogForPatient(patient);
        }
    } catch (_) {
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Impossible de supprimer la consultation en cours.', life: 3000 });
    } finally {
        if (patient?.id) {
            setCreateConsultationLoading(patient.id, false);
        }
    }
};

const handleConsultationCreated = async () => {
    createConsultationDialogVisible.value = false;
    createConsultationPreSelectedPatient.value = null;
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

const askCancel = (eventOrTarget, consultation) => {
    const fallbackTarget = typeof document !== 'undefined' && consultation?.id
        ? document.querySelector(`[data-cancel-consultation-id="${consultation.id}"]`)
        : null;
    const activeElement = typeof document !== 'undefined' ? document.activeElement : null;
    const target = eventOrTarget?.currentTarget
        || eventOrTarget?.target?.closest?.('button')
        || eventOrTarget?.target
        || eventOrTarget
        || fallbackTarget
        || (activeElement instanceof HTMLElement ? activeElement : null);

    confirm.require({
        group: 'focus-cancel-consultation',
        target,
        message: 'Annuler cette consultation ? Cette action est irréversible.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, annuler',
        rejectLabel: 'Non',
        acceptClass: 'p-button-danger',
        accept: () => handleCancel(consultation)
    });
};

const onFocusRealtimeEvent = async () => {
    if (selectedMode.value === 'rdv') {
        return;
    }

    await loadConsultations({ silent: true, realtime: true });
    await loadSelectedPatient();
};

const { realtimeEnabled } = useFocusRealtime(onFocusRealtimeEvent);

watch(
    () => payForm.value.insuranceEnabled,
    (enabled) => {
        if (!enabled) {
            if (!invoiceHasInsurance.value) {
                payForm.value.assuranceId = null;
                payForm.value.insuranceRate = 0;
            }
            payForm.value.montant = Number(selectedFacture.value?.reste) || 0;
            return;
        }

        if (!invoiceAllowsInsurance.value) {
            payForm.value.insuranceEnabled = false;
            return;
        }

        const defaultAssurance = (assurances.value || []).find((item) => item?.actif !== false) || null;
        payForm.value.assuranceId = payForm.value.assuranceId || defaultAssurance?.id || null;
        if (!invoiceHasInsurance.value && payForm.value.assuranceId) {
            const assurance = (assurances.value || []).find((item) => Number(item?.id) === Number(payForm.value.assuranceId)) || null;
            payForm.value.insuranceRate = resolveAssuranceDefaultRate(assurance);
        }
        payForm.value.montant = 0;
    }
);

watch(
    () => payForm.value.assuranceId,
    (assuranceId) => {
        if (!assuranceId) {
            return;
        }

        if (!invoiceHasInsurance.value) {
            const assurance = (assurances.value || []).find((item) => Number(item?.id) === Number(assuranceId)) || null;
            payForm.value.insuranceRate = resolveAssuranceDefaultRate(assurance);
        }

        if ((Number(payForm.value.montant) || 0) > maxClientPaymentAmount.value) {
            payForm.value.montant = maxClientPaymentAmount.value;
        }
    }
);

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

watch(
    () => selectedMode.value,
    () => {
        if (!initialized.value) return;
        loadConsultations();
    }
);

const initializeFocusPage = async () => {
    if (initialized.value) {
        return;
    }

    initialized.value = true;
    await loadSettings();
    await Promise.all([loadPaymentMethods(), loadAssurances()]);
    await loadConsultations();
};

onMounted(() => {
    initializeFocusPage();
    if (useLayout().isSidebarActive) useLayout().toggleMenu();
});

onBeforeUnmount(() => {
     if (!useLayout().isSidebarActive) useLayout().toggleMenu();
});

</script>

<template>
     <section class="max-h-[80vh] bg-surface-50 dark:bg-surface-950 transition-colors duration-300">
        <AppToast />
        <ConfirmPopup group="focus-cancel-consultation" />

        <!-- Header ultra-mince (h-12 = 48px) avec bordure inférieure colorée selon le mode -->
        <header class="sticky top-0 z-30 h-12 border-b-2 bg-white/90 backdrop-blur-xl dark:bg-surface-900/90"
            :class="selectedModeBorderClass">
            <div class="mx-auto flex h-full max-w-[1920px] items-center justify-between px-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-primary-500 text-white">
                        <i class="pi pi-bolt text-xs"></i>
                    </div>
                    <h1 class="text-sm font-bold tracking-tight text-surface-900 dark:text-surface-50">
                        Mode Focus
                    </h1>
                    <span class="hidden sm:inline text-xs text-surface-500">
                        {{ focusStats.pending }} en attente · {{ focusStats.closed }} terminées
                    </span>
                    <span v-if="isRealtimeRefreshing" class="hidden lg:inline text-xs text-primary-600 dark:text-primary-300">
                        <i class="pi pi-spin pi-spinner mr-1"></i>Sync en cours
                    </span>
                </div>

                <div class="flex items-center gap-2">
                    <!-- Switcher de mode avec bordure colorée active -->
                    <div v-if="availableModes.length > 1" class="flex rounded-md bg-surface-100 p-0.5 dark:bg-surface-800">
                        <button
                            v-for="mode in availableModes"
                            :key="mode.value"
                            @click="selectedMode = mode.value"
                            :class="[
                                'rounded px-3 py-1 text-xs font-medium transition-all',
                                selectedMode === mode.value
                                    ? 'bg-white text-surface-900 shadow-sm ring-1 ring-primary-500/20 dark:bg-surface-700 dark:text-surface-50 dark:ring-primary-400/20'
                                    : 'text-surface-500 hover:text-surface-700 dark:text-surface-400 dark:hover:text-surface-200'
                            ]"
                        >
                            {{ mode.label }}
                        </button>
                    </div>

                    <div class="h-4 w-px bg-surface-200 dark:bg-surface-700"></div>

                    <!-- Temps réel avec point clignotant coloré -->
                    <button
                        @click="realtimeEnabled = !realtimeEnabled"
                        :class="[
                            'flex items-center gap-1.5 rounded px-2 py-1 text-xs font-medium transition-all',
                            realtimeEnabled
                                ? 'bg-green-50 text-green-700 ring-1 ring-green-300 dark:bg-green-900/20 dark:text-green-400 dark:ring-green-700'
                                : 'text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800'
                        ]"
                    >
                        <span class="relative flex h-1.5 w-1.5">
                            <span v-if="realtimeEnabled" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                            <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="realtimeEnabled ? 'bg-green-500' : 'bg-surface-300 dark:bg-surface-600'"></span>
                        </span>
                        Temps réel
                    </button>

                    <!-- Rafraîchir -->
                    <button
                        @click="loadConsultations"
                        :disabled="loading"
                        class="rounded p-1.5 text-sm text-surface-500 transition hover:bg-surface-100 disabled:opacity-50 dark:text-surface-400 dark:hover:bg-surface-800"
                    >
                        <i :class="loading ? 'pi pi-spin pi-spinner' : 'pi pi-refresh'"></i>
                    </button>
                </div>
            </div>
        </header>

        <!-- Contenu Principal -->
        <div class="mx-auto max-w-[1920px] p-6">
            <FocusReceptionView
                v-if="selectedMode === 'reception'"
                v-model:showCompletedSecretary="showCompletedSecretary"
                :consultations="consultations"
                :recent-patients="receptionRecentPatients"
                :billing-by-consultation="receptionBillingByConsultation"
                :loading="showFocusSkeleton"
                :consultation-toolbar-loading="consultationToolbarLoading"
                :consultation-loading-by-patient="createConsultationLoading"
                :allow-reception-quick-close="allowReceptionQuickClose"
                :is-admin="isAdmin"
                :selected-consultation-id="selectedConsultationId"
                @refresh="loadConsultations"
                @select-consultation="(consultationId) => { selectedConsultationId = consultationId; }"
                @open-create-patient="createPatientDialogVisible = true"
                @open-create-consultation="openCreateConsultationDialog"
                @open-create-consultation-for-patient="openCreateConsultationDialogForPatient"
                @open-edit-patient="(patient) => { patientToEdit = patient; editPatientDialogVisible = true; }"
                @open-caisse-pay="openPayDialog"
                @open-caisse-validate="openValidateDialog"
                @open-caisse-modify="openModifyDialog"
                @open-caisse-preview="openPreviewDialog"
                @send-invoice-sms="sendInvoiceBySms"
                @open-details="openDetails"
                @open-quick-dialog="openQuickDialog"
                @select-medical-workspace="openMedicalWorkspace"
                @cancel-consultation="askCancel"
            />

            <FocusMedecinView
                v-else-if="selectedMode === 'medecin'"
                v-model:showCompletedMedecin="showCompletedMedecin"
                :consultations="consultations.map((consultation) => ({
                    ...consultation,
                    focusActionChoice: actionChoiceByConsultation[consultation.id] || null
                }))"
                :selected-consultation-id="selectedConsultationId"
                :selected-patient="selectedPatient"
                :hide-patient-dossier="shouldHidePatientDossierForMedecin"
                :hide-patient-phone="shouldHidePatientPhoneForMedecin"
                @clear-selection="clearSelection"
                @select-consultation="(consultationId) => { selectedConsultationId = consultationId; }"
                @select-action-choice="openMedicalWorkspace"
                @patient-loaded="handlePatientLoaded"
                @consultation-closed="loadConsultations"
            />

            <FocusRendezVousView v-else-if="selectedMode === 'rdv'" />

            <!-- Dialogs restent inchangés -->
            <ConsultationDetailsDialog v-model:visible="detailsDialogVisible" :details="detailData" :loading="detailsLoading" />
            <QuickClotureConsultationDialog
                v-model:visible="quickDialogVisible"
                :consultation="quickDialogConsultation"
                :action-mode="quickDialogActionMode"
                @saved="handleQuickDialogDone"
                @closed="handleQuickDialogDone"
            />
            <CaisseInvoiceDialogs
                :pay-dialog-visible="payDialogVisible"
                :selected-facture="selectedFacture"
                :payment-dialog-tab="paymentDialogTab"
                :pay-form="payForm"
                :classic-payment-options="classicPaymentOptions"
                :assurance-options="assuranceOptions"
                :selected-assurance="selectedAssurance"
                :insurance-covered-amount="insuranceCoveredAmount"
                :patient-already-paid-amount="patientAlreadyPaidAmount"
                :patient-outstanding-amount="patientOutstandingAmount"
                :invoice-has-insurance="invoiceHasInsurance"
                :insurance-helper-message="insuranceHelperMessage"
                :insurance-section-disabled-reason="insuranceSectionDisabledReason"
                :insurance-status-label="insuranceStatusLabel"
                :insurance-status-severity="insuranceStatusSeverity"
                :invoice-allows-insurance="invoiceAllowsInsurance"
                :requires-classic-payment="requiresClassicPayment"
                :max-client-payment-amount="maxClientPaymentAmount"
                :remaining-after-pay="remainingAfterPay"
                :can-reset-invoice-payments="canResetInvoicePayments"
                :pay-loading="payLoading"
                :reset-payment-dialog-visible="resetPaymentDialogVisible"
                :reset-payments-loading="resetPaymentsLoading"
                :validate-dialog-visible="validateDialogVisible"
                :validate-loading="validateLoading"
                :facture-dialog-visible="factureDialogVisible"
                :facture-lines="factureLines"
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
                @update:payDialogVisible="payDialogVisible = $event"
                @update:paymentDialogTab="paymentDialogTab = $event"
                @update:resetPaymentDialogVisible="resetPaymentDialogVisible = $event"
                @update:validateDialogVisible="validateDialogVisible = $event"
                @update:factureDialogVisible="factureDialogVisible = $event"
                @update:previewDialogVisible="previewDialogVisible = $event"
                @update:previewDialogTab="previewDialogTab = $event"
                @submit-payment="submitPayment"
                @confirm-reset="resetSelectedDevisPayments"
                @confirm-validate="confirmValidate"
                @save-facture="handleSaveFacture(factureLines)"
                @print-invoice="printInvoice"
            />
            <Dialog v-model:visible="createPatientDialogVisible" modal :style="{ width: '45rem' }">
                <template #header>
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-primary-100 p-2 dark:bg-primary-900/30">
                            <i class="pi pi-user-plus text-primary-600 dark:text-primary-300"></i>
                        </div>
                        <div>
                            <h4 class="m-0 text-surface-900 dark:text-surface-100">Nouveau patient</h4>
                            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">Ajouter un patient depuis le mode focus</p>
                        </div>
                    </div>
                </template>
                <FormPatient @saved="handlePatientSaved" @cancel="createPatientDialogVisible = false" />
            </Dialog>
            <Dialog v-model:visible="createConsultationDialogVisible" modal :style="{ width: '50rem' }">
                <template #header>
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-green-100 p-2 dark:bg-green-900/30">
                            <i class="pi pi-plus-circle text-green-600 dark:text-green-400"></i>
                        </div>
                        <div>
                            <h4 class="m-0 text-surface-900 dark:text-surface-100">Nouvelle consultation</h4>
                            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">Creer une consultation depuis le mode focus</p>
                        </div>
                    </div>
                </template>
                <FormCreateConsultation :patient="createConsultationPreSelectedPatient" @saved="handleConsultationCreated" @cancel="createConsultationDialogVisible = false; createConsultationPreSelectedPatient = null" />
            </Dialog>
            <Dialog v-model:visible="showActiveConsultWarn" modal :style="{ width: '35rem' }">
                <div class="p-6">
                    <div class="mb-4 flex items-center gap-3">
                        <div class="rounded-lg bg-amber-100 p-2 dark:bg-amber-900/30">
                            <i class="fas fa-exclamation-triangle text-amber-600 dark:text-amber-400"></i>
                        </div>
                        <h4 class="m-0 text-surface-900 dark:text-surface-100">Consultation en cours</h4>
                    </div>

                    <p class="mb-4 text-surface-700 dark:text-surface-300">
                        Une consultation est déjà ouverte pour ce patient. Clôturez-la ou continuez-la avant d'en créer une nouvelle.
                    </p>

                    <p v-if="!activeConsultInfo.hasFiche" class="mb-4 text-sm text-surface-600 dark:text-surface-400">
                        Si cette consultation a été ouverte par erreur, vous pouvez l'annuler directement depuis ce dialogue.
                    </p>

                    <div v-if="activeConsultInfo.hasFiche"
                        class="mb-4 flex items-center gap-2 rounded-lg bg-surface-50 p-3 dark:bg-surface-800/50">
                        <i class="pi pi-info-circle text-surface-500"></i>
                        <span class="text-sm text-surface-600 dark:text-surface-400">
                            Cette consultation est liée à une fiche : elle ne peut pas être supprimée.
                        </span>
                    </div>

                    <div class="flex justify-end gap-2">
                        <Button label="Compris" severity="secondary" @click="closeActiveConsultWarn" class="rounded-xl px-5" />
                        <Button v-if="!activeConsultInfo.hasFiche" label="Annuler la consultation" icon="pi pi-times"
                            severity="danger" @click="cancelActiveConsultation" class="rounded-xl px-5" />
                    </div>
                </div>
            </Dialog>
            <Dialog v-model:visible="editPatientDialogVisible" modal :style="{ width: '45rem' }">
                <template #header>
                    <div class="flex items-center gap-3">
                        <div class="rounded-lg bg-blue-100 p-2 dark:bg-blue-900/30">
                            <i class="pi pi-user-edit text-blue-600 dark:text-blue-300"></i>
                        </div>
                        <div>
                            <h4 class="m-0 text-surface-900 dark:text-surface-100">Modifier le patient</h4>
                            <p class="mt-1 text-sm text-surface-500 dark:text-surface-400">Mettre à jour les informations du patient</p>
                        </div>
                    </div>
                </template>
                <FormPatient :patient="patientToEdit" @saved="handlePatientSaved" @cancel="editPatientDialogVisible = false; patientToEdit = null" />
            </Dialog>
        </div>
    </section>


</template>
