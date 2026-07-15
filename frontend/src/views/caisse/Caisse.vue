<script setup>
import CaisseInvoiceDialogs from '@/components/caisse/CaisseInvoiceDialogs.vue';
import CaisseFactures from '@/components/caisse/CaisseFactures.vue';
import CaisseOverview from '@/components/caisse/CaisseOverview.vue';
import CaissePaiements from '@/components/caisse/CaissePaiements.vue';
import CaisseAssurances from '@/components/caisse/CaisseAssurances.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintPaymentsListBody from '@/components/print/PrintPaymentsListBody.vue';
import PrintFactureAssuranceBody from '@/components/print/PrintFactureAssuranceBody.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';
import PrintTicketBody from '@/components/print/PrintTicketBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import {
	activateCaisseTourMock,
	deactivateCaisseTourMock,
	resetCaisseTourMockData
} from '@/services/caisseTourMock';
import { GUIDED_TOUR_START_EVENT } from '@/tours';
import { createCaisseTour, resolveCaisseTourGroup } from '@/tours/caisseTour';
import { startTourGuide } from '@/tours/tourGuideClient';
import { useAuthStore } from '@/stores/auth';
import { useAssurancesStore } from '@/stores/assurances';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import {
	getDefaultClassicMethod,
	getPaymentMethodDefinition
} from '@/utils/paymentMethodUtils';
import {
	fetchFactureDetail,
	fetchFactures,
	fetchFactureLines,
	fetchAssurancesDashboard,
	fetchAssuranceLots,
	openAssuranceLot,
	updateAssuranceLot,
	fetchAssuranceLotDetail,
	sendAssuranceLot,
	reopenAssuranceLot,
	confirmAssuranceLot,
	unconfirmAssuranceLot,
	refundAssuranceLot,
	cancelAssuranceLotRefund,
	addClaimToAssuranceLot,
	moveClaimToAssuranceLot,
	removeClaimFromAssuranceLot,
	fetchInsuranceClaimDetail,
	fetchPayments,
	payFacture,
	payInsurancePatientShare,
	resetFacturePayments,
	updateFactureLines,
	validateEmptyFacture
} from '@/services/caisseService';
import { fetchPublicGeneralSettings } from '@/services/globalSettingsService';
import { canUserModifyInvoice } from '@/utils/invoiceModificationAccess';
import { defaultSoinList, normalizeSoinList } from '@/services/consultations';
import {
	fetchInvoicePrintData,
	fetchFactureAssurancePrintData,
	fetchPaymentsListPrintData,
	fetchReceiptPrintData,
	fetchTicketPrintData
} from '@/services/printService';
import { sendInvoiceSms, sendReceiptSms } from '@/services/smsService';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const toApiDate = (value) => {
	if (!value) return '';
	const date = value instanceof Date ? value : new Date(value);
	return date.toISOString().slice(0, 10);
};

const normalizeRange = (val) => {
	if (!Array.isArray(val)) return [];
	return val
		.map((item) => {
			if (!item) return null;
			const date = item instanceof Date ? new Date(item.getTime()) : new Date(item);
			return Number.isNaN(date.getTime()) ? null : date;
		})
		.filter(Boolean);
};

const rangeKey = (range) => {
	const normalized = normalizeRange(range);
	if (normalized.length < 2) return '';
	return `${toApiDate(normalized[0])}|${toApiDate(normalized[1])}`;
};

const rangesEqual = (left, right) => rangeKey(left) === rangeKey(right);

let syncingOverviewRange = false;

const applyOverviewRangeSync = (normalized, targetRef) => {
	if (activeView.value !== 'overview' || syncingOverviewRange) return;
	if (normalized.length < 2) return;
	if (rangesEqual(normalized, targetRef.value)) return;
	syncingOverviewRange = true;
	targetRef.value = normalized.map((date) => new Date(date.getTime()));
	syncingOverviewRange = false;
};


const toast = useToast();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();
const authStore = useAuthStore();
const assurancesStore = useAssurancesStore();
const paymentMethodsStore = usePaymentMethodsStore();

const viewStorageKey = 'caisse.view';
const activeView = ref(localStorage.getItem(viewStorageKey) || 'overview');

const today = new Date();
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

const factureType = ref('all');

const factureRange = ref([]);
const paymentRange = ref([]);

if(authStore.user.roles.includes('ROLE_RECEPTION')) {
	factureRange.value = [today, today];
	paymentRange.value = [today, today];
} else {
	factureRange.value = [startOfMonth, endOfMonth];
	paymentRange.value = [startOfMonth, endOfMonth];
}

const factures = ref([]);
const payments = ref([]);
const insuranceDashboard = ref([]);
const insuranceLotsAssurance = ref(null);
const insuranceLots = ref([]);
const insuranceOpenLots = ref([]);
const insuranceUnassignedClaims = ref([]);
const insuranceSelectedClaim = ref(null);
const insuranceSelectedLot = ref(null);
const facturesLoading = ref(false);
const paymentsLoading = ref(false);
const insuranceDashboardLoading = ref(false);
const insuranceLotsLoading = ref(false);
const insuranceClaimLoading = ref(false);
const insuranceLotLoading = ref(false);
const insuranceActionLoadingId = ref(null);
const validateLoading = ref(false);


const currentTime = () => {
	const date = new Date();
	return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false });
};

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const paymentMethods = ref([]);
const assurances = ref([]);
const payDialogVisible = ref(false);
const selectedFacture = ref(null);
const paymentDialogTab = ref('client');
const publicGeneralSettings = ref({ paiementDirectAssurance: false, allowReceptionInvoiceModification: false, soinsList: [...defaultSoinList] });
const isAdmin = computed(() => Array.isArray(authStore.user?.roles) && authStore.user.roles.includes('ROLE_ADMIN'));
const isMedecin = computed(() => Array.isArray(authStore.user?.roles) && authStore.user.roles.includes('ROLE_MEDECIN'));
const canModifyInvoiceByRole = computed(() =>
	canUserModifyInvoice(authStore.user, publicGeneralSettings.value)
);
const shouldHidePatientPhoneForMedecin = computed(() => isMedecin.value && !isAdmin.value && publicGeneralSettings.value?.hidePatientPhoneForMedecins === true);
const payForm = ref({
	montant: 0,
	modeId: null,
	date: toApiDate(today),
	time: currentTime(),
	insuranceEnabled: false,
	assuranceId: null,
	insuranceRate: 0
});

const validateDialogVisible = ref(false);
const pendingFacture = ref(null);
const resetPaymentDialogVisible = ref(false);
const resetPaymentsLoading = ref(false);

const factureDialogVisible = ref(false);
const factureConsultId = ref(null);
const factureLines = ref([]);
const factureDate = ref('');
const factureTime = ref('');
const factureSaving = ref(false);

const previewDialogVisible = ref(false);
const previewLoading = ref(false);
const previewData = ref(null);
const previewDialogTab = ref('services');
const payLoading = ref(false);
const isGuidedTourStarting = ref(false);
const loadErrorMessage = ref('');
const isInitialLoadPhase = ref(true);
let guidedTourPageState = null;
let guidedTourDemoActive = false;
let guidedTourCleanupPromise = null;

// Explicit setters avoid template auto-unwrapping issues on refs
const setFactureType = (val) => {
	factureType.value = val || 'all';
};

const setFactureRange = (val) => {
	const normalized = normalizeRange(val);
	factureRange.value = normalized;
	applyOverviewRangeSync(normalized, paymentRange);
};

const setPaymentRange = (val) => {
	const normalized = normalizeRange(val);
	paymentRange.value = normalized;
	applyOverviewRangeSync(normalized, factureRange);
};

const factureRangeKey = computed(() => rangeKey(factureRange.value));
const paymentRangeKey = computed(() => rangeKey(paymentRange.value));

const soinsList = computed(() => publicGeneralSettings.value?.soinsList || defaultSoinList);

const factureTotal = computed(() => factureLines.value.reduce((sum, line) => sum + (Number(line.prix) || 0) * (Number(line.quantite) || 0), 0));

const isAdminUser = computed(() => Array.isArray(authStore.user?.roles) && authStore.user.roles.includes('ROLE_ADMIN'));

const activeInvoiceContext = computed(() => {
	if (selectedFacture.value?.id) {
		return selectedFacture.value;
	}

	if (previewData.value?.id) {
		return previewData.value;
	}

	return null;
});

const selectedDevisInsurance = computed(() => activeInvoiceContext.value?.insurance || null);

const invoiceHasInsurance = computed(() => selectedDevisInsurance.value?.hasInsurance === true);

const effectiveInsuranceRate = computed(() => {
	if (invoiceHasInsurance.value) {
		return Number(selectedDevisInsurance.value?.insuranceRate) || 0;
	}

	if (payForm.value.insuranceEnabled) {
		return Number(payForm.value.insuranceRate) || 0;
	}

	return 0;
});

const effectiveInsuranceAmount = computed(() => {
	if (invoiceHasInsurance.value) {
		return Number(selectedDevisInsurance.value?.insuranceAmount) || 0;
	}

	if (!selectedFacture.value || !payForm.value.insuranceEnabled) {
		return 0;
	}

	const baseAmount = Number(selectedFacture.value.reste) || 0;
	return Math.max(0, (baseAmount * effectiveInsuranceRate.value) / 100);
});

const patientAlreadyPaidAmount = computed(() => Number(selectedDevisInsurance.value?.patientPaidAmount) || 0);

const hasExistingPayments = computed(() => patientAlreadyPaidAmount.value > 0 || invoiceHasInsurance.value);

const insuranceSectionDisabledReason = computed(() => {
	if (invoiceHasInsurance.value) {
		return 'Cette facture a déjà une assurance liée. La prise en charge n’est plus modifiable.';
	}

	if (patientAlreadyPaidAmount.value > 0) {
		return 'Des paiements sont déjà enregistrés sur cette facture. L’assurance ne peut plus être activée.';
	}

	if (!selectedFacture.value || (Number(selectedFacture.value.montant) || 0) <= 0 || (Number(selectedFacture.value.reste) || 0) <= 0) {
		return 'La prise en charge assurance n’est pas disponible pour cette facture.';
	}

	return null;
});

const insuranceStatusLabel = computed(() => {
	if (!invoiceHasInsurance.value) {
		return 'Aucune assurance rattachée';
	}

	return selectedDevisInsurance.value?.assuranceNom
		|| selectedDevisInsurance.value?.insuranceModeLabel
		|| 'Assurance enregistrée';
});

const insuranceStatusSeverity = computed(() => {
	if (!invoiceHasInsurance.value) {
		return 'secondary';
	}

	return 'info';
});

const previewPayments = computed(() => Array.isArray(previewData.value?.paiements) ? previewData.value.paiements : []);

const isInsurancePayment = (payment) => {
	const role = String(payment?.rolePaiement || payment?.role || '').toLowerCase();
	return role === 'patient_insurance';
};

const previewPaymentRoleTag = (payment) => {
	if (isInsurancePayment(payment)) {
		return { label: 'Assurance', severity: 'info' };
	}

	return { label: 'Client', severity: 'success' };
};

const previewPaymentModeTag = (payment) => {
	if (isInsurancePayment(payment)) {
		return {
			label: payment?.mode || 'Assurance',
			severity: 'info'
		};
	}

	return {
		label: payment?.mode || '—',
		severity: 'success'
	};
};

const previewServicesTotal = computed(() => (previewData.value?.contenus || []).reduce((sum, line) => sum + (Number(line?.total) || 0), 0));

const patientOutstandingAmount = computed(() => {
	if (!selectedFacture.value) {
		return 0;
	}

	if (invoiceHasInsurance.value) {
		return Number(selectedFacture.value.reste) || 0;
	}

	const total = Number(selectedFacture.value.montant) || 0;
	return Math.max(0, total - patientAlreadyPaidAmount.value - effectiveInsuranceAmount.value);
});

const insuranceHelperMessage = computed(() => publicGeneralSettings.value?.paiementDirectAssurance
	? 'Le paiement assurance sera créé automatiquement avec une transaction en attente.'
	: 'Une transaction assurance en attente sera créée. Le paiement assurance sera ajouté après validation.'
);

const maxClientPaymentAmount = computed(() => {
	if (!selectedFacture.value) {
		return 0;
	}

	const base = Number(selectedFacture.value.reste) || 0;
	const reservedInsurance = invoiceHasInsurance.value ? 0 : effectiveInsuranceAmount.value;
	return Math.max(0, base - reservedInsurance);
});

const canResetInvoicePayments = computed(() => {
	if (!isAdminUser.value || !activeInvoiceContext.value) {
		return false;
	}

	return hasExistingPayments.value || (Number(activeInvoiceContext.value.reste) || 0) !== (Number(activeInvoiceContext.value.montant) || 0);
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
		.filter((method) => method.actif !== false)
		.map((method) => ({
			label: `${method.libelle}${getPaymentMethodDefinition(method).label !== 'Autre' ? ` (${getPaymentMethodDefinition(method).label})` : ''}`,
			value: method.id
		}))
);

const assuranceOptions = computed(() =>
	(assurances.value || [])
		.filter((item) => item?.actif !== false)
		.map((item) => ({
			label: item?.nom || item?.libelle || 'Assurance',
			value: item?.id
		}))
);

const selectedAssurance = computed(() =>
	(assurances.value || []).find((item) => Number(item?.id) === Number(payForm.value.assuranceId)) || null
);

const insuranceCoveredAmount = computed(() => {
	if (invoiceHasInsurance.value) {
		return Number(selectedDevisInsurance.value?.insuranceAmount) || 0;
	}

	return effectiveInsuranceAmount.value;
});

const patientPortionAmount = computed(() => {
	if (!selectedFacture.value) {
		return 0;
	}

	if (invoiceHasInsurance.value) {
		return Number(selectedFacture.value.reste) || 0;
	}

	return patientOutstandingAmount.value;
});

const invoiceAllowsInsurance = computed(() => {
	if (!selectedFacture.value) {
		return false;
	}

	const total = Number(selectedFacture.value.montant) || 0;
	const reste = Number(selectedFacture.value.reste) || 0;
	return total > 0 && reste > 0 && !invoiceHasInsurance.value && patientAlreadyPaidAmount.value <= 0;
});

const requiresClassicPayment = computed(() => (Number(payForm.value.montant) || 0) > 0);

const setActiveView = (view) => {
	const allowed = ['overview', 'factures', 'paiements', 'assurances'];
	const normalized = allowed.includes(view) ? view : 'overview';
	activeView.value = normalized;
	localStorage.setItem(viewStorageKey, normalized);
};

const cloneValue = (value) => {
	if (value === undefined) return undefined;
	if (value === null) return null;
	return JSON.parse(JSON.stringify(value));
};

const waitForTourUi = (ms = 180) => new Promise((resolve) => {
	window.setTimeout(resolve, ms);
});

const hasOpenDialogs = computed(() => (
	payDialogVisible.value
	|| validateDialogVisible.value
	|| factureDialogVisible.value
	|| previewDialogVisible.value
));

const firstPayableFacture = computed(() => factures.value.find((row) => !row?.isRegle) || null);
const firstPreviewableFacture = computed(() => factures.value.find((row) => !(Number(row?.montant) === 0 && Number(row?.reste) === 0)) || null);
const firstModifiableFacture = computed(() => {
	if (!canModifyInvoiceByRole.value) return null;
	return factures.value.find((row) => !row?.hasPayments && (Number(row?.montant) === Number(row?.reste)) && !row?.isRegle) || null;
});

const loadFactures = async () => {
	const isAllUnpaid = factureType.value === 'impaye_toutes';
	if (!isAllUnpaid && (!factureRange.value || factureRange.value.length < 2)) return;
	try {
		facturesLoading.value = true;
		const fetchParams = isAllUnpaid
			? { factureType: 'impaye_toutes' }
			: {
				start: toApiDate(factureRange.value[0]),
				end: toApiDate(factureRange.value[1]),
				factureType: factureType.value,
			};
		const res = await fetchFactures(fetchParams, token);
		factures.value = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
		if (isInitialLoadPhase.value) {
			loadErrorMessage.value = '';
		}
	} catch (error) {
		console.error(error);
		if (isInitialLoadPhase.value) {
			loadErrorMessage.value = 'Impossible de charger les factures de caisse.';
		}
		toast.add({
			severity: 'error',
			summary: 'Factures',
			detail: error?.userMessage || 'Chargement des factures impossible',
			life: 3500
		});
	} finally {
		facturesLoading.value = false;
	}
};

const loadPayments = async () => {
	if (!paymentRange.value || paymentRange.value.length < 2) return;
	try {
		paymentsLoading.value = true;
		const [start, end] = paymentRange.value;
		const res = await fetchPayments({ start: toApiDate(start), end: toApiDate(end) }, token);
		payments.value = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
		if (isInitialLoadPhase.value) {
			loadErrorMessage.value = '';
		}
	} catch (error) {
		console.error(error);
		if (isInitialLoadPhase.value) {
			loadErrorMessage.value = 'Impossible de charger les paiements de caisse.';
		}
		toast.add({ severity: 'error', summary: 'Paiements', detail: 'Chargement des paiements impossible', life: 3500 });
	} finally {
		paymentsLoading.value = false;
	}
};

const loadPaymentMethods = async () => {
	try {
		paymentMethods.value = await paymentMethodsStore.load(token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'warn', summary: 'Modes de paiement', detail: 'Chargement impossible', life: 3000 });
	}
};

const loadAssurances = async () => {
	try {
		assurances.value = await assurancesStore.load(token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'warn', summary: 'Assurances', detail: 'Chargement impossible', life: 3000 });
	}
};

const buildInsuranceDashboardFallback = () => (assurances.value || [])
	.filter((item) => item?.actif !== false && item?.code)
	.map((item) => ({
		id: item.id,
		nom: item.nom,
		code: item.code,
		logoPath: item.logoPath ?? null,
		actif: item.actif !== false,
		counts: { sansLot: 0, ouverts: 0, envoyes: 0, confirmes: 0, rembourses: 0 },
	}));

const refreshInsuranceViews = async ({ includePayments = false } = {}) => {
	const tasks = [loadInsuranceDashboard()];
	if (insuranceLotsAssurance.value?.code) {
		tasks.push(loadInsuranceLots());
	}
	if (insuranceSelectedLot.value?.id) {
		tasks.push(loadInsuranceLotDetail(insuranceSelectedLot.value));
	}
	if (includePayments) {
		tasks.push(loadPayments());
	}
	await Promise.all(tasks);
};

const loadInsuranceDashboard = async () => {
	try {
		insuranceDashboardLoading.value = true;
		const cards = await fetchAssurancesDashboard(token);
		if (!cards.length) {
			if (!assurances.value.length) {
				await loadAssurances();
			}
			insuranceDashboard.value = buildInsuranceDashboardFallback();
		} else {
			insuranceDashboard.value = cards;
		}
	} catch (error) {
		console.error(error);
		if (!assurances.value.length) {
			await loadAssurances();
		}
		insuranceDashboard.value = buildInsuranceDashboardFallback();
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Chargement du tableau de bord impossible', life: 3500 });
	} finally {
		insuranceDashboardLoading.value = false;
	}
};

const loadInsuranceLots = async () => {
	const code = insuranceLotsAssurance.value?.code;
	if (!code) return;
	try {
		insuranceLotsLoading.value = true;
		const res = await fetchAssuranceLots(code, {}, token);
		insuranceLots.value = Array.isArray(res?.data) ? res.data : [];
		insuranceOpenLots.value = Array.isArray(res?.openLots) ? res.openLots : [];
		insuranceUnassignedClaims.value = Array.isArray(res?.unassignedClaims) ? res.unassignedClaims : [];
		if (res?.assurance) {
			insuranceLotsAssurance.value = { ...insuranceLotsAssurance.value, ...res.assurance };
		}
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Chargement des lots impossible', life: 3500 });
	} finally {
		insuranceLotsLoading.value = false;
	}
};

const loadInsuranceClaimDetail = async (claim) => {
	const claimId = Number(claim?.id);
	if (!claimId) return;
	try {
		insuranceClaimLoading.value = true;
		insuranceSelectedClaim.value = await fetchInsuranceClaimDetail(claimId, token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Chargement du détail impossible', life: 3500 });
	} finally {
		insuranceClaimLoading.value = false;
	}
};

const loadInsuranceLotDetail = async (lot) => {
	const lotId = Number(lot?.id);
	if (!lotId) return;
	try {
		insuranceLotLoading.value = true;
		insuranceSelectedLot.value = await fetchAssuranceLotDetail(lotId, token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Chargement du lot impossible', life: 3500 });
	} finally {
		insuranceLotLoading.value = false;
	}
};

const viewInsuranceLots = async (card) => {
	insuranceSelectedLot.value = null;
	insuranceSelectedClaim.value = null;
	insuranceLotsAssurance.value = card;
	await loadInsuranceLots();
};

const backToInsuranceDashboard = () => {
	insuranceLotsAssurance.value = null;
	insuranceLots.value = [];
	insuranceOpenLots.value = [];
	insuranceUnassignedClaims.value = [];
	insuranceSelectedLot.value = null;
	insuranceSelectedClaim.value = null;
};

const backToInsuranceLots = () => {
	insuranceSelectedLot.value = null;
	insuranceSelectedClaim.value = null;
};

const backFromInsuranceClaim = () => {
	insuranceSelectedClaim.value = null;
};

const createInsuranceLot = async (payload) => {
	const code = insuranceLotsAssurance.value?.code;
	if (!code) return;
	try {
		insuranceActionLoadingId.value = -1;
		await openAssuranceLot(code, payload || {}, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Lot créé.', life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		const detail = error?.response?.data?.error || 'Création du lot impossible.';
		toast.add({ severity: 'error', summary: 'Assurances', detail, life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const updateInsuranceLotMeta = async ({ lot, payload }) => {
	try {
		insuranceActionLoadingId.value = Number(lot?.id) || null;
		await updateAssuranceLot(lot.id, payload || {}, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Lot mis à jour.', life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Modification du lot impossible.', life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const viewInsuranceLot = async (lotSummary) => {
	await loadInsuranceLotDetail(lotSummary);
};

const runLotTransition = async (lot, action, successMessage) => {
	try {
		insuranceActionLoadingId.value = Number(lot?.id) || null;
		await action(lot.id, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: successMessage, life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		const detail = error?.response?.data?.error || 'Action impossible.';
		toast.add({ severity: 'error', summary: 'Assurances', detail, life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const sendInsuranceLot = (lot) => runLotTransition(lot, sendAssuranceLot, 'Lot envoyé.');
const reopenInsuranceLot = (lot) => runLotTransition(lot, reopenAssuranceLot, 'Lot rouvert.');
const confirmInsuranceLot = (lot) => runLotTransition(lot, confirmAssuranceLot, 'Lot confirmé.');
const unconfirmInsuranceLot = (lot) => runLotTransition(lot, unconfirmAssuranceLot, 'Lot repassé en envoyé.');

const refundInsuranceLot = async (payload) => {
	const lot = payload?.lot || payload;
	const modeId = payload?.modeId || getDefaultClassicMethod(paymentMethods.value)?.id;
	const amount = payload?.amount;
	if (!lot?.id || !modeId) {
		toast.add({ severity: 'warn', summary: 'Assurances', detail: 'Mode de paiement requis.', life: 3500 });
		return;
	}
	try {
		insuranceActionLoadingId.value = Number(lot.id);
		await refundAssuranceLot(lot.id, { modeId, amount, date: new Date().toISOString() }, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Remboursement enregistré.', life: 3000 });
		await refreshInsuranceViews({ includePayments: false });
	} catch (error) {
		console.error(error);
		const detail = error?.response?.data?.error || 'Remboursement impossible.';
		toast.add({ severity: 'error', summary: 'Assurances', detail, life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const cancelInsuranceLotRefund = async ({ lot, transaction }) => {
	try {
		insuranceActionLoadingId.value = Number(lot?.id) || null;
		await cancelAssuranceLotRefund(lot.id, transaction.id, {}, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Remboursement annulé.', life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Annulation impossible.', life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const assignClaimToLot = async ({ claim, lotId }) => {
	try {
		insuranceActionLoadingId.value = Number(claim?.id) || null;
		await addClaimToAssuranceLot(lotId, claim.id, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Facture affectée au lot.', life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		const detail = error?.response?.data?.error || 'Affectation impossible.';
		toast.add({ severity: 'error', summary: 'Assurances', detail, life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const changeClaimLot = async ({ claim, lotId }) => {
	try {
		insuranceActionLoadingId.value = Number(claim?.id) || null;
		await moveClaimToAssuranceLot(claim.id, lotId, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Lot changé.', life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		const detail = error?.response?.data?.error || 'Changement de lot impossible.';
		toast.add({ severity: 'error', summary: 'Assurances', detail, life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const removeClaimFromLot = async (claim) => {
	const lotId = Number(insuranceSelectedLot.value?.id);
	if (!lotId || !claim?.id) return;
	try {
		insuranceActionLoadingId.value = Number(claim.id);
		await removeClaimFromAssuranceLot(lotId, claim.id, token);
		toast.add({ severity: 'success', summary: 'Assurances', detail: 'Facture retirée du lot.', life: 3000 });
		await refreshInsuranceViews();
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Retrait impossible.', life: 3500 });
	} finally {
		insuranceActionLoadingId.value = null;
	}
};

const modifyInsuranceClaim = async (claim) => {
	const consultationId = Number(claim?.consultationId);
	const factureId = Number(claim?.factureId);
	if (!consultationId || !factureId) {
		toast.add({ severity: 'warn', summary: 'Assurances', detail: 'Facture classique introuvable pour modification.', life: 3500 });
		return;
	}
	await openModifyDialog({ id: factureId, consultation: consultationId, ...claim });
};

const viewInsuranceClaim = async (claim) => {
	await loadInsuranceClaimDetail(claim);
};

const printInsuranceClaim = async (claim) => {
	const claimId = Number(claim?.id);
	if (!claimId) return;
	try {
		const res = await fetchFactureAssurancePrintData(claimId, token);
		await printComponent(
			PrintFactureAssuranceBody,
			{ doc: res.doc, title: res.title || 'Facture assurance' },
			{ format: [226.77, 255.12], width: '80mm' }
		);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Assurances', detail: 'Impression indisponible', life: 3500 });
	}
};

const printInsuranceReceipt = async (paymentRow) => {
	const paymentId = Number(paymentRow?.paiementId);
	if (!paymentId) return;
	await printReceiptById(paymentId);
};

const collectPatientShare = async (claim) => {
	const amount = Number(claim?.restePatient);
	const claimId = claim?.factureId || claim?.id;

	await openPayDialog({
		id: claimId,
		factureAssuranceId: claimId,
		reste: amount > 0 ? amount : 0,
		montant: Number(claim.montantPatient) || 0,
		isRegle: false,
		type: 'FactureAssurance',
		insurance: {
			hasInsurance: true,
			assuranceId: claim?.assurance?.id,
			assuranceNom: claim?.assurance?.nom,
			assuranceCode: claim?.assurance?.code,
			insuranceRate: claim?.tauxCouverture,
			tauxCouverture: claim?.tauxCouverture,
			montantTotal: Number(claim?.montantTotal) || 0,
			montantAssurance: Number(claim?.montantAssurance) || 0,
			insuranceAmount: Number(claim?.montantAssurance) || 0,
			montantPatient: Number(claim?.montantPatient) || 0,
			patientRemainingAmount: amount > 0 ? amount : 0,
			restePatient: amount > 0 ? amount : 0,
			patientPaidAmount: Number(claim?.patientPaidAmount) || 0,
			factureAssuranceId: claimId,
			insuranceStatus: claim?.insuranceStatus,
		}
	});
};

const loadPublicGeneralSettings = async () => {
	try {
		const settings = await fetchPublicGeneralSettings(token);
		publicGeneralSettings.value = {
			paiementDirectAssurance: settings?.paiementDirectAssurance === true,
			allowReceptionInvoiceModification: settings?.allowReceptionInvoiceModification === true,
			hidePatientPhoneForMedecins: settings?.hidePatientPhoneForMedecins === true,
			soinsList: normalizeSoinList(settings?.soinsList)
		};
		if (isInitialLoadPhase.value) {
			loadErrorMessage.value = '';
		}
	} catch (error) {
		console.error(error);
		publicGeneralSettings.value = {
			paiementDirectAssurance: false,
			allowReceptionInvoiceModification: false,
			hidePatientPhoneForMedecins: false,
			soinsList: [...defaultSoinList]
		};
		if (isInitialLoadPhase.value) {
			loadErrorMessage.value = 'Impossible de charger la configuration de caisse.';
		}
	}
};

const retryLoadPage = async () => {
	loadErrorMessage.value = '';
	isInitialLoadPhase.value = true;
	await loadPublicGeneralSettings();
	await loadFactures();
	await loadPayments();
	isInitialLoadPhase.value = false;
};

const openPayDialog = async (row) => {
	selectedFacture.value = row;
	await Promise.all([loadPaymentMethods(), loadAssurances()]);
	const defaultClassicMethod = getDefaultClassicMethod(paymentMethods.value);
	const existingInsurance = row?.insurance || null;
	payForm.value = {
		montant: Number(row.reste) || 0,
		modeId: defaultClassicMethod?.id ?? null,
		date: toApiDate(new Date()),
		time: currentTime(),
		insuranceEnabled: false,
		assuranceId: existingInsurance?.assuranceId ?? null,
		insuranceRate: Number(existingInsurance?.insuranceRate) || 0
	};
	payDialogVisible.value = true;
};

const openTourPaymentDialog = async () => {
	if (!firstPayableFacture.value) return;
	await openPayDialog(firstPayableFacture.value);
};

const openTourPaymentDialogStable = async () => {
	resetTourDialogs();
	await nextTick();
	await waitForTourUi();
	await openTourPaymentDialog();
	await nextTick();
};

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
		payForm.value.montant = 0;
	}
);

watch(
	() => payForm.value.assuranceId,
	() => {
		if ((Number(payForm.value.montant) || 0) > maxClientPaymentAmount.value) {
			payForm.value.montant = maxClientPaymentAmount.value;
		}
	}
);

watch(
	() => payForm.value.insuranceRate,
	() => {
		if (payForm.value.insuranceEnabled && (Number(payForm.value.montant) || 0) > maxClientPaymentAmount.value) {
			payForm.value.montant = maxClientPaymentAmount.value;
		}
	}
);

const isInsuranceFacture = (row) => row?.type === 'FactureAssurance' || row?.insurance?.hasInsurance === true;

const submitPayment = async () => {

	if (!selectedFacture.value) return;
	const montant = Number(payForm.value.montant) || 0;
	const max = Number(selectedFacture.value.reste) || 0;

	if (montant < 0) {
		toast.add({ severity: 'warn', summary: 'Montant', detail: 'Saisissez un montant valide', life: 2500 });
		return;
	}
	if (montant <= 0) {
		toast.add({ severity: 'warn', summary: 'Montant', detail: 'Saisissez un montant valide', life: 2500 });
		return;
	}
	if (montant > max) {
		toast.add({ severity: 'warn', summary: 'Montant', detail: `Le montant ne peut dépasser ${formatFcfa(max)}`, life: 2500 });
		return;
	}
	if (!payForm.value.modeId || !payForm.value.date || !payForm.value.time) {
		toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Mode, date et heure sont requis', life: 2500 });
		return;
	}
	try {
		payLoading.value = true;
		const canPrintClientReceipt = montant > 0;
		const isInsured = isInsuranceFacture(selectedFacture.value);
		const claimId = selectedFacture.value.factureAssuranceId || selectedFacture.value.insurance?.factureAssuranceId || selectedFacture.value.id;

		let res;
		if (isInsured) {
			res = await payInsurancePatientShare(claimId, {
				modeId: payForm.value.modeId,
				date: `${payForm.value.date}T${payForm.value.time}`,
				amount: montant
			}, token);
		} else {
			res = await payFacture(selectedFacture.value.id, {
				montant,
				modeId: payForm.value.modeId,
				date: payForm.value.date,
				time: payForm.value.time
			}, token);
		}

		const factureId = selectedFacture.value.id;
		const paymentId = res?.paiement_id ?? res?.paiementId ?? null;
		const toastPayload = {
			severity: 'success',
			summary: 'Paiement',
			detail: 'Paiement enregistré',
			life: 10000,
			data: canPrintClientReceipt ? {
				actionLabel: 'Imprimer le reçu',
				action: async () => {
					if (!paymentId) {
						await loadPayments();
						const match = payments.value
							.filter((p) => Number(p.factureId) === Number(factureId))
							.reduce((maxId, p) => (p?.pId && p.pId > maxId ? p.pId : maxId), 0);
						if (!match) {
							toast.add({ severity: 'warn', summary: 'Paiement', detail: 'Reçu introuvable pour cette facture.', life: 3000 });
							return;
						}
						await printReceiptById(match);
						return;
					}
					await printReceiptById(paymentId);
				}
			} : undefined
		};

		toast.add(toastPayload);
		payDialogVisible.value = false;
		const tasks = [loadFactures(), loadPayments()];
		if (isInsured) {
			tasks.push(refreshInsuranceViews({ includePayments: false }));
		}
		await Promise.all(tasks);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Paiement', detail: error?.response?.data?.error || 'Enregistrement impossible', life: 3500 });
	} finally {
		payLoading.value = false;
	}
};

const confirmResetPaymentDialog = () => {
	if (!activeInvoiceContext.value || !canResetInvoicePayments.value) {
		return;
	}

	if (!selectedFacture.value?.id && activeInvoiceContext.value?.id) {
		selectedFacture.value = activeInvoiceContext.value;
	}

	resetPaymentDialogVisible.value = true;
};

const reloadFacturePreview = async (factureId) => {
	if (!previewDialogVisible.value || !factureId) {
		return;
	}

	previewLoading.value = true;
	try {
		previewData.value = await fetchFactureDetail(factureId, token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Actualisation du détail impossible', life: 3500 });
	} finally {
		previewLoading.value = false;
	}
};

const resetSelectedDevisPayments = async () => {
	const factureId = selectedFacture.value?.id ?? previewData.value?.id ?? activeInvoiceContext.value?.id;
	if (!factureId) {
		return;
	}

	try {
		resetPaymentsLoading.value = true;
		await resetFacturePayments(factureId, token);
		toast.add({ severity: 'success', summary: 'Facture', detail: 'La facture a été réinitialisée.', life: 3000 });
		resetPaymentDialogVisible.value = false;
		payDialogVisible.value = false;
		await Promise.all([loadFactures(), loadPayments()]);
		await reloadFacturePreview(factureId);

		const updatedRow = factures.value.find((row) => Number(row.id) === Number(factureId));
		if (updatedRow) {
			selectedFacture.value = updatedRow;
		}
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Réinitialisation impossible.', life: 3500 });
	} finally {
		resetPaymentsLoading.value = false;
	}
};

const openValidateDialog = (row) => {
	pendingFacture.value = row;
	validateDialogVisible.value = true;
};

const confirmValidate = async () => {
	if (!pendingFacture.value) return;
	validateLoading.value = true;
	try {
		const isInsured = isInsuranceFacture(pendingFacture.value);
		if (isInsured) {
			const claimId = pendingFacture.value.factureAssuranceId || pendingFacture.value.insurance?.factureAssuranceId || pendingFacture.value.id;
			const classicMethod = getDefaultClassicMethod(paymentMethods.value);
			await payInsurancePatientShare(claimId, {
				modeId: classicMethod?.id,
				date: new Date().toISOString(),
				amount: 0
			}, token);
		} else {
			await validateEmptyFacture(pendingFacture.value.id, token);
		}
		toast.add({ severity: 'success', summary: 'Validation', detail: 'Facture vide validée', life: 2500 });
		validateDialogVisible.value = false;
		const tasks = [loadFactures(), loadPayments()];
		if (isInsured) {
			tasks.push(refreshInsuranceViews({ includePayments: false }));
		}
		await Promise.all(tasks);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Validation', detail: 'Échec de la validation', life: 3500 });
	} finally {
		validateLoading.value = false;
	}
};

const openModifyDialog = async (row) => {
	if (!canModifyInvoiceByRole.value) return;
	factureConsultId.value = row.consultation;
	try {
		const invoice = await fetchFactureLines(row.consultation, token);
		factureLines.value = invoice.lines?.length ? invoice.lines : [createEmptyLine()];
		factureDate.value = invoice.date || '';
		factureTime.value = invoice.time || '';
		factureDialogVisible.value = true;
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Impossible de charger la facture', life: 3500 });
	}
};

const openTourModifyDialog = async () => {
	if (!firstModifiableFacture.value) return;
	await openModifyDialog(firstModifiableFacture.value);
};

const openTourModifyDialogStable = async () => {
	resetTourDialogs();
	await nextTick();
	await waitForTourUi();
	await openTourModifyDialog();
	await nextTick();
};

const createEmptyLine = () => ({ dent: [], type: '', description: '', prix: 0, quantite: 1 });

const addFactureLine = () => {
	factureLines.value.push(createEmptyLine());
};

const removeFactureLine = (index) => {
	factureLines.value.splice(index, 1);
	if (!factureLines.value.length) factureLines.value.push(createEmptyLine());
};

const saveFacture = async () => {
	if (!factureConsultId.value) return;
	factureSaving.value = true;
	try {
		await updateFactureLines(factureConsultId.value, {
			lines: factureLines.value,
			date: factureDate.value,
			time: factureTime.value
		}, token);
		toast.add({ severity: 'success', summary: 'Facture', detail: 'Facture enregistrée', life: 2500 });
		factureDialogVisible.value = false;
		await loadFactures();
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Enregistrement impossible', life: 3500 });
	} finally {
		factureSaving.value = false;
	}
};

const openPreviewDialog = async (row) => {
	previewDialogVisible.value = true;
	previewLoading.value = true;
	previewDialogTab.value = 'services';
	selectedFacture.value = row;
	try {
		previewData.value = await fetchFactureDetail(row.id, token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Aperçu indisponible', life: 3500 });
	} finally {
		previewLoading.value = false;
	}
};

const openTourPreviewDialog = async () => {
	if (!firstPreviewableFacture.value) return;
	await openPreviewDialog(firstPreviewableFacture.value);
};

const openTourPreviewDialogStable = async () => {
	resetTourDialogs();
	await nextTick();
	await waitForTourUi();
	await openTourPreviewDialog();
	await nextTick();
};

const resetTourDialogs = () => {
	payDialogVisible.value = false;
	validateDialogVisible.value = false;
	factureDialogVisible.value = false;
	previewDialogVisible.value = false;
	pendingFacture.value = null;
	selectedFacture.value = null;
	factureConsultId.value = null;
	factureDate.value = '';
	factureTime.value = '';
};

const capturePageState = () => ({
	activeView: activeView.value,
	factureType: factureType.value,
	factureRange: cloneValue(factureRange.value),
	paymentRange: cloneValue(paymentRange.value),
	factures: cloneValue(factures.value),
	payments: cloneValue(payments.value),
	paymentMethods: cloneValue(paymentMethods.value),
	payForm: cloneValue(payForm.value),
	factureLines: cloneValue(factureLines.value),
	previewData: cloneValue(previewData.value),
	selectedFacture: cloneValue(selectedFacture.value),
	pendingFacture: cloneValue(pendingFacture.value)
});

const restorePageState = async (state) => {
	if (!state) return;
	setActiveView(state.activeView || 'overview');
	factureType.value = state.factureType || 'all';
	factureRange.value = cloneValue(state.factureRange) || [];
	paymentRange.value = cloneValue(state.paymentRange) || [];
	factures.value = cloneValue(state.factures) || [];
	payments.value = cloneValue(state.payments) || [];
	paymentMethods.value = cloneValue(state.paymentMethods) || [];
	payForm.value = cloneValue(state.payForm) || payForm.value;
	factureLines.value = cloneValue(state.factureLines) || [];
	previewData.value = cloneValue(state.previewData) || null;
	selectedFacture.value = cloneValue(state.selectedFacture) || null;
	pendingFacture.value = cloneValue(state.pendingFacture) || null;
	await nextTick();
};

const prepareGuidedTourDemo = async () => {
	guidedTourPageState = capturePageState();
	activateCaisseTourMock();
	resetCaisseTourMockData();
	guidedTourDemoActive = true;
	setActiveView('overview');
	await Promise.all([loadFactures(), loadPayments(), loadPaymentMethods(), loadAssurances()]);
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
		deactivateCaisseTourMock();
		guidedTourDemoActive = false;
		const stateToRestore = guidedTourPageState;
		guidedTourPageState = null;
		await restorePageState(stateToRestore);
	})().finally(() => {
		guidedTourCleanupPromise = null;
	});

	return guidedTourCleanupPromise;
};

const handleGuidedTourRequest = async (event) => {
	if (event?.detail?.routeName !== 'caisse' || isGuidedTourStarting.value) {
		return;
	}

	if (facturesLoading.value || paymentsLoading.value) {
		toast.add({
			severity: 'warn',
			summary: 'Aide guidee',
			detail: 'Attendez la fin du chargement de la caisse avant de lancer le tour.',
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

		const steps = createCaisseTour({
			activeView: activeView.value,
			canOpenPaymentDialog: Boolean(firstPayableFacture.value),
			canOpenPreviewDialog: Boolean(firstPreviewableFacture.value),
			canOpenModifyDialog: Boolean(firstModifiableFacture.value),
			openPaymentDialog: openTourPaymentDialogStable,
			openPreviewDialog: openTourPreviewDialogStable,
			openModifyDialog: openTourModifyDialogStable,
			switchView: async (view) => {
				setActiveView(view);
				resetTourDialogs();
				await nextTick();
				await waitForTourUi(220);
			},
			closeAllDialogs: resetTourDialogs
		});

		await startTourGuide({
			group: resolveCaisseTourGroup(activeView.value),
			steps,
			onAfterExit: cleanupGuidedTourDemo,
			onFinish: cleanupGuidedTourDemo
		});
	} catch (error) {
		console.error('Erreur lancement guided tour caisse', error);
		await cleanupGuidedTourDemo();
		toast.add({
			severity: 'error',
			summary: 'Aide guidee',
			detail: 'Impossible de lancer le tour de la caisse.',
			life: 3000
		});
	} finally {
		isGuidedTourStarting.value = false;
	}
};

const printInvoice = async () => {
	if (!previewData.value?.id) return;
	try {
		const res = await fetchInvoicePrintData(previewData.value.id, token);
		await printComponent(PrintDevisBody, { doc: res.doc, title: res.title || 'Facture' });
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Impression indisponible', life: 3500 });
	}
};

const printPayments = async () => {
	if (!paymentRange.value || paymentRange.value.length < 2) return;
	const [start, end] = paymentRange.value;
	try {
		const res = await fetchPaymentsListPrintData({ start: toApiDate(start), end: toApiDate(end) }, token);
		await printComponent(PrintPaymentsListBody, {
			paiements: res.paiements || [],
			start: res.start,
			end: res.end,
			total: res.total || 0
		}, { orientation: 'landscape' });
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Paiements', detail: 'Impression indisponible', life: 3500 });
	}
};

const printPayment = async (row) => {
	if (!row?.pId) return;
	try {
		const res = await fetchReceiptPrintData(row.pId, token);
		await printComponent(
			PrintReceiptBody,
			{ paiement: res.paiement },
			{ format: [226.77, 255.12], width: '80mm' }
		);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Paiement', detail: 'Impression indisponible', life: 3500 });
	}
};

const printReceipt = async (row) => {
	if (!row?.pId) return;
	try {
		const res = await fetchTicketPrintData(row.pId, token);
		await printComponent(
			PrintTicketBody,
			{ paiement: res.paiement },
			{ format: [226.77, 255.12], width: '80mm' }
		);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Reçu', detail: 'Impression indisponible', life: 3500 });
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
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Reçu', detail: 'Impression indisponible', life: 3500 });
	}
};

const sendInvoiceBySms = async (row) => {
	if (!row?.id) return;
	try {
		const res = await sendInvoiceSms(row.id, {}, token);
		toast.add({
			severity: res?.success ? 'success' : 'warn',
			summary: 'SMS Facture',
			detail: res?.success ? 'Facture ajoutée à la file SMS.' : (res?.error || 'Échec de l\'envoi.'),
			life: 3500
		});
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'SMS Facture', detail: 'Envoi impossible.', life: 3500 });
	}
};

const sendReceiptBySms = async (row) => {
	if (!row?.pId) return;
	try {
		const res = await sendReceiptSms(row.pId, {}, token);
		toast.add({
			severity: res?.success ? 'success' : 'warn',
			summary: 'SMS Reçu',
			detail: res?.success ? 'Reçu ajouté à la file SMS.' : (res?.error || 'Échec de l\'envoi.'),
			life: 3500
		});
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'SMS Reçu', detail: 'Envoi impossible.', life: 3500 });
	}
};

watch([factureRangeKey, factureType], () => {
	if (factureType.value === 'impaye_toutes') {
		loadFactures();
		return;
	}
	if (!factureRangeKey.value) return;
	loadFactures();
}, { immediate: true });

watch(paymentRangeKey, () => {
	if (!paymentRangeKey.value) return;
	loadPayments();
}, { immediate: true });
watch(activeView, (view) => {
	if (view === 'assurances') {
		loadInsuranceDashboard();
	}
}, { immediate: true });

onMounted(async () => {
	await loadPublicGeneralSettings();
	await Promise.all([loadPaymentMethods(), loadAssurances()]);
	setActiveView(activeView.value);
	isInitialLoadPhase.value = false;
	window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
});

onBeforeUnmount(() => {
	window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
	deactivateCaisseTourMock();
	guidedTourDemoActive = false;
	resetTourDialogs();
});
</script>

<template>
	<div class="page-shell">
		<div v-if="loadErrorMessage" class="mb-4 flex min-h-[320px] flex-col items-center justify-center gap-4 rounded-2xl border border-amber-200/70 bg-amber-50/70 p-8 dark:border-amber-800/70 dark:bg-amber-950/20">
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
		<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
			<div>
				<h1 class="text-2xl font-semibold mb-1">Gestion de la caisse</h1>
				<p class="muted">Suivi des factures et paiements avec PrimeVue.</p>
			</div>

		</div>

		<Tabs :value="activeView" @update:value="setActiveView">
			<TabList data-tour="caisse.tabs">
				<Tab value="overview">Vue d'ensemble</Tab>
				<Tab value="factures">Factures</Tab>
				<Tab value="paiements">Paiements</Tab>
				<Tab value="assurances">Assurances</Tab>
			</TabList>
			<TabPanels class="mt-4">
				<TabPanel value="overview">
					<CaisseOverview :factures="factures" :factures-loading="facturesLoading" :payments="payments"
						:hide-patient-phone="shouldHidePatientPhoneForMedecin"
						:allow-invoice-modification="canModifyInvoiceByRole"
						:payments-loading="paymentsLoading" :facture-type="factureType" :facture-range="factureRange"
						:payment-range="paymentRange" @update:factureType="setFactureType"
						@update:factureRange="setFactureRange" @update:paymentRange="setPaymentRange"
						@refresh-factures="loadFactures" @refresh-payments="loadPayments" @pay="openPayDialog"
						@validate-free="openValidateDialog" @modify="openModifyDialog" @preview="openPreviewDialog"
						@print-payments="printPayments" @print-payment="printPayment" @print-receipt="printReceipt"
						@send-invoice-sms="sendInvoiceBySms" @send-receipt-sms="sendReceiptBySms" />
				</TabPanel>
				<TabPanel value="factures">
					<CaisseFactures :factures="factures" :factures-loading="facturesLoading" :facture-type="factureType"
						:hide-patient-phone="shouldHidePatientPhoneForMedecin"
						:allow-invoice-modification="canModifyInvoiceByRole"
						:facture-range="factureRange" @update:factureType="setFactureType" @update:factureRange="setFactureRange"
						@refresh-factures="loadFactures" @pay="openPayDialog" @validate-free="openValidateDialog"
						@modify="openModifyDialog" @preview="openPreviewDialog" @send-invoice-sms="sendInvoiceBySms" />
				</TabPanel>
				<TabPanel value="paiements">
					<CaissePaiements :payments="payments" :payments-loading="paymentsLoading"
						:hide-patient-phone="shouldHidePatientPhoneForMedecin"
						:payment-range="paymentRange" @update:paymentRange="setPaymentRange"
						@refresh-payments="loadPayments" @print-payments="printPayments" @print-payment="printPayment"
						@print-receipt="printReceipt" @send-receipt-sms="sendReceiptBySms" />
				</TabPanel>
				<TabPanel value="assurances">
					<CaisseAssurances
						:dashboard-cards="insuranceDashboard"
						:lots-assurance="insuranceLotsAssurance"
						:lots="insuranceLots"
						:open-lots="insuranceOpenLots"
						:unassigned-claims="insuranceUnassignedClaims"
						:selected-claim="insuranceSelectedClaim"
						:selected-lot="insuranceSelectedLot"
						:payment-methods="paymentMethods"
						:dashboard-loading="insuranceDashboardLoading"
						:lots-loading="insuranceLotsLoading"
						:claim-loading="insuranceClaimLoading"
						:lot-loading="insuranceLotLoading"
						:action-loading-id="insuranceActionLoadingId"
						@refresh-dashboard="loadInsuranceDashboard"
						@refresh-lots="loadInsuranceLots"
						@refresh-lot="() => loadInsuranceLotDetail(insuranceSelectedLot)"
						@view-lots="viewInsuranceLots"
						@back-to-dashboard="backToInsuranceDashboard"
						@back-to-lots="backToInsuranceLots"
						@create-lot="createInsuranceLot"
						@update-lot="updateInsuranceLotMeta"
						@view-lot="viewInsuranceLot"
						@send-lot="sendInsuranceLot"
						@reopen-lot="reopenInsuranceLot"
						@confirm-lot="confirmInsuranceLot"
						@unconfirm-lot="unconfirmInsuranceLot"
						@refund-lot="refundInsuranceLot"
						@cancel-refund="cancelInsuranceLotRefund"
						@view-claim="viewInsuranceClaim"
						@back-from-claim="backFromInsuranceClaim"
						@collect-patient-share="collectPatientShare"
						@modify-claim="modifyInsuranceClaim"
						@assign-claim="assignClaimToLot"
						@change-claim-lot="changeClaimLot"
						@remove-claim="removeClaimFromLot"
						@print-receipt="printInsuranceReceipt"
						@print-claim="printInsuranceClaim"
					/>
				</TabPanel>
			</TabPanels>
		</Tabs>

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
			@update:payDialogVisible="payDialogVisible = $event"
			@update:paymentDialogTab="paymentDialogTab = $event"
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
			@save-facture="saveFacture"
			@print-invoice="printInvoice"
		/>
		</template>
	</div>
</template>

<style scoped>
.page-shell {
	padding: 1.5rem;
	background: var(--surface-ground);
	min-height: 100vh;
}

.card {
	border-radius: 14px;
	padding: 1.25rem;
	background: var(--surface-card);
	box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
	border: 1px solid var(--surface-border);
}

.eyebrow {
	letter-spacing: 0.08em;
	text-transform: uppercase;
	font-size: 0.8rem;
	color: #94a3b8;
	margin: 0;
}

.muted {
	color: #6b7280;
}

.dialog-note {
	color: #374151;
}

.preview-subtext,
.preview-summary-label {
	color: #6b7280;
}

.preview-table-card,
.preview-payment-card {
	background: rgba(255, 255, 255, 0.92);
}

.preview-table-head,
.preview-table-foot {
	background: rgba(248, 250, 252, 0.92);
}

.preview-table-head-row,
.preview-table-row {
	border-color: #e5e7eb;
}

.preview-table-muted,
.preview-payment-date,
.preview-payment-description,
.preview-payment-meta,
.preview-payment-meta-label,
.preview-empty-state {
	color: #64748b;
}

.preview-table-strong,
.preview-table-emphasis,
.preview-payment-amount {
	color: #0f172a;
}

.preview-table-warning {
	color: #b45309;
}

.app-dark .muted,
.app-dark .dialog-note,
.app-dark .preview-subtext,
.app-dark .preview-summary-label,
.app-dark .preview-table-muted,
.app-dark .preview-payment-date,
.app-dark .preview-payment-description,
.app-dark .preview-payment-meta,
.app-dark .preview-payment-meta-label,
.app-dark .preview-empty-state {
	color: #94a3b8;
}

.app-dark .preview-header-card,
.app-dark .preview-summary-card,
.app-dark .preview-empty-state {
	background: linear-gradient(135deg, rgba(30, 41, 59, 0.88), rgba(15, 23, 42, 0.82));
	border-color: #334155;
}

.app-dark .preview-table-card,
.app-dark .preview-payment-card {
	background: rgba(15, 23, 42, 0.92);
	border-color: #334155;
	box-shadow: 0 10px 30px rgba(0, 0, 0, 0.28);
}

.app-dark .preview-table-head,
.app-dark .preview-table-foot {
	background: rgba(30, 41, 59, 0.92);
	color: #e2e8f0;
}

.app-dark .preview-table-head-row,
.app-dark .preview-table-row {
	border-color: #334155;
}

.app-dark .preview-table-strong,
.app-dark .preview-table-emphasis,
.app-dark .preview-payment-amount,
.app-dark .preview-payment-card .font-medium,
.app-dark .preview-header-card .font-semibold,
.app-dark .preview-summary-card .font-semibold {
	color: #f8fafc;
}

.app-dark .preview-table-warning {
	color: #fbbf24;
}
</style>
