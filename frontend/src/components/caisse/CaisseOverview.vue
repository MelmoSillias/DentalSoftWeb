<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DataView from 'primevue/dataview';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    factures: { type: Array, default: () => [] },
    facturesLoading: { type: Boolean, default: false },
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    factureType: { type: String, default: 'all' },
    factureRange: { type: Array, default: () => [] },
    paymentRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false }
});

const emit = defineEmits([
    'update:factureType',
    'update:factureRange',
    'update:paymentRange',
    'refresh-factures',
    'refresh-payments',
    'pay',
    'validate-free',
    'modify',
    'preview',
    'print-payments',
    'print-payment',
    'print-receipt',
    'send-invoice-sms',
    'send-receipt-sms'
]);

const factureTypeOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Factures impayées', value: 'impaye' }
];

const factureTypeModel = computed({
    get: () => props.factureType,
    set: (val) => emit('update:factureType', val || 'all')
});

const factureRangeModel = computed({
    get: () => props.factureRange,
    set: (val) => emit('update:factureRange', val || [])
});

const paymentRangeModel = computed({
    get: () => props.paymentRange,
    set: (val) => emit('update:paymentRange', val || [])
});

const factureSearch = ref('');
const paymentsSearch = ref('');
const paymentFamilyFilter = ref('non-insurance');
const paymentModeFilter = ref('all');
const overviewDisplayMode = ref('standard');
const expandedInvoiceCards = ref({});
const showStatsModal = ref(false);

const overviewDisplayOptions = [
    { label: 'Affichage standard', value: 'standard' },
    { label: 'Affichage regroupé', value: 'grouped' }
];

const normalizeText = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

const matchesQuery = (parts, query) => {
    if (!query) return true;
    return parts.some((part) => normalizeText(part).includes(query));
};

const factureSearchQuery = computed(() => normalizeText(factureSearch.value.trim()));
const paymentsSearchQuery = computed(() => normalizeText(paymentsSearch.value.trim()));

const isInsurancePayment = (payment) => {
    const role = String(payment?.rolePaiement || '').toLowerCase();
    const mode = normalizeText(payment?.mode);

    return role === 'insurance' || mode.includes('assur');
};

const filteredFactures = computed(() => {
    const list = Array.isArray(props.factures) ? props.factures : [];
    const query = factureSearchQuery.value;
    return list.filter((row) => {
        const patient = (row.patient && typeof row.patient === 'object')
            ? `${row.patient.nom || ''} ${row.patient.prenom || ''}`.trim()
            : (row.patient || '');
        const status = computeStatus(row).label;
        return matchesQuery([
            patient,
            row.telephone,
            row.date,
            formatDate(row.date),
            row.montant,
            row.reste,
            status
        ], query);
    });
});

const filteredFacturesR = computed(() => {
    const rows = filteredFactures.value;
    const payments = Array.isArray(props.payments) ? props.payments : [];

    return rows.map((invoice) => {
        const invoiceId = Number(invoice?.id);
        const consultationId = Number(invoice?.consultation);

        const invoicePayments = payments
            .filter((payment) => payment?.type === 'facture' && Number(payment?.factureId) === invoiceId)
            .map((payment) => ({
                ...payment,
                detailType: 'facture_payment',
                detailLabel: 'Paiement facture'
            }));

        const consultationTicket = consultationId > 0
            ? payments.find((payment) => payment?.type === 'ticket' && Number(payment?.consultationId) === consultationId)
            : null;

        const detailRows = [
            ...invoicePayments,
            ...(consultationTicket ? [{
                ...consultationTicket,
                detailType: 'consultation_ticket',
                detailLabel: 'Ticket consultation'
            }] : [])
        ];

        return {
            ...invoice,
            detailRows,
            hasDetails: detailRows.length > 0,
            detailCount: detailRows.length
        };
    });
});

const computePaymentModeTag = (payment) => {
    if (payment?.insuranceStatus === 'pending') {
        return {
            label: payment?.mode || 'Assurance',
            severity: 'warning'
        };
    }

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

const paymentModeOptions = computed(() => {
    const options = (Array.isArray(props.payments) ? props.payments : []).reduce((acc, payment) => {
        const modeId = Number(payment?.modeId);
        if (!Number.isFinite(modeId) || modeId <= 0) {
            return acc;
        }

        if (!acc.some((option) => option.value === modeId)) {
            acc.push({ label: payment?.mode || 'Autre', value: modeId });
        }

        return acc;
    }, [{ label: 'Tous les modes', value: 'all' }]);

    return options.sort((left, right) => {
        if (left.value === 'all') return -1;
        if (right.value === 'all') return 1;
        return String(left.label).localeCompare(String(right.label), 'fr');
    });
});

const filteredPayments = computed(() => {
    const list = Array.isArray(props.payments) ? props.payments : [];
    const query = paymentsSearchQuery.value;
    return list.filter((row) => {
        if (paymentModeFilter.value !== 'all' && Number(row?.modeId) !== Number(paymentModeFilter.value)) {
            return false;
        }

        return matchesQuery([
            row.patient,
            row.telephone,
            row.date,
            formatDate(row.date, true),
            row.montant,
            row.mode,
            row.type,
            row.insuranceStatus,
            computePaymentModeTag(row).label
        ], query);
    });
});

const factureTotals = computed(() => {
    const list = filteredFactures.value;
    const totalRestant = list.reduce((sum, r) => sum + (Number(r.reste) || 0), 0);
    return {
        count: list.length,
        restant: totalRestant
    };
});

const paymentsTotals = computed(() => {
    const list = filteredPayments.value;
    const total = list.reduce((sum, r) => sum + (Number(r.montant) || 0), 0);
    return {
        count: list.length,
        montant: total
    };
});

// Statistiques détaillées pour le modal
const detailedStats = computed(() => {
    const allInvoices = props.factures || [];
    const allPayments = props.payments || [];
    const totalRevenue = allPayments.reduce((sum, payment) => sum + (Number(payment?.montant) || 0), 0);

    const totalInvoices = allInvoices.length;
    const totalUnpaid = allInvoices.reduce((sum, inv) => sum + (Number(inv.reste) || 0), 0);

    const statusCounts = {
        paid: 0,
        partial: 0,
        unpaid: 0,
        freeNotValidated: 0
    };
    allInvoices.forEach(inv => {
        const reste = Number(inv.reste) || 0;
        const montant = Number(inv.montant) || 0;
        if (inv.isRegle && reste === 0) statusCounts.paid++;
        else if (!inv.isRegle && reste === 0) statusCounts.freeNotValidated++;
        else if (reste === montant) statusCounts.unpaid++;
        else statusCounts.partial++;
    });

    const paymentModeBreakdown = {};
    allPayments.forEach(p => {
        const mode = p.mode || 'Autre';
        paymentModeBreakdown[mode] = (paymentModeBreakdown[mode] || 0) + (Number(p.montant) || 0);
    });

    const insurancePayments = allPayments.filter((payment) => isInsurancePayment(payment));
    const totalInsurance = insurancePayments.reduce((sum, p) => sum + (Number(p.montant) || 0), 0);
    const pendingInsurance = insurancePayments.filter(p => p.insuranceStatus === 'pending').length;
    const paymentModeRows = Object.entries(paymentModeBreakdown)
        .map(([mode, amount]) => ({ mode, amount: Number(amount) || 0 }))
        .sort((left, right) => right.amount - left.amount);

    return {
        totalInvoices,
        totalPaid: totalRevenue,
        totalUnpaid,
        statusCounts,
        paymentModeBreakdown,
        paymentModeRows,
        totalInsurance,
        pendingInsurance,
        totalPaymentsCount: allPayments.length,
        totalPaymentsAmount: totalRevenue
    };
});

const totalRevenueLabel = computed(() => formatFcfa(detailedStats.value.totalPaid));

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const formatDate = (value, withTime = false) => {
    if (!value) return '—';
    const date = new Date(value);
    const datePart = date.toLocaleDateString('fr-FR');
    if (!withTime) return datePart;
    const timePart = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    return `${datePart} ${timePart}`;
};

const displayPhone = (value) => (props.hidePatientPhone ? 'Masqué par l\'administrateur' : (value || '—'));

const computeStatus = (row) => {
    const montant = Number(row.montant) || 0;
    const reste = Number(row.reste) || 0;

    if (row.isRegle && reste === 0) return { label: 'Payé', severity: 'success' };
    if (!row.isRegle && reste === 0) return { label: 'Vide non validé', severity: 'secondary' };
    if (reste === montant) return { label: 'Impayé', severity: 'danger' };
    return { label: 'Partiellement payé', severity: 'warning' };
};

const computeInsuranceBadge = (row) => {
    const insurance = row?.insurance;
    if (!insurance?.hasInsurance) {
        return null;
    }

    return insurance.insuranceStatus === 'pending'
        ? { label: 'Assurance en attente', severity: 'warning' }
        : { label: 'Assurance', severity: 'info' };
};

const canModify = (row) => (Number(row.montant) === Number(row.reste)) && !row.isRegle;
const canPreview = (row) => !(Number(row.montant) === 0 && Number(row.reste) === 0);
const targetIsFree = (row) => !row.isRegle && Number(row.reste) === 0;

const handlePay = (row) => emit('pay', row);
const handleValidate = (row) => emit('validate-free', row);
const handleModify = (row) => emit('modify', row);
const handlePreview = (row) => emit('preview', row);

const detailRowClass = (row) => row?.detailType === 'consultation_ticket' ? 'ticket-row' : '';

const isInvoiceExpanded = (invoice) => expandedInvoiceCards.value[String(invoice?.id)] === true;

const toggleInvoiceExpansion = (invoice) => {
    const key = String(invoice?.id);
    if (!key) return;

    expandedInvoiceCards.value = {
        ...expandedInvoiceCards.value,
        [key]: !expandedInvoiceCards.value[key]
    };
};

const printDetailPayment = (row) => {
    if (!row?.pId) return;

    if (row?.detailType === 'consultation_ticket') {
        emit('print-receipt', row);
        return;
    }

    emit('print-payment', row);
};

</script>

<template>
    <div class="flex flex-col gap-5">
        <!-- Barre supérieure avec SelectButton et bouton Stats -->
        <div class="top-bar">
            <div class="display-mode-selector">
                <span class="label">Mode d'affichage</span>
                <SelectButton v-model="overviewDisplayMode" :options="overviewDisplayOptions" optionLabel="label"
                    optionValue="value" />
            </div>
            <div class="top-bar-actions">
                <div class="top-bar-metric">
                    <span class="top-bar-metric__label">Recette totale</span>
                    <strong class="top-bar-metric__value">{{ totalRevenueLabel }}</strong>
                </div>
                <Button label="Statistiques" icon="pi pi-chart-bar" severity="secondary" outlined
                    @click="showStatsModal = true" />
            </div>
        </div>

        <!-- Modal statistiques détaillées -->
        <Dialog v-model:visible="showStatsModal" header="Statistiques détaillées" :modal="true"
            :style="{ width: '600px' }" class="stats-dialog">
            <div class="stats-dashboard">

                <!-- KPI principaux -->
                <div class="stats-kpis">
                    <div class="kpi-card">
                        <span>Total factures</span>
                        <strong>{{ detailedStats.totalInvoices }}</strong>
                    </div>
                    <div class="kpi-card success">
                        <span>Recette totale</span>
                        <strong>{{ formatFcfa(detailedStats.totalPaid) }}</strong>
                    </div>
                    <div class="kpi-card danger">
                        <span>Restant</span>
                        <strong>{{ formatFcfa(detailedStats.totalUnpaid) }}</strong>
                    </div>
                </div>

                <!-- Statuts -->
                <div class="stats-section">
                    <h4>Répartition des factures</h4>
                    <div class="status-grid">
                        <div class="status-item paid">Payé: {{ detailedStats.statusCounts.paid }}</div>
                        <div class="status-item partial">Partiel: {{ detailedStats.statusCounts.partial }}</div>
                        <div class="status-item unpaid">Impayé: {{ detailedStats.statusCounts.unpaid }}</div>
                        <div class="status-item free">Gratuit: {{ detailedStats.statusCounts.freeNotValidated }}</div>
                    </div>
                </div>

                <!-- Paiements -->
                <div class="stats-section">
                    <h4>Paiements</h4>
                    <div class="stats-grid">
                        <div>Total: {{ detailedStats.totalPaymentsCount }}</div>
                        <div>Montant: {{ formatFcfa(detailedStats.totalPaymentsAmount) }}</div>
                        <div>Assurances: {{ formatFcfa(detailedStats.totalInsurance) }}</div>
                        <div>En attente: {{ detailedStats.pendingInsurance }}</div>
                    </div>
                </div>

                <div class="stats-section">
                    <h4>Recette par mode de paiement</h4>
                    <div v-if="detailedStats.paymentModeRows.length" class="payment-breakdown-grid">
                        <div v-for="item in detailedStats.paymentModeRows" :key="item.mode" class="payment-breakdown-card">
                            <span class="payment-breakdown-card__mode">{{ item.mode }}</span>
                            <strong class="payment-breakdown-card__amount">{{ formatFcfa(item.amount) }}</strong>
                        </div>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-surface-300 px-4 py-5 text-sm text-surface-500 dark:border-surface-600 dark:text-surface-400">
                        Aucun paiement enregistré sur la période.
                    </div>
                </div>

            </div>
        </Dialog>

        <!-- Section factures -->
        <div class="section-card" data-tour="caisse-overview.factures">
            <!-- En-tête simplifié en mode regroupé -->
            <div class="section-header" :class="{ 'simplified-header': overviewDisplayMode === 'grouped' }">
                <div>
                    <p class="section-eyebrow">Factures</p>
                    <p class="section-title">
                        {{ overviewDisplayMode === 'grouped' ? 'Vue détaillée avec paiements associés' : 'Filtrez, réglez ou modifiez une facture' }}
                    </p>
                </div>
                <div class="filters" :class="{ 'simplified-filters': overviewDisplayMode === 'grouped' }">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="factureSearch" placeholder="Patient, téléphone, montant..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Affichage</label>
                        <Select v-model="factureTypeModel" :options="factureTypeOptions" optionLabel="label"
                            optionValue="value" />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <DatePicker v-model="factureRangeModel" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            fluid />
                    </div>
                    <Button label="Rafraîchir" icon="pi pi-refresh" text @click="emit('refresh-factures')" />
                </div>
            </div>

            <!-- Vue standard -->
            <DataTable v-if="overviewDisplayMode === 'standard'" class="rounded-xl overflow-hidden"
                :value="filteredFactures" dataKey="id" :loading="facturesLoading" paginator :rows="10"
                :rowsPerPageOptions="[5, 10, 20]" responsiveLayout="scroll">
                <Column field="date" header="Date" sortable>
                    <template #body="{ data }">{{ formatDate(data.date) }}</template>
                </Column>
                <Column header="Patient" sortable>
                    <template #body="{ data }">
                        {{ (data.patient && `${data.patient.nom || ''} ${data.patient.prenom || ''}`.trim()) ||
                            data.patient || '—' }}
                    </template>
                </Column>
                <Column field="telephone" header="Téléphone" sortable>
                    <template #body="{ data }">{{ displayPhone(data.telephone) }}</template>
                </Column>
                <Column field="montant" header="Montant" sortable>
                    <template #body="{ data }">{{ formatFcfa(data.montant) }}</template>
                </Column>
                <Column field="reste" header="Reste" sortable>
                    <template #body="{ data }">{{ formatFcfa(data.reste) }}</template>
                </Column>
                <Column header="Statut">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Tag :value="computeStatus(data).label" :severity="computeStatus(data).severity" />
                            <Tag v-if="computeInsuranceBadge(data)" :value="computeInsuranceBadge(data).label"
                                :severity="computeInsuranceBadge(data).severity" icon="pi pi-shield" />
                        </div>
                    </template>
                </Column>
                <Column header="Actions" style="width: 240px">
                    <template #body="{ data }">
                        <div class="flex gap-2 flex-wrap">
                            <Button v-if="!data.isRegle" :label="targetIsFree(data) ? 'Valider' : 'Régler'" size="small"
                                :severity="targetIsFree(data) ? 'secondary' : 'success'" icon="pi pi-wallet"
                                @click="targetIsFree(data) ? handleValidate(data) : handlePay(data)" />
                            <Button v-if="canModify(data)" size="small" severity="secondary" icon="pi pi-pencil"
                                @click="handleModify(data)" />
                            <Button v-if="canPreview(data)" size="small" icon="pi pi-eye" severity="info"
                                class="p-button-outlined" @click="handlePreview(data)" />
                            <Button v-if="canPreview(data)" size="small" icon="pi pi-send" severity="help"
                                @click="emit('send-invoice-sms', data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Vue regroupée améliorée -->
            <DataView v-else class="grouped-invoices-view" :value="filteredFacturesR" :loading="facturesLoading" paginator
                :rows="10" :rowsPerPageOptions="[5, 10, 20]">
                <template #list="slotProps">
                    <div class="flex flex-col gap-4 p-2">
                        <article v-for="invoice in slotProps.items" :key="invoice.id"
                            class="inv-card"
                            :class="`inv-card--${computeStatus(invoice).severity}`">

                            <!-- ── DOCUMENT HEADER ── -->
                            <div class="inv-doc-header">
                                <div class="inv-doc-badge">
                                    <i class="pi pi-file-invoice"></i>
                                    <span>FACTURE</span>
                                </div>
                                <span class="inv-doc-id">#{{ invoice.id }}</span>
                            </div>

                            <!-- ── CORPS ── -->
                            <div class="inv-body">
                                <!-- Info patient -->
                                <div class="inv-patient-block">
                                    <p class="inv-patient-name">
                                        {{ (invoice.patient && typeof invoice.patient === 'object'
                                            ? `${invoice.patient.nom || ''} ${invoice.patient.prenom || ''}`.trim()
                                            : invoice.patient) || '—' }}
                                    </p>
                                    <div class="inv-patient-meta">
                                        <span><i class="pi pi-phone"></i> {{ displayPhone(invoice.telephone) }}</span>
                                        <span><i class="pi pi-calendar"></i> {{ formatDate(invoice.date) }}</span>
                                    </div>
                                    <div class="inv-tags">
                                        <Tag :value="computeStatus(invoice).label" :severity="computeStatus(invoice).severity" />
                                        <Tag v-if="computeInsuranceBadge(invoice)"
                                            :value="computeInsuranceBadge(invoice).label"
                                            :severity="computeInsuranceBadge(invoice).severity"
                                            icon="pi pi-shield" />
                                    </div>
                                </div>

                                <!-- Montants -->
                                <div class="inv-amounts-block">
                                    <div class="inv-amount-row">
                                        <span class="inv-amount-label">Total facture</span>
                                        <span class="inv-amount-value">{{ formatFcfa(invoice.montant) }}</span>
                                    </div>
                                    <div class="inv-amount-row inv-amount-row--reste">
                                        <span class="inv-amount-label">Reste à payer</span>
                                        <span class="inv-amount-value inv-amount-reste"
                                            :class="`inv-amount-reste--${computeStatus(invoice).severity}`">
                                            {{ formatFcfa(invoice.reste) }}
                                        </span>
                                    </div>
                                    <div class="inv-payment-count">
                                        <i class="pi pi-history"></i>
                                        {{ invoice.detailCount || 0 }} paiement(s) enregistré(s)
                                    </div>
                                </div>
                            </div>

                            <!-- ── ACTIONS ── -->
                            <div class="inv-actions">
                                <Button v-if="!invoice.isRegle"
                                    :label="targetIsFree(invoice) ? 'Valider' : 'Régler'"
                                    size="small"
                                    :severity="targetIsFree(invoice) ? 'secondary' : 'success'"
                                    icon="pi pi-wallet"
                                    @click="targetIsFree(invoice) ? handleValidate(invoice) : handlePay(invoice)" />
                                <Button v-if="canModify(invoice)" label="Modifier" size="small" severity="secondary"
                                    icon="pi pi-pencil" @click="handleModify(invoice)" />
                                <Button v-if="canPreview(invoice)" label="Voir" size="small" icon="pi pi-eye"
                                    severity="info" outlined @click="handlePreview(invoice)" />
                                <Button v-if="canPreview(invoice)" icon="pi pi-send" size="small" severity="help"
                                    text @click="emit('send-invoice-sms', invoice)" />
                                <Button size="small" text
                                    :label="isInvoiceExpanded(invoice) ? 'Masquer paiements' : 'Voir paiements'"
                                    :icon="isInvoiceExpanded(invoice) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                                    :badge="invoice.detailCount > 0 ? String(invoice.detailCount) : undefined"
                                    badgeSeverity="info"
                                    @click="toggleInvoiceExpansion(invoice)" />
                            </div>

                            <!-- ── PAIEMENTS LIÉS (dépliants) ── -->
                            <transition name="fade-slide">
                                <div v-if="isInvoiceExpanded(invoice)" class="inv-payments-section">
                                    <div class="inv-payments-header">
                                        <i class="pi pi-history"></i>
                                        <span>Paiements liés à cette facture</span>
                                    </div>
                                    <div v-if="!invoice.detailRows?.length" class="inv-payments-empty">
                                        <i class="pi pi-inbox"></i>
                                        Aucun paiement enregistré pour cette facture.
                                    </div>
                                    <div v-else class="inv-payments-list">
                                        <div v-for="(detail, idx) in invoice.detailRows"
                                            :key="`${invoice.id}-${detail.pId || idx}`"
                                            class="inv-payment-row"
                                            :class="detail.detailType === 'consultation_ticket' ? 'inv-payment-row--ticket' : 'inv-payment-row--facture'">
                                            <div class="inv-payment-icon-wrap">
                                                <i :class="detail.detailType === 'consultation_ticket' ? 'pi pi-ticket' : 'pi pi-wallet'"></i>
                                            </div>
                                            <div class="inv-payment-info">
                                                <span class="inv-payment-type">{{ detail.detailLabel }}</span>
                                                <span class="inv-payment-meta">
                                                    {{ formatDate(detail.date, true) }}
                                                    <span v-if="detail.mode" class="inv-payment-mode">· {{ detail.mode }}</span>
                                                </span>
                                            </div>
                                            <div class="inv-payment-right">
                                                <strong class="inv-payment-amount">{{ formatFcfa(detail.montant) }}</strong>
                                                <Button icon="pi pi-print" size="small" text rounded
                                                    @click="printDetailPayment(detail)" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </transition>
                        </article>
                    </div>
                </template>
            </DataView>
        </div>

        <!-- Section paiements - masquée en mode regroupé -->
        <div v-if="overviewDisplayMode !== 'grouped'" class="section-card" data-tour="caisse-overview.payments">
            <div class="section-header">
                <div>
                    <p class="section-eyebrow text-success">Paiements enregistrés</p>
                    <p class="section-title">Consultez les encaissements et imprimez un récapitulatif.</p>
                </div>
                <div class="filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="paymentsSearch" placeholder="Tapez quelque chose..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <DatePicker v-model="paymentRangeModel" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            fluid />
                    </div>
                    <Button label="Imprimer la période" icon="pi pi-print" severity="primary"
                        @click="emit('print-payments')" />
                    <Button label="Rafraîchir" icon="pi pi-refresh" text @click="emit('refresh-payments')" />
                </div>
            </div>

            <DataTable class="rounded-xl overflow-hidden" :value="filteredPayments" dataKey="pId"
                :loading="paymentsLoading" paginator :rows="10" :rowsPerPageOptions="[5, 10, 20]"
                responsiveLayout="scroll">
                <Column field="date" header="Date" sortable>
                    <template #body="{ data }">{{ formatDate(data.date, true) }}</template>
                </Column>
                <Column field="patient" header="Patient" sortable></Column>
                <Column field="telephone" header="Téléphone" sortable>
                    <template #body="{ data }">{{ displayPhone(data.telephone) }}</template>
                </Column>
                <Column field="montant" header="Montant" sortable>
                    <template #body="{ data }">{{ formatFcfa(data.montant) }}</template>
                </Column>
                <Column field="mode" header="Mode" sortable>
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Tag :value="computePaymentModeTag(data).label"
                                :severity="computePaymentModeTag(data).severity" />
                            <Tag v-if="data.insuranceStatus === 'pending'" value="Assurance en attente" severity="warning" />
                        </div>
                    </template>
                </Column>
                <Column header="Actions" style="width: 140px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button :icon="data.type === 'facture' ? 'pi pi-print' : 'pi pi-ticket'" text
                                @click="emit(data.type === 'facture' ? 'print-payment' : 'print-receipt', data)" />
                            <Button icon="pi pi-send" text @click="emit('send-receipt-sms', data)" />
                        </div>
                    </template>
                </Column>
                <template #paginatorend>
                    <div class="payment-paginator-filters">
                        <Select v-model="paymentModeFilter" :options="paymentModeOptions" optionLabel="label"
                            optionValue="value" />
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

<style scoped>
/* Barre supérieure */
.top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    background: var(--surface-card);
    padding: 0.75rem 1rem;
    border-radius: 14px;
    border: 1px solid var(--surface-border);
}

.display-mode-selector {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.display-mode-selector .label {
    font-weight: 500;
    color: var(--text-color-secondary);
}

.top-bar-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    justify-content: flex-end;
}

.top-bar-metric {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    padding: 0.65rem 0.9rem;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.16), rgba(14, 165, 233, 0.1));
    border: 1px solid rgba(16, 185, 129, 0.18);
}

.top-bar-metric__label {
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-color-secondary);
}

.top-bar-metric__value {
    font-size: 1rem;
    color: var(--text-color);
}

.payment-breakdown-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.85rem;
}

.payment-breakdown-card {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
    padding: 0.95rem 1rem;
    border-radius: 14px;
    border: 1px solid var(--surface-border);
    background: color-mix(in srgb, var(--surface-card) 88%, #14b8a6 12%);
}

.payment-breakdown-card__mode {
    font-size: 0.83rem;
    color: var(--text-color-secondary);
}

.payment-breakdown-card__amount {
    font-size: 1.1rem;
    color: var(--text-color);
}

/* Section cards */
.section-card {
    background: var(--surface-card);
    border-radius: 14px;
    border: 1px solid var(--surface-border);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
    overflow: hidden;
}

.section-header {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: space-between;
    align-items: flex-start;
    padding: 1rem 1rem 0.95rem;
    background: linear-gradient(135deg, rgba(226, 232, 240, 0.9), rgba(203, 213, 225, 0.72));
    border-bottom: 1px solid var(--surface-border);
}

.simplified-header {
    background: linear-gradient(135deg, rgba(220, 240, 220, 0.85), rgba(200, 220, 200, 0.7));
}

.section-eyebrow {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
}

.section-title {
    color: #020617;
    font-weight: 600;
}

.filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    align-items: flex-end;
}

.simplified-filters {
    opacity: 0.9;
}

.filter-item {
    min-width: 200px;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.filter-item label {
    font-size: 0.85rem;
    color: #475569;
}

/* Mode regroupé */
.grouped-invoices-view {
    padding: 0.5rem;
}

.invoice-card {
    border: 2px solid transparent;
    border-left: 6px solid var(--primary-color);
    border-radius: 18px;
    background: var(--surface-card);
    padding: 1.2rem;
    transition: all 0.25s ease;
    position: relative;
}

.invoice-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.12);
    border-left-color: var(--primary-500);
}

/* NOM PATIENT = HERO */
.invoice-patient {
    margin: 0;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--primary-color);
}

/* Facture secondaire */
.invoice-number {
    margin: 0;
    font-size: 0.8rem;
    color: #64748b;
}

/* Sub info */
.invoice-subline {
    font-size: 0.8rem;
    color: #94a3b8;
}

.invoice-card-header {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
    flex-wrap: wrap;
}

.invoice-main {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.invoice-topline {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.6rem;
}


.invoice-amounts {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.3rem;
    font-size: 0.85rem;
}

.invoice-amounts p {
    margin: 0;
}

.invoice-amounts.highlight {
    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
    padding: 0.6rem 0.8rem;
    border-radius: 12px;
    min-width: 140px;
}

.invoice-amounts strong {
    font-size: 1rem;
    color: #065f46;
}

.app-dark .invoice-amounts.highlight {
    background: linear-gradient(135deg, #064e3b, #064e3b);
}

.app-dark .invoice-amounts strong {
    color: #ecfdf5;
}

.invoice-amounts span {
    color: #64748b;
}

.invoice-actions {
    margin-top: 0.9rem;
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    border-top: 1px solid var(--surface-border);
    padding-top: 0.75rem;
}

.invoice-details {
    margin-top: 0.8rem;
    border-top: 1px dashed var(--surface-border);
    padding-top: 0.7rem;
}

.detail-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
    border-radius: 12px;
    background: color-mix(in srgb, var(--surface-card) 95%, var(--surface-border) 5%);
    padding: 0.6rem 0.8rem;
}

.detail-row {
    border-left: 4px solid transparent;
}

.detail-row:not(.ticket-row) {
    border-left-color: #10b981;
}

.ticket-row {
    border-left-color: #f59e0b;
}

.detail-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    align-items: center;
    font-size: 0.85rem;
}

.detail-date,
.detail-mode {
    color: #64748b;
}

.detail-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ticket-row {
    background: rgba(245, 158, 11, 0.1);
    border-left: 3px solid #f59e0b;
}

/* Filtres pagination paiements */
.payment-paginator-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-left: 0.75rem;
}

/* Transitions */
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: all 0.2s ease;
}

.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

/* Modal stats */
.stats-dialog :deep(.p-dialog-content) {
    padding: 1rem 1.5rem;
}

.stats-content {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.stats-section h4 {
    margin: 0 0 0.75rem 0;
    font-size: 1.1rem;
    font-weight: 600;
    border-left: 4px solid var(--primary-color);
    padding-left: 0.75rem;
}

.stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.5rem 1rem;
    margin-bottom: 1rem;
}

.status-breakdown {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-top: 0.5rem;
}

.stats-dashboard {
    display: flex;
    flex-direction: column;
    gap: 1.2rem;
}

/* KPI cards */
.stats-kpis {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.kpi-card {
    background: #f1f5f9;
    border-radius: 14px;
    padding: 0.8rem;
    display: flex;
    flex-direction: column;
}

.kpi-card span {
    font-size: 0.75rem;
    color: #64748b;
}

.kpi-card strong {
    font-size: 1.1rem;
}

.kpi-card.success {
    background: #ecfdf5;
    color: #065f46;
}

.kpi-card.danger {
    background: #fef2f2;
    color: #7f1d1d;
}

.app-dark .kpi-card {
    background: #1e293b;
}

.app-dark .kpi-card span {
    color: #dceafd;
}

.app-dark .kpi-card.success {
    background: #064e3b;
    color: #ecfdf5;
}

.app-dark .kpi-card.danger {
    background: #7f1d1d;
    color: #fef2f2;
}

/* Status */
.status-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.5rem;
}

.status-item {
    padding: 0.5rem;
    border-radius: 10px;
    font-size: 0.85rem;
}

.status-item.paid { background: #dcfce7; }
.status-item.partial { background: #fef9c3; }
.status-item.unpaid { background: #fee2e2; }
.status-item.free { background: #e2e8f0; }

.app-dark .status-item.paid { background: #064e3b; }
.app-dark .status-item.partial { background: #f59e0b; }
.app-dark .status-item.unpaid { background: #7f1d1d; }
.app-dark .status-item.free { background: #1e293b; }

/* Dark mode */
.app-dark .section-header {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.96), rgba(15, 23, 42, 0.88));
}

.app-dark .section-eyebrow {
    color: #9ca3af;
}

.app-dark .section-title {
    color: #e2e8f0;
}

.app-dark .filter-item label {
    color: #94a3b8;
}

.app-dark .invoice-number {
    color: #e2e8f0;
}

.app-dark .invoice-subline,
.app-dark .invoice-amounts span,
.app-dark .detail-date,
.app-dark .detail-mode {
    color: #94a3b8;
}

@media (max-width: 900px) {
    .invoice-card-header {
        flex-direction: column;
    }

    .invoice-amounts {
        align-items: flex-start;
    }

    .top-bar {
        flex-direction: column;
        align-items: stretch;
    }

    .display-mode-selector {
        justify-content: space-between;
    }
}

/* ────────────────────────────────────────────────────────────
   NOUVELLE VUE REGROUPÉE — cartes facture + paiements liés
   ──────────────────────────────────────────────────────────── */

/* Carte principale */
.inv-card {
    background: var(--surface-card);
    border-radius: 16px;
    border: 1px solid var(--surface-border);
    border-left: 5px solid #94a3b8;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.inv-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

/* Couleur de la bordure gauche selon statut */
.inv-card--success  { border-left-color: #22c55e; }
.inv-card--danger   { border-left-color: #ef4444; }
.inv-card--warning  { border-left-color: #f59e0b; }
.inv-card--secondary { border-left-color: #94a3b8; }

/* En-tête document */
.inv-doc-header {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    padding: 0.55rem 1rem;
    background: linear-gradient(90deg, rgba(241, 245, 249, 0.95), rgba(248, 250, 252, 0.8));
    border-bottom: 1px solid var(--surface-border);
}

.inv-doc-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #475569;
    background: #e2e8f0;
    border-radius: 6px;
    padding: 0.2rem 0.55rem;
}

.inv-doc-badge .pi { font-size: 0.8rem; }

.inv-doc-id {
    font-size: 0.82rem;
    font-weight: 600;
    color: #94a3b8;
    margin-left: auto;
}

/* Corps de la carte */
.inv-body {
    display: flex;
    gap: 1rem;
    padding: 0.9rem 1rem;
    flex-wrap: wrap;
    align-items: flex-start;
}

/* Bloc patient */
.inv-patient-block {
    flex: 1 1 220px;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.inv-patient-name {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: var(--text-color);
}

.inv-patient-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    font-size: 0.82rem;
    color: #64748b;
}

.inv-patient-meta .pi {
    font-size: 0.78rem;
    margin-right: 0.25rem;
}

.inv-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-top: 0.2rem;
}

/* Bloc montants */
.inv-amounts-block {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 170px;
    background: rgba(241, 245, 249, 0.7);
    border-radius: 12px;
    padding: 0.65rem 0.9rem;
    align-self: flex-start;
}

.inv-amount-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.75rem;
}

.inv-amount-row--reste {
    margin-top: 0.1rem;
    padding-top: 0.3rem;
    border-top: 1px dashed #cbd5e1;
}

.inv-amount-label {
    font-size: 0.78rem;
    color: #64748b;
    white-space: nowrap;
}

.inv-amount-value {
    font-size: 0.95rem;
    font-weight: 700;
    color: #0f172a;
}

.inv-amount-reste--success  { color: #16a34a; }
.inv-amount-reste--danger   { color: #dc2626; }
.inv-amount-reste--warning  { color: #d97706; }
.inv-amount-reste--secondary { color: #64748b; }

.inv-payment-count {
    margin-top: 0.4rem;
    font-size: 0.78rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.inv-payment-count .pi { font-size: 0.78rem; }

/* Barre d'actions */
.inv-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    padding: 0.6rem 1rem;
    border-top: 1px solid var(--surface-border);
    background: rgba(248, 250, 252, 0.6);
}

/* Section paiements dépliants */
.inv-payments-section {
    border-top: 2px dashed var(--surface-border);
    padding: 0.85rem 1rem;
    background: rgba(241, 245, 249, 0.45);
}

.inv-payments-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.82rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: #475569;
    margin-bottom: 0.7rem;
}

.inv-payments-header .pi { color: #6366f1; }

.inv-payments-empty {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #94a3b8;
    padding: 0.5rem 0;
}

.inv-payments-list {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

/* Ligne de paiement */
.inv-payment-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.55rem 0.75rem;
    border-radius: 10px;
    background: var(--surface-card);
    border: 1px solid var(--surface-border);
    border-left: 4px solid transparent;
}

.inv-payment-row--facture { border-left-color: #10b981; }
.inv-payment-row--ticket  { border-left-color: #f59e0b; background: rgba(245, 158, 11, 0.05); }

.inv-payment-icon-wrap {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.inv-payment-row--facture .inv-payment-icon-wrap {
    background: #dcfce7;
    color: #16a34a;
}

.inv-payment-row--ticket .inv-payment-icon-wrap {
    background: #fef3c7;
    color: #d97706;
}

.inv-payment-icon-wrap .pi { font-size: 0.9rem; }

.inv-payment-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.inv-payment-type {
    font-size: 0.83rem;
    font-weight: 600;
    color: var(--text-color);
}

.inv-payment-meta {
    font-size: 0.78rem;
    color: #64748b;
}

.inv-payment-mode {
    color: #94a3b8;
}

.inv-payment-right {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.inv-payment-amount {
    font-size: 0.95rem;
    color: #0f172a;
}

/* Dark mode */
.app-dark .inv-doc-header {
    background: linear-gradient(90deg, rgba(30, 41, 59, 0.9), rgba(15, 23, 42, 0.7));
}

.app-dark .inv-doc-badge {
    background: #334155;
    color: #94a3b8;
}

.app-dark .inv-amounts-block {
    background: rgba(30, 41, 59, 0.6);
}

.app-dark .inv-amount-value { color: #e2e8f0; }
.app-dark .inv-payment-amount { color: #e2e8f0; }
.app-dark .inv-amount-label,
.app-dark .inv-patient-meta,
.app-dark .inv-payment-meta,
.app-dark .inv-payment-count,
.app-dark .inv-doc-id { color: #94a3b8; }

.app-dark .inv-actions {
    background: rgba(15, 23, 42, 0.4);
}

.app-dark .inv-payments-section {
    background: rgba(15, 23, 42, 0.3);
}

.app-dark .inv-payment-row {
    background: rgba(30, 41, 59, 0.5);
}

.app-dark .inv-payment-row--facture .inv-payment-icon-wrap {
    background: #14532d;
    color: #4ade80;
}

.app-dark .inv-payment-row--ticket .inv-payment-icon-wrap {
    background: #78350f;
    color: #fbbf24;
}

.app-dark .inv-payment-row--ticket {
    background: rgba(120, 53, 15, 0.08);
}
</style>
