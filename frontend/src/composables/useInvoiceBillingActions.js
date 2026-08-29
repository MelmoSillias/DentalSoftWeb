/**
 * Shared invoice billing actions (pay / preview / print / validate)
 * for Dossier patient and other non-Caisse surfaces that reuse CaisseInvoiceDialogs.
 */

import { computed, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useAuthStore } from '@/stores/auth';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import { usePrinter } from '@/composables/usePrinter';
import {
    advanceAfterSettledTab,
    applyPartialPaymentToTab,
    buildPayTabs,
    isInsuranceFactureRow,
    resolveFacturePatientId,
    sumPriorReliquatFromTabs
} from '@/composables/usePayTabsDialog';
import {
    fetchFactureDetail,
    fetchInsuranceClaimDetail,
    fetchUnpaidFacturesByPatient,
    payFacture,
    payInsurancePatientShare,
    resetFacturePayments,
    validateEmptyFacture
} from '@/services/caisseService';
import { fetchFactureAssurancePrintData, fetchInvoicePrintData, fetchReceiptPrintData } from '@/services/printService';
import { getDefaultClassicMethod } from '@/utils/paymentMethodUtils';
import { formatFactureFcfa, targetIsFreeFacture } from '@/utils/factureRow';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintFactureAssuranceBody from '@/components/print/PrintFactureAssuranceBody.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';

const todayApiDate = () => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
};

const mapClaimToPreviewData = (detail, claimId) => ({
    ...detail,
    insurance: {
        hasInsurance: true,
        assuranceNom: detail?.assurance?.nom,
        assuranceCode: detail?.assurance?.code,
        tauxCouverture: detail?.tauxCouverture,
        insuranceRate: detail?.tauxCouverture,
        montantTotal: detail?.montantTotal,
        montantAssurance: detail?.montantAssurance,
        insuranceAmount: detail?.montantAssurance,
        montantPatient: detail?.montantPatient,
        restePatient: detail?.restePatient,
        patientPaidAmount: detail?.patientPaidAmount,
        factureAssuranceId: claimId
    },
    type: 'FactureAssurance',
    montant: detail?.montantPatient ?? 0,
    reste: detail?.restePatient ?? 0,
    contenus: detail?.lignes || []
});

const isInsurancePayment = (payment) => {
    const role = String(payment?.rolePaiement || payment?.role || '').toLowerCase();
    return role === 'patient_insurance';
};

/**
 * @param {{ onSettled?: () => void | Promise<void>, getToken?: () => string | null }} options
 */
export function useInvoiceBillingActions(options = {}) {
    const toast = useToast();
    const auth = useAuthStore();
    const paymentMethodsStore = usePaymentMethodsStore();
    const { printComponent } = usePrinter();

    const getToken = () => options.getToken?.() ?? localStorage.getItem('token');
    const notifySettled = async () => {
        if (typeof options.onSettled === 'function') {
            await options.onSettled();
        }
    };

    const paymentMethods = ref([]);
    const payDialogVisible = ref(false);
    const selectedFacture = ref(null);
    const payTabs = ref([]);
    const activePayTabId = ref(null);
    const payForm = ref({
        montant: 0,
        modeId: null,
        date: todayApiDate(),
        time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false })
    });
    const payLoading = ref(false);
    const validateDialogVisible = ref(false);
    const validateLoading = ref(false);
    const pendingFacture = ref(null);
    const resetPaymentDialogVisible = ref(false);
    const resetPaymentsLoading = ref(false);
    const previewDialogVisible = ref(false);
    const previewLoading = ref(false);
    const previewData = ref(null);
    const previewDialogTab = ref('services');

    // Unused by dossier but required by CaisseInvoiceDialogs props
    const factureDialogVisible = ref(false);
    const factureLines = ref([]);
    const factureDate = ref('');
    const factureTime = ref('');
    const factureSaving = ref(false);
    const soinsList = ref([]);

    const formatFcfa = formatFactureFcfa;

    const isAdmin = computed(() => Array.isArray(auth.user?.roles) && auth.user.roles.includes('ROLE_ADMIN'));

    const activeInvoiceContext = computed(() => {
        if (selectedFacture.value?.id) return selectedFacture.value;
        if (previewData.value?.id) return previewData.value;
        return null;
    });

    const selectedFactureInsurance = computed(() => activeInvoiceContext.value?.insurance || null);
    const invoiceHasInsurance = computed(() => selectedFactureInsurance.value?.hasInsurance === true);
    const invoiceInsuranceRate = computed(() => Number(selectedFactureInsurance.value?.insuranceRate) || 0);
    const insuranceCoveredAmount = computed(() => {
        if (!invoiceHasInsurance.value) return 0;
        return Number(selectedFactureInsurance.value?.insuranceAmount) || 0;
    });
    const patientAlreadyPaidAmount = computed(() => Number(selectedFactureInsurance.value?.patientPaidAmount) || 0);
    const insuranceStatusLabel = computed(() => {
        if (!invoiceHasInsurance.value) return 'Aucune assurance';
        return selectedFactureInsurance.value?.assuranceNom
            || selectedFactureInsurance.value?.insuranceModeLabel
            || (selectedFactureInsurance.value?.insuranceStatus === 'pending' ? 'Assurance en attente' : 'Assurance enregistrée');
    });
    const previewPayments = computed(() => (Array.isArray(previewData.value?.paiements) ? previewData.value.paiements : []));
    const previewServicesTotal = computed(() =>
        (previewData.value?.contenus || []).reduce((sum, line) => sum + (Number(line?.total) || 0), 0)
    );
    const patientOutstandingAmount = computed(() => {
        if (!selectedFacture.value) return 0;
        return Math.max(0, Number(selectedFacture.value.reste) || 0);
    });
    const activePayTab = computed(() =>
        (payTabs.value || []).find((tab) => String(tab.id) === String(activePayTabId.value)) || null
    );
    const activePayTabMode = computed(() => activePayTab.value?.mode || 'pay');
    const priorReliquatTotal = computed(() => sumPriorReliquatFromTabs(payTabs.value, activePayTabId.value));
    const hasPayReliquatTabs = computed(() => (payTabs.value || []).length > 1);
    const maxClientPaymentAmount = computed(() => {
        if (!selectedFacture.value) return 0;
        return Math.max(0, Number(selectedFacture.value.reste) || 0);
    });
    const canResetInvoicePayments = computed(() => {
        if (!isAdmin.value || !activeInvoiceContext.value) return false;
        return patientAlreadyPaidAmount.value > 0
            || invoiceHasInsurance.value
            || (Number(activeInvoiceContext.value.reste) || 0) !== (Number(activeInvoiceContext.value.montant) || 0);
    });
    const remainingAfterPay = computed(() => {
        if (!selectedFacture.value) return 0;
        const reste = Number(selectedFacture.value.reste) || 0;
        const montantPatient = Number(payForm.value.montant) || 0;
        return Math.max(0, reste - montantPatient);
    });
    const classicPaymentOptions = computed(() =>
        (paymentMethods.value || [])
            .filter((method) => method?.actif !== false)
            .map((method) => ({ label: method.libelle, value: method.id, disabled: false }))
    );
    const factureTotal = computed(() =>
        factureLines.value.reduce((sum, line) => sum + (Number(line.prix) || 0) * (Number(line.quantite) || 0), 0)
    );

    const previewPaymentRoleTag = (payment) => {
        if (isInsurancePayment(payment)) {
            return payment?.status === 'pending'
                ? { label: 'Assurance en attente', severity: 'warning' }
                : { label: 'Assurance', severity: 'info' };
        }
        return { label: 'Client', severity: 'success' };
    };

    const previewPaymentModeTag = (payment) => {
        if (isInsurancePayment(payment)) {
            return {
                label: payment?.mode || 'Assurance',
                severity: payment?.status === 'pending' ? 'warning' : 'info'
            };
        }
        return { label: payment?.mode || '—', severity: 'success' };
    };

    const loadPaymentMethods = async () => {
        const token = getToken();
        paymentMethods.value = await paymentMethodsStore.load(token);
    };

    const syncPayFormForFacture = (row) => {
        const defaultClassicMethod = getDefaultClassicMethod(paymentMethods.value);
        payForm.value = {
            montant: Number(row?.reste) || 0,
            modeId: defaultClassicMethod?.id ?? payForm.value.modeId ?? null,
            date: todayApiDate(),
            time: new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false })
        };
    };

    const selectPayTab = (tabId) => {
        activePayTabId.value = tabId == null ? null : String(tabId);
        const tab = (payTabs.value || []).find((item) => String(item.id) === String(activePayTabId.value));
        if (!tab) return;
        selectedFacture.value = tab.facture;
        if (tab.mode === 'validate') {
            pendingFacture.value = tab.facture;
        }
        if (tab.mode === 'pay') {
            syncPayFormForFacture(tab.facture);
        }
    };

    const closePayDialog = () => {
        payDialogVisible.value = false;
        payTabs.value = [];
        activePayTabId.value = null;
    };

    const onPayDialogVisibleUpdate = (visible) => {
        if (!visible) {
            closePayDialog();
            return;
        }
        payDialogVisible.value = true;
    };

    const handleAfterInvoiceSettled = async () => {
        const settledId = activePayTabId.value ?? String(selectedFacture.value?.id ?? '');
        const hadReliquatTabs = hasPayReliquatTabs.value;

        await notifySettled();

        if (!hadReliquatTabs) {
            closePayDialog();
            return;
        }

        const advanced = advanceAfterSettledTab(payTabs.value, settledId);
        payTabs.value = advanced.tabs;
        if (advanced.shouldClose || !advanced.nextTabId) {
            closePayDialog();
            return;
        }
        selectPayTab(advanced.nextTabId);
    };

    const openPayDialog = async (row, { primaryMode = null } = {}) => {
        if (!row) return;
        const normalized = {
            ...row,
            patientId: resolveFacturePatientId(row) || row.patientId || null
        };
        selectedFacture.value = normalized;
        await loadPaymentMethods();

        const patientId = resolveFacturePatientId(normalized);
        let unpaidRows = [];
        if (patientId) {
            try {
                unpaidRows = await fetchUnpaidFacturesByPatient(patientId, getToken());
            } catch (_) {
                unpaidRows = [];
            }
        }

        const mode = primaryMode
            || ((Number(normalized?.reste) || 0) === 0 && !normalized?.isRegle ? 'validate' : 'pay');
        payTabs.value = buildPayTabs(normalized, unpaidRows, { primaryMode: mode });
        activePayTabId.value = String(normalized.id);
        pendingFacture.value = mode === 'validate' ? normalized : null;
        syncPayFormForFacture(normalized);
        payDialogVisible.value = true;
    };

    const openValidateDialog = async (row) => {
        if (!row) return;

        if (Number(row?.priorReliquat || 0) > 0) {
            await openPayDialog(row, { primaryMode: 'validate' });
            return;
        }

        const patientId = resolveFacturePatientId(row);
        if (patientId) {
            try {
                const unpaidRows = await fetchUnpaidFacturesByPatient(patientId, getToken());
                const tabs = buildPayTabs(row, unpaidRows, { primaryMode: 'validate' });
                if (tabs.length > 1) {
                    await openPayDialog(row, { primaryMode: 'validate' });
                    return;
                }
            } catch (_) {
                // fall through
            }
        }

        pendingFacture.value = row;
        validateDialogVisible.value = true;
    };

    /** Pay or validate depending on row state (context menu entry). */
    const handlePayAction = async (row) => {
        if (!row) return;
        if (targetIsFreeFacture(row)) {
            await openValidateDialog(row);
            return;
        }
        await openPayDialog(row);
    };

    const openPreviewDialog = async (row) => {
        if (!row?.id && !row?.factureAssuranceId) return;
        previewDialogVisible.value = true;
        previewLoading.value = true;
        previewDialogTab.value = 'services';
        selectedFacture.value = row;
        try {
            if (isInsuranceFactureRow(row)) {
                const claimId = row.factureAssuranceId || row.insurance?.factureAssuranceId || row.id;
                const detail = await fetchInsuranceClaimDetail(claimId, getToken());
                previewData.value = mapClaimToPreviewData(detail, claimId);
            } else {
                previewData.value = await fetchFactureDetail(row.id, getToken());
            }
        } catch (_) {
            previewDialogVisible.value = false;
            toast.add({ severity: 'error', summary: 'Facture', detail: 'Aperçu indisponible', life: 3500 });
        } finally {
            previewLoading.value = false;
        }
    };

    const reloadFacturePreview = async (factureId) => {
        if (!previewDialogVisible.value || !factureId) return;
        previewLoading.value = true;
        try {
            if (isInsuranceFactureRow(selectedFacture.value) || isInsuranceFactureRow(previewData.value)) {
                const claimId = selectedFacture.value?.factureAssuranceId
                    || previewData.value?.insurance?.factureAssuranceId
                    || factureId;
                const detail = await fetchInsuranceClaimDetail(claimId, getToken());
                previewData.value = mapClaimToPreviewData(detail, claimId);
            } else {
                previewData.value = await fetchFactureDetail(factureId, getToken());
            }
        } catch (_) {
            toast.add({ severity: 'error', summary: 'Facture', detail: 'Actualisation du détail impossible', life: 3500 });
        } finally {
            previewLoading.value = false;
        }
    };

    const printReceiptById = async (paymentId) => {
        if (!paymentId) return;
        try {
            const res = await fetchReceiptPrintData(paymentId, getToken());
            await printComponent(
                PrintReceiptBody,
                { paiement: res.paiement },
                { format: [226.77, 255.12], width: '80mm' }
            );
        } catch (_) {
            toast.add({ severity: 'error', summary: 'Reçu', detail: 'Impression indisponible', life: 3500 });
        }
    };

    const printInvoice = async (row = null) => {
        const context = row || previewData.value || selectedFacture.value;
        if (!context?.id && !context?.factureAssuranceId) return;
        try {
            if (isInsuranceFactureRow(context) || isInsuranceFactureRow(previewData.value) || isInsuranceFactureRow(selectedFacture.value)) {
                const claimId = context.insurance?.factureAssuranceId
                    || context.factureAssuranceId
                    || selectedFacture.value?.factureAssuranceId
                    || previewData.value?.insurance?.factureAssuranceId
                    || context.id;
                const res = await fetchFactureAssurancePrintData(claimId, getToken());
                await printComponent(PrintFactureAssuranceBody, {
                    doc: res.doc,
                    title: res.title || 'Facture assurance'
                });
            } else {
                const invoiceId = previewData.value?.id || context.id;
                const res = await fetchInvoicePrintData(invoiceId, getToken());
                await printComponent(PrintDevisBody, { doc: res.doc, title: res.title || 'Facture' });
            }
        } catch (_) {
            toast.add({ severity: 'error', summary: 'Facture', detail: 'Impression indisponible', life: 3500 });
        }
    };

    const submitPayment = async () => {
        if (!selectedFacture.value) return;
        const montant = Number(payForm.value.montant) || 0;
        const max = Number(selectedFacture.value.reste) || 0;
        if (montant <= 0 || montant > max) {
            toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Montant invalide', life: 3500 });
            return;
        }
        if (!payForm.value.modeId || !payForm.value.date || !payForm.value.time) {
            toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Informations de paiement incomplètes.', life: 3500 });
            return;
        }

        payLoading.value = true;
        try {
            const canPrintClientReceipt = montant > 0;
            const isInsured = isInsuranceFactureRow(selectedFacture.value);
            const settledFully = montant >= max;
            let res;

            if (isInsured) {
                const claimId = selectedFacture.value.factureAssuranceId
                    || selectedFacture.value.insurance?.factureAssuranceId
                    || selectedFacture.value.id;
                res = await payInsurancePatientShare(claimId, {
                    modeId: payForm.value.modeId,
                    date: `${payForm.value.date}T${payForm.value.time}`,
                    amount: montant
                }, getToken());
            } else {
                res = await payFacture(selectedFacture.value.id, {
                    montant,
                    modeId: payForm.value.modeId,
                    date: payForm.value.date,
                    time: payForm.value.time
                }, getToken());
            }

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

            if (settledFully) {
                await handleAfterInvoiceSettled();
            } else {
                payTabs.value = applyPartialPaymentToTab(payTabs.value, activePayTabId.value, montant);
                selectPayTab(activePayTabId.value);
                await notifySettled();
            }
        } catch (_) {
            toast.add({ severity: 'error', summary: 'Paiement', detail: 'Enregistrement impossible', life: 3500 });
        } finally {
            payLoading.value = false;
        }
    };

    const confirmValidate = async () => {
        const target = pendingFacture.value || selectedFacture.value;
        if (!target) return;
        validateLoading.value = true;
        const fromPayDialog = payDialogVisible.value;
        try {
            const isInsured = isInsuranceFactureRow(target);
            if (isInsured) {
                await loadPaymentMethods();
                const claimId = target.factureAssuranceId || target.insurance?.factureAssuranceId || target.id;
                const classicMethod = getDefaultClassicMethod(paymentMethods.value);
                await payInsurancePatientShare(claimId, {
                    modeId: classicMethod?.id,
                    date: new Date().toISOString(),
                    amount: 0
                }, getToken());
            } else {
                await validateEmptyFacture(target.id, getToken());
            }
            validateDialogVisible.value = false;
            toast.add({ severity: 'success', summary: 'Validation', detail: 'Facture vide validée.', life: 3000 });

            if (fromPayDialog) {
                await handleAfterInvoiceSettled();
            } else {
                await notifySettled();
            }
        } catch (_) {
            toast.add({ severity: 'error', summary: 'Validation', detail: 'Échec de la validation', life: 3500 });
        } finally {
            validateLoading.value = false;
        }
    };

    const resetSelectedDevisPayments = async () => {
        const factureId = selectedFacture.value?.id ?? previewData.value?.id;
        if (!factureId) return;

        resetPaymentsLoading.value = true;
        try {
            if (isInsuranceFactureRow(selectedFacture.value) || isInsuranceFactureRow(previewData.value)) {
                // insurance reset uses classic reset on linked facture when applicable
            }
            await resetFacturePayments(factureId, getToken());
            resetPaymentDialogVisible.value = false;
            closePayDialog();
            toast.add({ severity: 'success', summary: 'Facture', detail: 'Facture réinitialisée.', life: 3000 });
            await notifySettled();
            await reloadFacturePreview(factureId);
        } catch (_) {
            toast.add({ severity: 'error', summary: 'Facture', detail: 'Réinitialisation impossible.', life: 3500 });
        } finally {
            resetPaymentsLoading.value = false;
        }
    };

    const dialogBindings = computed(() => ({
        payDialogVisible: payDialogVisible.value,
        selectedFacture: selectedFacture.value,
        payForm: payForm.value,
        classicPaymentOptions: classicPaymentOptions.value,
        insuranceCoveredAmount: insuranceCoveredAmount.value,
        insuranceRate: invoiceInsuranceRate.value,
        patientAlreadyPaidAmount: patientAlreadyPaidAmount.value,
        patientOutstandingAmount: patientOutstandingAmount.value,
        invoiceHasInsurance: invoiceHasInsurance.value,
        insuranceStatusLabel: insuranceStatusLabel.value,
        maxClientPaymentAmount: maxClientPaymentAmount.value,
        remainingAfterPay: remainingAfterPay.value,
        canResetInvoicePayments: canResetInvoicePayments.value,
        payLoading: payLoading.value,
        payTabs: payTabs.value,
        activePayTabId: activePayTabId.value,
        priorReliquatTotal: priorReliquatTotal.value,
        activePayTabMode: activePayTabMode.value,
        resetPaymentDialogVisible: resetPaymentDialogVisible.value,
        resetPaymentsLoading: resetPaymentsLoading.value,
        validateDialogVisible: validateDialogVisible.value,
        validateLoading: validateLoading.value,
        factureDialogVisible: factureDialogVisible.value,
        factureLines: factureLines.value,
        factureDate: factureDate.value,
        factureTime: factureTime.value,
        factureSaving: factureSaving.value,
        factureTotal: factureTotal.value,
        soinsList: soinsList.value,
        previewDialogVisible: previewDialogVisible.value,
        previewLoading: previewLoading.value,
        previewData: previewData.value,
        previewDialogTab: previewDialogTab.value,
        previewPayments: previewPayments.value,
        previewServicesTotal: previewServicesTotal.value,
        formatFcfa,
        previewPaymentModeTag,
        previewPaymentRoleTag
    }));

    return {
        // state refs (for template v-bind)
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
        dialogBindings,

        // actions
        openPayDialog,
        openValidateDialog,
        handlePayAction,
        openPreviewDialog,
        printInvoice,
        submitPayment,
        confirmValidate,
        resetSelectedDevisPayments,
        selectPayTab,
        onPayDialogVisibleUpdate,
        closePayDialog
    };
}
