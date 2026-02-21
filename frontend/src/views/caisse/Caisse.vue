<script setup>
import CaisseFactures from '@/components/caisse/CaisseFactures.vue';
import CaisseOverview from '@/components/caisse/CaisseOverview.vue';
import CaissePaiements from '@/components/caisse/CaissePaiements.vue';
import PrintDevisBody from '@/components/print/PrintDevisBody.vue';
import PrintPaymentsListBody from '@/components/print/PrintPaymentsListBody.vue';
import PrintReceiptBody from '@/components/print/PrintReceiptBody.vue';
import PrintTicketBody from '@/components/print/PrintTicketBody.vue';
import { usePrinter } from '@/composables/usePrinter';
import {
	fetchDevis,
	fetchDevisDetail,
	fetchFactureLines,
	fetchPaymentMethods,
	fetchPayments,
	payDevis,
	updateFactureLines,
	validateEmptyDevis
} from '@/services/caisseService';
import {
	fetchInvoicePrintData,
	fetchPaymentsListPrintData,
	fetchReceiptPrintData,
	fetchTicketPrintData
} from '@/services/printService';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useToast } from 'primevue/usetoast';
import { computed, onMounted, ref, watch } from 'vue';

const toast = useToast();
const token = localStorage.getItem('token');
const { printComponent } = usePrinter();

const viewStorageKey = 'caisse.view';
const activeView = ref(localStorage.getItem(viewStorageKey) || 'overview');

const today = new Date();
const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

const devisType = ref('all');
const devisRange = ref([startOfMonth, endOfMonth]);
const paymentRange = ref([startOfMonth, endOfMonth]);

const devis = ref([]);
const payments = ref([]);
const devisLoading = ref(false);
const paymentsLoading = ref(false);

const toApiDate = (value) => {
	if (!value) return '';
	const date = value instanceof Date ? value : new Date(value);
	return date.toISOString().slice(0, 10);
};

const currentTime = () => {
	const date = new Date();
	return date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', hour12: false });
};

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const paymentMethods = ref([]);
const payDialogVisible = ref(false);
const selectedDevis = ref(null);
const payForm = ref({ montant: 0, modeId: null, date: toApiDate(today), time: currentTime() });

const validateDialogVisible = ref(false);
const pendingDevis = ref(null);

const factureDialogVisible = ref(false);
const factureConsultId = ref(null);
const factureLines = ref([]);
const factureSaving = ref(false);

const previewDialogVisible = ref(false);
const previewLoading = ref(false);
const previewData = ref(null);

// Explicit setters avoid template auto-unwrapping issues on refs
const setDevisType = (val) => {
	devisType.value = val || 'all';
};

const setDevisRange = (val) => {
	devisRange.value = Array.isArray(val) ? val : [];
};

const setPaymentRange = (val) => {
	paymentRange.value = Array.isArray(val) ? val : [];
};

const soinsList = [
	'Consultation',
	'Détartrage',
	'Extraction',
	'Remplissage',
	'Composite',
	'Amalgame',
	'Traitement de canal',
	'Traumatisme',
	'Couronne',
	'Blanchiment',
	'Radio',
	'Prothèse',
	'Orthodontie',
	'Chirurgie'
];

const factureTotal = computed(() => factureLines.value.reduce((sum, line) => sum + (Number(line.prix) || 0) * (Number(line.quantite) || 0), 0));

const remainingAfterPay = computed(() => {
	if (!selectedDevis.value) return 0;
	const reste = Number(selectedDevis.value.reste) || 0;
	const montant = Number(payForm.value.montant) || 0;
	return Math.max(0, reste - montant);
});

const setActiveView = (view) => {
	const allowed = ['overview', 'factures', 'paiements'];
	const normalized = allowed.includes(view) ? view : 'overview';
	activeView.value = normalized;
	localStorage.setItem(viewStorageKey, normalized);
};

const loadDevis = async () => {
	if (!devisRange.value || devisRange.value.length < 2) return;
	try {
		devisLoading.value = true;
		const [start, end] = devisRange.value;
		const res = await fetchDevis({ start: toApiDate(start), end: toApiDate(end), unpaidOnly: devisType.value === 'impaye' }, token);
		devis.value = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Factures', detail: 'Chargement des factures impossible', life: 3500 });
	} finally {
		devisLoading.value = false;
	}
};

const loadPayments = async () => {
	if (!paymentRange.value || paymentRange.value.length < 2) return;
	try {
		paymentsLoading.value = true;
		const [start, end] = paymentRange.value;
		const res = await fetchPayments({ start: toApiDate(start), end: toApiDate(end) }, token);
		payments.value = Array.isArray(res) ? res : (Array.isArray(res?.data) ? res.data : []);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Paiements', detail: 'Chargement des paiements impossible', life: 3500 });
	} finally {
		paymentsLoading.value = false;
	}
};

const loadPaymentMethods = async () => {
	try {
		paymentMethods.value = await fetchPaymentMethods(token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'warn', summary: 'Modes de paiement', detail: 'Chargement impossible', life: 3000 });
	}
};

const openPayDialog = async (row) => {
	selectedDevis.value = row;
	payForm.value = {
		montant: Number(row.reste) || 0,
		modeId: null,
		date: toApiDate(new Date()),
		time: currentTime()
	};
	await loadPaymentMethods();
	payDialogVisible.value = true;
};

const submitPayment = async () => {
	if (!selectedDevis.value) return;
	const montant = Number(payForm.value.montant) || 0;
	const max = Number(selectedDevis.value.reste) || 0;
	if (!montant || montant < 0) {
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
		const res = await payDevis(selectedDevis.value.id, {
			montant,
			modeId: payForm.value.modeId,
			date: payForm.value.date,
			time: payForm.value.time
		}, token);
		const devisId = selectedDevis.value.id;
		const paymentId = res?.paiement_id ?? res?.paiementId ?? null;
		const toastPayload = {
			severity: 'success',
			summary: 'Paiement',
			detail: 'Paiement enregistré',
			life: 10000,
			data: {
				actionLabel: 'Imprimer le reçu',
				action: async () => {
					if (!paymentId) {
						await loadPayments();
						const match = payments.value
							.filter((p) => Number(p.devisId) === Number(devisId))
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
			}
		};
		toast.add(toastPayload);
		payDialogVisible.value = false;
		await Promise.all([loadDevis(), loadPayments()]);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Paiement', detail: 'Enregistrement impossible', life: 3500 });
	}
};

const openValidateDialog = (row) => {
	pendingDevis.value = row;
	validateDialogVisible.value = true;
};

const confirmValidate = async () => {
	if (!pendingDevis.value) return;
	try {
		await validateEmptyDevis(pendingDevis.value.id, token);
		toast.add({ severity: 'success', summary: 'Validation', detail: 'Facture vide validée', life: 2500 });
		validateDialogVisible.value = false;
		await Promise.all([loadDevis(), loadPayments()]);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Validation', detail: 'Échec de la validation', life: 3500 });
	}
};

const openModifyDialog = async (row) => {
	factureConsultId.value = row.consultation;
	try {
		const lignes = await fetchFactureLines(row.consultation, token);
		factureLines.value = lignes.length
			? lignes.map((l) => ({
				dent: l.dent || '',
				type: l.type || l.designation || '',
				description: l.description || l.designation || '',
				prix: Number(l.prix || l.montant || 0),
				quantite: Number(l.quantite || 1)
			}))
			: [createEmptyLine()];
		factureDialogVisible.value = true;
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Impossible de charger la facture', life: 3500 });
	}
};

const createEmptyLine = () => ({ dent: '', type: '', description: '', prix: 0, quantite: 1 });

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
		await updateFactureLines(factureConsultId.value, factureLines.value, token);
		toast.add({ severity: 'success', summary: 'Facture', detail: 'Facture enregistrée', life: 2500 });
		factureDialogVisible.value = false;
		await loadDevis();
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
	try {
		previewData.value = await fetchDevisDetail(row.id, token);
	} catch (error) {
		console.error(error);
		toast.add({ severity: 'error', summary: 'Facture', detail: 'Aperçu indisponible', life: 3500 });
	} finally {
		previewLoading.value = false;
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
		toast.add({ severity: 'error', summary: 'Paiement', detail: 'Impression indisponible', life: 3500 });
	}
};

watch([devisRange, devisType], loadDevis, { immediate: true });
watch(paymentRange, loadPayments, { immediate: true });

onMounted(() => {
	setActiveView(activeView.value);
});
</script>

<template>
	<div class="page-shell">
		<div class="flex flex-wrap items-center justify-between gap-3 mb-4">
			<div>
				<p class="eyebrow">Caisse</p>
				<h1 class="text-2xl font-semibold mb-1">Gestion de la caisse</h1>
				<p class="muted">Suivi des factures et paiements avec PrimeVue.</p>
			</div>

		</div>

		<Tabs :value="activeView" @update:value="setActiveView">
			<TabList>
				<Tab value="overview">Vue d'ensemble</Tab>
				<Tab value="factures">Factures</Tab>
				<Tab value="paiements">Paiements</Tab>
			</TabList>
			<TabPanels class="mt-4">
				<TabPanel value="overview">
					<CaisseOverview :devis="devis" :devis-loading="devisLoading" :payments="payments"
						:payments-loading="paymentsLoading" :devis-type="devisType" :devis-range="devisRange"
						:payment-range="paymentRange" @update:devisType="setDevisType"
						@update:devisRange="setDevisRange" @update:paymentRange="setPaymentRange"
						@refresh-devis="loadDevis" @refresh-payments="loadPayments" @pay="openPayDialog"
						@validate-free="openValidateDialog" @modify="openModifyDialog" @preview="openPreviewDialog"
						@print-payments="printPayments" @print-payment="printPayment" @print-receipt="printReceipt" />
				</TabPanel>
				<TabPanel value="factures">
					<CaisseFactures :devis="devis" :devis-loading="devisLoading" :devis-type="devisType"
						:devis-range="devisRange" @update:devisType="setDevisType" @update:devisRange="setDevisRange"
						@refresh-devis="loadDevis" @pay="openPayDialog" @validate-free="openValidateDialog"
						@modify="openModifyDialog" @preview="openPreviewDialog" />
				</TabPanel>
				<TabPanel value="paiements">
					<CaissePaiements :payments="payments" :payments-loading="paymentsLoading"
						:payment-range="paymentRange" @update:paymentRange="setPaymentRange"
						@refresh-payments="loadPayments" @print-payments="printPayments" @print-payment="printPayment"
						@print-receipt="printReceipt" />
				</TabPanel>
			</TabPanels>
		</Tabs>

		<Dialog v-model:visible="payDialogVisible" header="Régler la facture" :modal="true" :style="{ width: '480px' }">
			<div class="flex flex-col gap-3">
				<div>
					<label class="text-sm text-gray-600">Mode de paiement</label>
					<Select v-model="payForm.modeId"
						:options="paymentMethods.map((m) => ({ label: m.libelle, value: m.id, disabled: !m.actif }))"
						optionLabel="label" optionValue="value" optionDisabled="disabled" placeholder="Sélectionner" />
				</div>
				<div class="grid grid-cols-2 gap-3">
					<div>
						<label class="text-sm text-gray-600">Date</label>
						<InputText v-model="payForm.date" type="date" class="w-full" />
					</div>
					<div>
						<label class="text-sm text-gray-600">Heure</label>
						<InputText v-model="payForm.time" type="time" class="w-full" />
					</div>
				</div>
				<div>
					<label class="text-sm text-gray-600">Montant</label>
					<InputNumber v-model="payForm.montant" mode="decimal" locale="fr-FR" :min="0" class="w-full" />
					<p class="text-xs text-gray-500 mt-1">Reste après paiement : {{ formatFcfa(remainingAfterPay) }}</p>
				</div>
			</div>
			<template #footer>
				<Button label="Annuler" text @click="payDialogVisible = false" />
				<Button label="Confirmer" severity="success" icon="pi pi-check" @click="submitPayment" />
			</template>
		</Dialog>

		<Dialog v-model:visible="validateDialogVisible" header="Valider la facture vide" :modal="true"
			:style="{ width: '420px' }">
			<p class="text-sm text-gray-700">Confirmer que cette facture est vide et doit être marquée comme validée.
			</p>
			<template #footer>
				<Button label="Annuler" text @click="validateDialogVisible = false" />
				<Button label="Valider" severity="success" icon="pi pi-check" @click="confirmValidate" />
			</template>
		</Dialog>

		<Dialog v-model:visible="factureDialogVisible" header="Modifier la facture" :modal="true"
			:style="{ width: '720px' }">
			<div class="flex flex-col gap-3">
				<div v-for="(line, idx) in factureLines" :key="idx" class="border rounded p-3 flex flex-col gap-2">
					<div class="grid md:grid-cols-2 gap-2">
						<InputText v-model="line.dent" placeholder="Dent" />
						<Select v-model="line.type" :options="soinsList" placeholder="Acte / Soin" />
					</div>
					<InputText v-model="line.description" placeholder="Description" />
					<div class="grid grid-cols-2 gap-2">
						<InputNumber v-model="line.prix" mode="decimal" locale="fr-FR" :min="0" class="w-full"
							placeholder="Prix" />
						<InputNumber v-model="line.quantite" :min="1" class="w-full" placeholder="Quantité" />
					</div>
					<div class="flex justify-end">
						<Button label="Supprimer" icon="pi pi-trash" text severity="danger"
							@click="removeFactureLine(idx)" />
					</div>
				</div>
				<Button label="Ajouter une ligne" icon="pi pi-plus" outlined @click="addFactureLine" />
				<div class="text-right font-semibold">Total TTC : {{ formatFcfa(factureTotal) }}</div>
			</div>
			<template #footer>
				<Button label="Annuler" text @click="factureDialogVisible = false" />
				<Button label="Enregistrer" severity="primary" icon="pi pi-save" :loading="factureSaving"
					@click="saveFacture" />
			</template>
		</Dialog>

		<Dialog v-model:visible="previewDialogVisible" header="Détail de la facture" :modal="true"
			:style="{ width: '820px' }">
			<div v-if="previewLoading" class="p-4 text-center text-gray-600">Chargement...</div>
			<div v-else-if="previewData" class="flex flex-col gap-3">
				<div class="flex items-center justify-between">
					<div>
						<p class="font-semibold">Facture n° {{ String(previewData.id).padStart(4, '0') }}</p>
						<p class="text-sm text-gray-600">Date : {{ previewData.date }}</p>
						<p class="text-sm text-gray-600">Patient : {{ previewData.patient?.nom }} {{
							previewData.patient?.prenom
						}}</p>
					</div>
					<Tag value="Reste {{ formatFcfa(previewData.reste) }}" severity="warning" />
				</div>
				<table class="w-full text-sm">
					<thead>
						<tr class="text-left border-b">
							<th class="py-2">Désignation</th>
							<th class="py-2">Qté</th>
							<th class="py-2 text-right">Prix</th>
							<th class="py-2 text-right">Total</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="(c, idx) in previewData.contenus || []" :key="idx" class="border-b">
							<td class="py-2">{{ c.designation }}</td>
							<td class="py-2">{{ c.qte }}</td>
							<td class="py-2 text-right">{{ formatFcfa(c.montant) }}</td>
							<td class="py-2 text-right">{{ formatFcfa(c.total) }}</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<th colspan="3" class="py-2 text-right">Total TTC</th>
							<th class="py-2 text-right">{{ formatFcfa(previewData.montant) }}</th>
						</tr>
						<tr>
							<th colspan="3" class="py-2 text-right">Reste à payer</th>
							<th class="py-2 text-right">{{ formatFcfa(previewData.reste) }}</th>
						</tr>
					</tfoot>
				</table>
			</div>
			<template #footer>
				<Button label="Fermer" text @click="previewDialogVisible = false" />
				<Button label="Imprimer" icon="pi pi-print" severity="info" @click="printInvoice" />
			</template>
		</Dialog>
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
</style>
