<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DataView from 'primevue/dataview';
import AppDialog from '@/components/layout/AppDialog.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import SelectButton from 'primevue/selectbutton';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import PageSection from '@/components/layout/PageSection.vue';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { canModifyFacture, canPreviewFacture, canSettleFacture, computeFactureStatus, computePriorReliquat, isCabinetServiceFacture, isInsuranceFactureRow, isValidatedEmptyFacture, targetIsFreeFacture } from '@/utils/factureRow';

const { isInternetFeaturesEnabled } = useInternetFeatures();

const props = defineProps({
    factures: { type: Array, default: () => [] },
    facturesLoading: { type: Boolean, default: false },
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    servicesCabinet: { type: Object, default: () => ({ count: 0, facture: 0, encaisse: 0, reste: 0 }) },
    factureType: { type: String, default: 'all' },
    factureRange: { type: Array, default: () => [] },
    paymentRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false },
    allowInvoiceModification: { type: Boolean, default: false }
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
    { label: 'Impayées (période)', value: 'impaye' },
    { label: 'Toutes les impayées', value: 'impaye_toutes' }
];

const periodFilterDisabled = computed(() => props.factureType === 'impaye_toutes');

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
const factureOrigin = ref('all');
const factureOriginOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Consultations', value: 'consultation' },
    { label: 'Services cabinet', value: 'service_cabinet' }
];
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

const normalizeText = (value) =>
    String(value ?? '')
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
    if (payment?.type === 'facture_assurance') return true;
    const role = String(payment?.rolePaiement || payment?.role || '').toLowerCase();
    return role === 'patient_insurance';
};

const formatPatientName = (row) => {
    if (!row) return '—';
    if (row.patient && typeof row.patient === 'object') {
        return `${row.patient.nom || ''} ${row.patient.prenom || ''}`.trim() || '—';
    }
    if (typeof row.patient === 'string' && row.patient.trim()) {
        return row.patient.trim();
    }
    return '—';
};

const priorReliquatAmount = (row) => computePriorReliquat(row);

const isInvoiceStylePayment = (payment) => ['devis', 'facture', 'facture_assurance', 'service_cabinet'].includes(payment?.type);
const isCabinetRow = (row) => isCabinetServiceFacture(row);
const documentLabel = (row) => (isCabinetRow(row) ? 'SERVICE CABINET' : isInsuranceRow(row) ? 'FACTURE ASSURANCE' : 'FACTURE');
const matchesOrigin = (row) => {
    if (factureOrigin.value === 'service_cabinet') return isCabinetRow(row);
    if (factureOrigin.value === 'consultation') return !isCabinetRow(row);
    return true;
};
const paymentMatchesInvoice = (payment, invoice) => {
    if (Number(payment?.factureId) !== Number(invoice?.id)) return false;
    if (isCabinetRow(invoice)) return payment?.type === 'service_cabinet';
    if (isInsuranceRow(invoice)) return payment?.type === 'facture_assurance';
    return payment?.type === 'facture' || payment?.type === 'devis';
};

const filteredFactures = computed(() => {
    const list = Array.isArray(props.factures) ? props.factures : [];
    const query = factureSearchQuery.value;
    return list
        .filter((row) => {
            if (!matchesOrigin(row)) return false;
            const patient = row.patient && typeof row.patient === 'object' ? `${row.patient.nom || ''} ${row.patient.prenom || ''}`.trim() : row.patient || '';
            const status = computeStatus(row).label;
            return matchesQuery([patient, row.telephone, row.date, formatDate(row.date), row.montant, row.reste, status], query);
        })
        .map((row) => ({ ...row, rowKey: `${row.type || row.kind || 'facture'}-${row.id}` }));
});

const filteredFacturesR = computed(() => {
    const rows = filteredFactures.value;
    const payments = Array.isArray(props.payments) ? props.payments : [];

    return rows.map((invoice) => {
        const consultationId = Number(invoice?.consultation);

        const invoicePayments = payments
            .filter((payment) => paymentMatchesInvoice(payment, invoice))
            .map((payment) => ({
                ...payment,
                detailType: payment?.type === 'service_cabinet' ? 'cabinet_payment' : payment?.type === 'facture_assurance' ? 'assurance_payment' : 'facture_payment',
                detailLabel: payment?.type === 'service_cabinet' ? 'Paiement service cabinet' : payment?.type === 'facture_assurance' ? 'Paiement assurance' : 'Paiement facture'
            }));

        const consultationTicket = consultationId > 0 ? payments.find((payment) => payment?.type === 'ticket' && Number(payment?.consultationId) === consultationId) : null;

        const detailRows = [
            ...invoicePayments,
            ...(consultationTicket
                ? [
                      {
                          ...consultationTicket,
                          detailType: 'consultation_ticket',
                          detailLabel: 'Ticket consultation'
                      }
                  ]
                : [])
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
    const options = (Array.isArray(props.payments) ? props.payments : []).reduce(
        (acc, payment) => {
            const modeId = Number(payment?.modeId);
            if (!Number.isFinite(modeId) || modeId <= 0) {
                return acc;
            }

            if (!acc.some((option) => option.value === modeId)) {
                acc.push({ label: payment?.mode || 'Autre', value: modeId });
            }

            return acc;
        },
        [{ label: 'Tous les modes', value: 'all' }]
    );

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

        return matchesQuery([row.patient, row.telephone, row.date, formatDate(row.date, true), row.montant, row.mode, row.type, row.insuranceStatus, computePaymentModeTag(row).label], query);
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
        freeNotValidated: 0,
        validatedEmpty: 0
    };
    allInvoices.forEach((inv) => {
        const reste = Number(inv.reste) || 0;
        const montant = Number(inv.montant) || 0;
        if (isValidatedEmptyFacture(inv) || inv.insuranceStatus === 'validated_empty') {
            statusCounts.validatedEmpty++;
        } else if (inv.isRegle && reste === 0) statusCounts.paid++;
        else if (!inv.isRegle && reste === 0) statusCounts.freeNotValidated++;
        else if (reste === montant) statusCounts.unpaid++;
        else statusCounts.partial++;
    });

    const paymentModeBreakdown = {};
    allPayments.forEach((p) => {
        const mode = p.mode || 'Autre';
        paymentModeBreakdown[mode] = (paymentModeBreakdown[mode] || 0) + (Number(p.montant) || 0);
    });

    const insurancePayments = allPayments.filter((payment) => isInsurancePayment(payment));
    const totalInsurance = insurancePayments.reduce((sum, p) => sum + (Number(p.montant) || 0), 0);
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
        totalPaymentsCount: allPayments.length,
        totalPaymentsAmount: totalRevenue
    };
});

const totalRevenueLabel = computed(() => formatFcfa(detailedStats.value.totalPaid));
const servicesCabinetStats = computed(() => ({
    count: Number(props.servicesCabinet?.count) || 0,
    facture: Number(props.servicesCabinet?.facture) || 0,
    encaisse: Number(props.servicesCabinet?.encaisse) || 0,
    reste: Number(props.servicesCabinet?.reste) || 0
}));

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const formatDate = (value, withTime = false) => {
    if (!value) return '—';
    const date = new Date(value);
    const datePart = date.toLocaleDateString('fr-FR');
    if (!withTime) return datePart;
    const timePart = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    return `${datePart} ${timePart}`;
};

const displayPhone = (value) => (props.hidePatientPhone ? "Masqué par l'administrateur" : value || '—');

const computeStatus = (row) => computeFactureStatus(row);

const isInsuranceRow = (row) => isInsuranceFactureRow(row);

const computeInsuranceBadge = (row) => {
    if (!isInsuranceRow(row)) {
        return null;
    }

    const nom = row?.insurance?.assuranceNom;
    return { label: nom ? `${nom}` : 'Assurance', severity: 'info' };
};

const canModify = (row) => canModifyFacture(row, { allowInvoiceModification: props.allowInvoiceModification });
const canSettle = (row) => canSettleFacture(row);
const canPreview = (row) => canPreviewFacture(row);
const targetIsFree = (row) => targetIsFreeFacture(row);

const handlePay = (row) => emit('pay', row);
const handleValidate = (row) => emit('validate-free', row);
const handleModify = (row) => emit('modify', row);
const handlePreview = (row) => emit('preview', row);

const detailRowClass = (row) => (row?.detailType === 'consultation_ticket' ? 'ticket-row' : '');

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
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3" data-tour="caisse-overview.toolbar">
            <SelectButton v-model="overviewDisplayMode" :options="overviewDisplayOptions" optionLabel="label" optionValue="value" />
            <Button label="Statistiques" icon="pi pi-chart-bar" severity="secondary" outlined @click="showStatsModal = true" />
        </div>

        <div class="page-kpi-grid" data-tour="caisse-overview.stats">
            <article class="page-kpi-card border-primary-200/70 bg-gradient-to-br from-primary-50/80 to-primary-100/50 dark:border-primary-800/40 dark:from-primary-900/30 dark:to-primary-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-primary-700 dark:text-primary-300">Factures</p>
                    <p class="page-kpi-value text-primary-900 dark:text-primary-100">{{ detailedStats.totalInvoices }}</p>
                </div>
                <i class="pi pi-file page-kpi-icon text-primary-500"></i>
            </article>
            <article class="page-kpi-card border-emerald-200/70 bg-gradient-to-br from-emerald-50/80 to-emerald-100/50 dark:border-emerald-800/40 dark:from-emerald-900/20 dark:to-emerald-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Encaissements</p>
                    <p class="page-kpi-value truncate text-emerald-900 dark:text-emerald-100">{{ totalRevenueLabel }}</p>
                    <p v-if="servicesCabinetStats.encaisse > 0" class="mt-1 truncate text-xs text-emerald-600/70 dark:text-emerald-400/70">Services cabinet : {{ formatFcfa(servicesCabinetStats.encaisse) }}</p>
                </div>
                <i class="pi pi-wallet page-kpi-icon text-emerald-500"></i>
            </article>
            <article class="page-kpi-card border-rose-200/70 bg-gradient-to-br from-rose-50/80 to-rose-100/50 dark:border-rose-800/40 dark:from-rose-900/20 dark:to-rose-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-rose-700 dark:text-rose-300">Reste à encaisser</p>
                    <p class="page-kpi-value truncate text-rose-900 dark:text-rose-100">{{ formatFcfa(detailedStats.totalUnpaid) }}</p>
                </div>
                <i class="pi pi-clock page-kpi-icon text-rose-500"></i>
            </article>
            <article class="page-kpi-card border-amber-200/70 bg-gradient-to-br from-amber-50/80 to-amber-100/50 dark:border-amber-800/40 dark:from-amber-900/20 dark:to-amber-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">Services cabinet</p>
                    <p class="page-kpi-value text-amber-900 dark:text-amber-100">{{ servicesCabinetStats.count }}</p>
                    <p class="mt-1 truncate text-xs text-amber-600/70 dark:text-amber-400/70">Facturé {{ formatFcfa(servicesCabinetStats.facture) }}</p>
                </div>
                <i class="pi pi-building page-kpi-icon text-amber-500"></i>
            </article>
        </div>

        <!-- Modal statistiques détaillées -->
        <AppDialog
            v-model:visible="showStatsModal"
            title="Statistiques détaillées"
            icon="pi pi-chart-bar"
            icon-tone="primary"
            size="md"
            :show-footer="false"
            class="stats-dialog"
        >
            <div class="flex flex-col gap-4">
                <div class="page-kpi-grid page-kpi-grid--compact">
                    <article class="page-kpi-card border-primary-200/70 bg-gradient-to-br from-primary-50/80 to-primary-100/50 dark:border-primary-800/40 dark:from-primary-900/30 dark:to-primary-800/20">
                        <div class="min-w-0 flex-1">
                            <p class="page-kpi-label text-primary-700 dark:text-primary-300">Total factures</p>
                            <p class="page-kpi-value text-primary-900 dark:text-primary-100">{{ detailedStats.totalInvoices }}</p>
                        </div>
                        <i class="pi pi-file page-kpi-icon text-primary-500"></i>
                    </article>
                    <article class="page-kpi-card border-emerald-200/70 bg-gradient-to-br from-emerald-50/80 to-emerald-100/50 dark:border-emerald-800/40 dark:from-emerald-900/20 dark:to-emerald-800/20">
                        <div class="min-w-0 flex-1">
                            <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Encaissements</p>
                            <p class="page-kpi-value truncate text-emerald-900 dark:text-emerald-100">{{ formatFcfa(detailedStats.totalPaid) }}</p>
                        </div>
                        <i class="pi pi-wallet page-kpi-icon text-emerald-500"></i>
                    </article>
                    <article class="page-kpi-card border-rose-200/70 bg-gradient-to-br from-rose-50/80 to-rose-100/50 dark:border-rose-800/40 dark:from-rose-900/20 dark:to-rose-800/20">
                        <div class="min-w-0 flex-1">
                            <p class="page-kpi-label text-rose-700 dark:text-rose-300">Restant</p>
                            <p class="page-kpi-value truncate text-rose-900 dark:text-rose-100">{{ formatFcfa(detailedStats.totalUnpaid) }}</p>
                        </div>
                        <i class="pi pi-clock page-kpi-icon text-rose-500"></i>
                    </article>
                </div>

                <section class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                    <h4 class="mb-2 font-semibold text-surface-900 dark:text-surface-100">Services cabinet</h4>
                    <div class="grid grid-cols-2 gap-2">
                        <div class="rounded-lg bg-surface-50 px-3 py-2 text-sm dark:bg-surface-800/60">Nombre : {{ servicesCabinetStats.count }}</div>
                        <div class="rounded-lg bg-surface-50 px-3 py-2 text-sm dark:bg-surface-800/60">Facturé : {{ formatFcfa(servicesCabinetStats.facture) }}</div>
                        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">Encaissé : {{ formatFcfa(servicesCabinetStats.encaisse) }}</div>
                        <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:bg-rose-950/30 dark:text-rose-200">Reste : {{ formatFcfa(servicesCabinetStats.reste) }}</div>
                    </div>
                </section>

                <section class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                    <h4 class="mb-2 font-semibold text-surface-900 dark:text-surface-100">Répartition des factures</h4>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">Payé : {{ detailedStats.statusCounts.paid }}</div>
                        <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">Partiel : {{ detailedStats.statusCounts.partial }}</div>
                        <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:bg-rose-950/30 dark:text-rose-200">Impayé : {{ detailedStats.statusCounts.unpaid }}</div>
                        <div class="rounded-lg bg-surface-100 px-3 py-2 text-sm dark:bg-surface-800">Gratuit : {{ detailedStats.statusCounts.freeNotValidated }}</div>
                        <div class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:bg-sky-950/30 dark:text-sky-200">Validée : {{ detailedStats.statusCounts.validatedEmpty }}</div>
                    </div>
                </section>

                <section class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                    <h4 class="mb-2 font-semibold text-surface-900 dark:text-surface-100">Paiements</h4>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div class="rounded-lg bg-surface-50 px-3 py-2 text-sm dark:bg-surface-800/60">Total : {{ detailedStats.totalPaymentsCount }}</div>
                        <div class="rounded-lg bg-surface-50 px-3 py-2 text-sm dark:bg-surface-800/60">Montant : {{ formatFcfa(detailedStats.totalPaymentsAmount) }}</div>
                        <div class="rounded-lg bg-surface-50 px-3 py-2 text-sm dark:bg-surface-800/60">Parts patient assurance : {{ formatFcfa(detailedStats.totalInsurance) }}</div>
                    </div>
                </section>

                <section class="rounded-xl border border-surface-200 p-3 dark:border-surface-700">
                    <h4 class="mb-2 font-semibold text-surface-900 dark:text-surface-100">Encaissements par mode de paiement</h4>
                    <div v-if="detailedStats.paymentModeRows.length" class="page-kpi-grid page-kpi-grid--compact">
                        <article v-for="item in detailedStats.paymentModeRows" :key="item.mode" class="page-kpi-card">
                            <div class="min-w-0 flex-1">
                                <p class="page-kpi-label">{{ item.mode }}</p>
                                <p class="page-kpi-value truncate">{{ formatFcfa(item.amount) }}</p>
                            </div>
                            <i class="pi pi-credit-card page-kpi-icon text-surface-400"></i>
                        </article>
                    </div>
                    <div v-else class="rounded-xl border border-dashed border-surface-300 px-4 py-5 text-sm text-surface-500 dark:border-surface-600 dark:text-surface-400">Aucun paiement enregistré sur la période.</div>
                </section>
            </div>
        </AppDialog>

        <!-- Section factures -->
        <PageSection
            title="Factures"
            :subtitle="overviewDisplayMode === 'grouped' ? 'Vue détaillée avec paiements associés' : 'Filtrez, réglez ou modifiez une facture'"
            tour-id="caisse-overview.factures"
        >
            <template #headerActions>
                <div class="filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="factureSearch" placeholder="Recherche..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Origine</label>
                        <Select v-model="factureOrigin" :options="factureOriginOptions" optionLabel="label" optionValue="value" placeholder="Origine" fluid />
                    </div>
                    <div class="filter-item">
                        <label>Affichage</label>
                        <Select v-model="factureTypeModel" :options="factureTypeOptions" optionLabel="label" optionValue="value" placeholder="Affichage" fluid />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <PanelDatePicker v-model="factureRangeModel" dateFormat="yy-mm-dd" showIcon fluid placeholder="Période" :disabled="periodFilterDisabled" />
                    </div>
                    <Button icon="pi pi-refresh" text aria-label="Rafraîchir" v-tooltip.top="'Rafraîchir'" class="!px-2" @click="emit('refresh-factures')" />
                </div>
            </template>

            <div class="page-table-scroll">
            <!-- Vue standard -->
            <DataTable v-if="overviewDisplayMode === 'standard'" :value="filteredFactures" dataKey="rowKey" :loading="facturesLoading" paginator :rows="10" :rowsPerPageOptions="[5, 10, 20]" stripedRows responsiveLayout="scroll">
                <Column field="date" header="Date" sortable>
                    <template #body="{ data }">{{ formatDate(data.date) }}</template>
                </Column>
                <Column header="Patient" sortable>
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <span>{{ formatPatientName(data) }}</span>
                            <i
                                v-if="priorReliquatAmount(data) > 0"
                                v-tooltip.top="`Reliquat : ${priorReliquatAmount(data).toLocaleString('fr-FR')} FCFA`"
                                class="pi pi-wallet inv-reliquat-icon text-orange-500 dark:text-orange-400"
                                aria-label="Reliquat patient"
                            ></i>
                        </div>
                    </template>
                </Column>
                <Column field="telephone" header="Téléphone" sortable>
                    <template #body="{ data }">{{ displayPhone(data.telephone) }}</template>
                </Column>
                <Column field="montant" header="Montant" sortable>
                    <template #body="{ data }">
                        <div>
                            <span>{{ formatFcfa(data.montant) }}</span>
                            <p v-if="isInsuranceRow(data)" class="text-xs text-sky-600 mt-0.5">Part patient</p>
                        </div>
                    </template>
                </Column>
                <Column field="reste" header="Reste" sortable>
                    <template #body="{ data }">
                        <div>
                            <span>{{ formatFcfa(data.reste) }}</span>
                            <p v-if="isInsuranceRow(data)" class="text-xs text-sky-600 mt-0.5">Part patient</p>
                        </div>
                    </template>
                </Column>
                <Column header="Statut">
                    <template #body="{ data }">
                        <div class="flex flex-wrap gap-2">
                            <Tag :value="computeStatus(data).label" :severity="computeStatus(data).severity" />
                            <Tag v-if="isCabinetRow(data)" value="Service cabinet" severity="warn" icon="pi pi-building" />
                            <Tag v-if="computeInsuranceBadge(data)" :value="computeInsuranceBadge(data).label" :severity="computeInsuranceBadge(data).severity" icon="pi pi-shield" />
                        </div>
                    </template>
                </Column>
                <Column header="Actions" style="width: 240px">
                    <template #body="{ data }">
                        <div class="flex gap-2 flex-wrap">
                            <Button
                                v-if="canSettle(data)"
                                :label="targetIsFree(data) ? 'Valider' : 'Régler'"
                                size="small"
                                :severity="targetIsFree(data) ? 'secondary' : 'success'"
                                icon="pi pi-wallet"
                                @click="targetIsFree(data) ? handleValidate(data) : handlePay(data)"
                            />
                            <Button v-if="canModify(data)" size="small" severity="secondary" icon="pi pi-pencil" @click="handleModify(data)" />
                            <Button v-if="canPreview(data)" size="small" icon="pi pi-eye" severity="info" class="p-button-outlined" @click="handlePreview(data)" />
                            <Button v-if="canPreview(data) && isInternetFeaturesEnabled" size="small" icon="pi pi-send" severity="help" @click="emit('send-invoice-sms', data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Vue regroupée améliorée -->
            <DataView v-else class="grouped-invoices-view" :value="filteredFacturesR" :loading="facturesLoading" paginator :rows="10" :rowsPerPageOptions="[5, 10, 20]">
                <template #list="slotProps">
                    <div class="flex flex-col gap-4 p-2">
                        <article v-for="invoice in slotProps.items" :key="invoice.rowKey || invoice.id" class="inv-card" :class="`inv-card--${computeStatus(invoice).severity}`">
                            <!-- ── DOCUMENT HEADER ── -->
                            <div class="inv-doc-header">
                                <div class="inv-doc-badge" :class="{ 'inv-doc-badge--insurance': isInsuranceRow(invoice), 'inv-doc-badge--cabinet': isCabinetRow(invoice) }">
                                    <i :class="isCabinetRow(invoice) ? 'pi pi-building' : isInsuranceRow(invoice) ? 'pi pi-shield' : 'pi pi-receipt'"></i>
                                    <span>{{ documentLabel(invoice) }}</span>
                                </div>
                                <span class="inv-doc-id">#{{ invoice.id }}</span>
                            </div>

                            <!-- ── CORPS ── -->
                            <div class="inv-body">
                                <!-- Info patient -->
                                <div class="inv-patient-block">
                                    <p class="inv-patient-name">
                                        <span>{{ formatPatientName(invoice) }}</span>
                                        <i
                                            v-if="priorReliquatAmount(invoice) > 0"
                                            v-tooltip.top="`Reliquat : ${priorReliquatAmount(invoice).toLocaleString('fr-FR')} FCFA`"
                                            class="pi pi-wallet inv-reliquat-icon text-orange-500 dark:text-orange-400"
                                            aria-label="Reliquat patient"
                                        ></i>
                                    </p>
                                    <div class="inv-patient-meta">
                                        <span><i class="pi pi-phone"></i> {{ displayPhone(invoice.telephone) }}</span>
                                        <span><i class="pi pi-calendar"></i> {{ formatDate(invoice.date) }}</span>
                                    </div>
                                    <div class="inv-tags">
                                        <Tag :value="computeStatus(invoice).label" :severity="computeStatus(invoice).severity" />
                                        <Tag v-if="isCabinetRow(invoice)" value="Service cabinet" severity="warn" icon="pi pi-building" />
                                        <Tag v-if="computeInsuranceBadge(invoice)" :value="computeInsuranceBadge(invoice).label" :severity="computeInsuranceBadge(invoice).severity" icon="pi pi-shield" />
                                    </div>
                                </div>

                                <!-- Montants -->
                                <div class="inv-amounts-block">
                                    <div v-if="isInsuranceRow(invoice)" class="inv-amount-row" style="margin-bottom: 0.2rem">
                                        <span class="inv-amount-label">Total facture</span>
                                        <span class="inv-amount-value" style="font-size: 0.82rem; opacity: 0.7">{{ formatFcfa(invoice.insurance?.montantTotal ?? invoice.montantTotal) }}</span>
                                    </div>
                                    <div class="inv-amount-row">
                                        <span class="inv-amount-label">{{ isInsuranceRow(invoice) ? 'Part patient' : 'Total facture' }}</span>
                                        <span class="inv-amount-value">{{ formatFcfa(invoice.montant) }}</span>
                                    </div>
                                    <div class="inv-amount-row inv-amount-row--reste">
                                        <span class="inv-amount-label">Reste à payer</span>
                                        <span class="inv-amount-value inv-amount-reste" :class="`inv-amount-reste--${computeStatus(invoice).severity}`">
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
                                <Button
                                    v-if="canSettle(invoice)"
                                    :label="targetIsFree(invoice) ? 'Valider' : 'Régler'"
                                    size="small"
                                    :severity="targetIsFree(invoice) ? 'secondary' : 'success'"
                                    icon="pi pi-wallet"
                                    @click="targetIsFree(invoice) ? handleValidate(invoice) : handlePay(invoice)"
                                />
                                <Button v-if="canModify(invoice)" label="Modifier" size="small" severity="secondary" icon="pi pi-pencil" @click="handleModify(invoice)" />
                                <Button v-if="canPreview(invoice)" label="Voir" size="small" icon="pi pi-eye" severity="info" outlined @click="handlePreview(invoice)" />
                                <Button v-if="canPreview(invoice) && isInternetFeaturesEnabled" icon="pi pi-send" size="small" severity="help" text @click="emit('send-invoice-sms', invoice)" />
                                <Button
                                    size="small"
                                    text
                                    :label="isInvoiceExpanded(invoice) ? 'Masquer paiements' : 'Voir paiements'"
                                    :icon="isInvoiceExpanded(invoice) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                                    :badge="invoice.detailCount > 0 ? String(invoice.detailCount) : undefined"
                                    badgeSeverity="info"
                                    @click="toggleInvoiceExpansion(invoice)"
                                />
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
                                        <div
                                            v-for="(detail, idx) in invoice.detailRows"
                                            :key="`${invoice.id}-${detail.pId || idx}`"
                                            class="inv-payment-row"
                                            :class="detail.detailType === 'consultation_ticket' ? 'inv-payment-row--ticket' : 'inv-payment-row--facture'"
                                        >
                                            <div class="inv-payment-icon-wrap">
                                                <i :class="detail.detailType === 'consultation_ticket' ? 'pi pi-ticket' : detail.detailType === 'assurance_payment' ? 'pi pi-shield' : 'pi pi-wallet'"></i>
                                            </div>
                                            <div class="inv-payment-info">
                                                <span class="inv-payment-type">
                                                    {{ detail.detailLabel }}
                                                    <Tag v-if="detail.detailType === 'assurance_payment'" value="Assurance" severity="info" icon="pi pi-shield" class="ml-2" />
                                                </span>
                                                <span class="inv-payment-meta">
                                                    {{ formatDate(detail.date, true) }}
                                                    <span v-if="detail.mode" class="inv-payment-mode">· {{ detail.mode }}</span>
                                                </span>
                                            </div>
                                            <div class="inv-payment-right">
                                                <strong class="inv-payment-amount">{{ formatFcfa(detail.montant) }}</strong>
                                                <Button icon="pi pi-print" size="small" text rounded @click="printDetailPayment(detail)" />
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
        </PageSection>

        <!-- Section paiements - masquée en mode regroupé -->
        <PageSection
            v-if="overviewDisplayMode !== 'grouped'"
            title="Paiements enregistrés"
            subtitle="Consultez les encaissements et imprimez un récapitulatif."
            tour-id="caisse-overview.payments"
        >
            <template #headerActions>
                <div class="filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="paymentsSearch" placeholder="Recherche..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <PanelDatePicker v-model="paymentRangeModel" dateFormat="yy-mm-dd" showIcon fluid placeholder="Période" />
                    </div>
                    <Button icon="pi pi-print" severity="primary" aria-label="Imprimer la période" v-tooltip.top="'Imprimer la période'" class="!px-2" @click="emit('print-payments')" />
                    <Button icon="pi pi-refresh" text aria-label="Rafraîchir" v-tooltip.top="'Rafraîchir'" class="!px-2" @click="emit('refresh-payments')" />
                </div>
            </template>

            <div class="page-table-scroll">
            <DataTable :value="filteredPayments" dataKey="pId" :loading="paymentsLoading" paginator :rows="10" :rowsPerPageOptions="[5, 10, 20]" stripedRows responsiveLayout="scroll">
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
                            <Tag :value="computePaymentModeTag(data).label" :severity="computePaymentModeTag(data).severity" />
                            <Tag v-if="data.type === 'service_cabinet'" value="Service cabinet" severity="warn" icon="pi pi-building" />
                            <Tag v-if="isInsurancePayment(data)" value="Assurance" severity="info" icon="pi pi-shield" />
                        </div>
                    </template>
                </Column>
                <Column header="Actions" style="width: 140px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button :icon="isInvoiceStylePayment(data) ? 'pi pi-print' : 'pi pi-ticket'" text @click="emit(isInvoiceStylePayment(data) ? 'print-payment' : 'print-receipt', data)" />
                            <Button v-if="isInternetFeaturesEnabled" icon="pi pi-send" text @click="emit('send-receipt-sms', data)" />
                        </div>
                    </template>
                </Column>
                <template #paginatorend>
                    <div class="payment-paginator-filters">
                        <Select v-model="paymentModeFilter" :options="paymentModeOptions" optionLabel="label" optionValue="value" />
                    </div>
                </template>
            </DataTable>
            </div>
        </PageSection>
    </div>
</template>

<style scoped>
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


/* ────────────────────────────────────────────────────────────
   NOUVELLE VUE REGROUPÉE — cartes facture + paiements liés
   ──────────────────────────────────────────────────────────── */

/* Carte principale */
.inv-card {
    background: var(--surface-card);
    border-radius: var(--page-section-radius);
    border: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    border-left: 4px solid #94a3b8;
    box-shadow: 0 1px 2px color-mix(in srgb, var(--text-color) 4%, transparent);
    overflow: hidden;
}

/* Couleur de la bordure gauche selon statut */
.inv-card--success {
    border-left-color: #22c55e;
}
.inv-card--danger {
    border-left-color: #ef4444;
}
.inv-card--warning {
    border-left-color: #f59e0b;
}
.inv-card--secondary {
    border-left-color: #94a3b8;
}

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

.inv-doc-badge .pi {
    font-size: 0.8rem;
}

.inv-doc-badge--insurance {
    background: #e0f2fe;
    color: #0369a1;
}

.app-dark .inv-doc-badge--insurance {
    background: #0c4a6e;
    color: #7dd3fc;
}

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
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.inv-patient-name .inv-reliquat-icon {
    font-size: 0.85rem;
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

.inv-amount-reste--success {
    color: #16a34a;
}
.inv-amount-reste--danger {
    color: #dc2626;
}
.inv-amount-reste--warning {
    color: #d97706;
}
.inv-amount-reste--secondary {
    color: #64748b;
}

.inv-payment-count {
    margin-top: 0.4rem;
    font-size: 0.78rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.3rem;
}

.inv-payment-count .pi {
    font-size: 0.78rem;
}

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

.inv-payments-header .pi {
    color: #6366f1;
}

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

.inv-payment-row--facture {
    border-left-color: #10b981;
}
.inv-payment-row--ticket {
    border-left-color: #f59e0b;
    background: rgba(245, 158, 11, 0.05);
}

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

.inv-payment-icon-wrap .pi {
    font-size: 0.9rem;
}

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

.app-dark .inv-amount-value {
    color: #e2e8f0;
}
.app-dark .inv-payment-amount {
    color: #e2e8f0;
}
.app-dark .inv-amount-label,
.app-dark .inv-patient-meta,
.app-dark .inv-payment-meta,
.app-dark .inv-payment-count,
.app-dark .inv-doc-id {
    color: #94a3b8;
}

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
