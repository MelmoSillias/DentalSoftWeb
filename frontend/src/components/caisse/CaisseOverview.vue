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
    devis: { type: Array, default: () => [] },
    devisLoading: { type: Boolean, default: false },
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    devisType: { type: String, default: 'all' },
    devisRange: { type: Array, default: () => [] },
    paymentRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false }
});

const emit = defineEmits([
    'update:devisType',
    'update:devisRange',
    'update:paymentRange',
    'refresh-devis',
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

const devisTypeOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Factures impayées', value: 'impaye' }
];

const devisTypeModel = computed({
    get: () => props.devisType,
    set: (val) => emit('update:devisType', val || 'all')
});

const devisRangeModel = computed({
    get: () => props.devisRange,
    set: (val) => emit('update:devisRange', val || [])
});

const paymentRangeModel = computed({
    get: () => props.paymentRange,
    set: (val) => emit('update:paymentRange', val || [])
});

const devisSearch = ref('');
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

const devisSearchQuery = computed(() => normalizeText(devisSearch.value.trim()));
const paymentsSearchQuery = computed(() => normalizeText(paymentsSearch.value.trim()));

const paymentFamilyOptions = [
    { label: 'Modes non assurances', value: 'non-insurance' },
    { label: 'Modes assurances', value: 'insurance' },
    { label: 'Tous les modes', value: 'all' }
];

const filteredDevis = computed(() => {
    const list = Array.isArray(props.devis) ? props.devis : [];
    const query = devisSearchQuery.value;
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

const groupedInvoices = computed(() => {
    const rows = filteredDevis.value;
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

const computePaymentRoleTag = (payment) => {
    if (payment?.rolePaiement === 'insurance') {
        return payment?.insuranceStatus === 'pending'
            ? { label: 'Assurance en attente', severity: 'warning' }
            : { label: 'Assurance', severity: 'info' };
    }

    return { label: 'Client', severity: 'success' };
};

const computePaymentModeTag = (payment) => {
    if (payment?.rolePaiement === 'insurance') {
        return {
            label: payment?.mode || 'Assurance',
            severity: payment?.insuranceStatus === 'pending' ? 'warning' : 'info'
        };
    }

    return {
        label: payment?.mode || '—',
        severity: 'success'
    };
};

const familyFilteredPayments = computed(() => {
    const list = Array.isArray(props.payments) ? props.payments : [];
    if (paymentFamilyFilter.value === 'all') {
        return list;
    }

    const wantsInsurance = paymentFamilyFilter.value === 'insurance';
    return list.filter((payment) => (payment?.rolePaiement === 'insurance') === wantsInsurance);
});

const paymentModeOptions = computed(() => {
    const options = familyFilteredPayments.value.reduce((acc, payment) => {
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

const handlePaymentFamilyChange = (value) => {
    paymentFamilyFilter.value = value;
    const optionStillExists = paymentModeOptions.value.some((option) => option.value === paymentModeFilter.value);
    if (!optionStillExists) {
        paymentModeFilter.value = 'all';
    }
};

const filteredPayments = computed(() => {
    const list = familyFilteredPayments.value;
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
        row.rolePaiement,
        computePaymentRoleTag(row).label
    ], query);
    });
});

const devisTotals = computed(() => {
    const list = filteredDevis.value;
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
    const allInvoices = props.devis || [];
    const allPayments = props.payments || [];
    
    const totalInvoices = allInvoices.length;
    const totalPaid = allInvoices.reduce((sum, inv) => sum + (Number(inv.montant) - (Number(inv.reste) || 0)), 0);
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
    
    const insurancePayments = allPayments.filter(p => p.rolePaiement === 'insurance');
    const totalInsurance = insurancePayments.reduce((sum, p) => sum + (Number(p.montant) || 0), 0);
    const pendingInsurance = insurancePayments.filter(p => p.insuranceStatus === 'pending').length;
    
    return {
        totalInvoices,
        totalPaid,
        totalUnpaid,
        statusCounts,
        paymentModeBreakdown,
        totalInsurance,
        pendingInsurance,
        totalPaymentsCount: allPayments.length,
        totalPaymentsAmount: allPayments.reduce((sum, p) => sum + (Number(p.montant) || 0), 0)
    };
});

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
                <SelectButton 
                    v-model="overviewDisplayMode" 
                    :options="overviewDisplayOptions" 
                    optionLabel="label" 
                    optionValue="value"
                />
            </div>
            <Button 
                label="Statistiques" 
                icon="pi pi-chart-bar" 
                severity="secondary" 
                outlined
                @click="showStatsModal = true" 
            />
        </div>

        <!-- Modal statistiques détaillées -->
        <Dialog 
            v-model:visible="showStatsModal" 
            header="Statistiques détaillées" 
            :modal="true" 
            :style="{ width: '600px' }"
            class="stats-dialog"
        >
            <div class="stats-content">
                <div class="stats-section">
                    <h4>Factures</h4>
                    <div class="stats-grid">
                        <div><strong>Total factures :</strong> {{ detailedStats.totalInvoices }}</div>
                        <div><strong>Montant total facturé :</strong> {{ formatFcfa(detailedStats.totalPaid + detailedStats.totalUnpaid) }}</div>
                        <div><strong>Montant encaissé :</strong> {{ formatFcfa(detailedStats.totalPaid) }}</div>
                        <div><strong>Montant restant dû :</strong> {{ formatFcfa(detailedStats.totalUnpaid) }}</div>
                    </div>
                    <div class="status-breakdown">
                        <div><Tag value="Payé" severity="success" /> : {{ detailedStats.statusCounts.paid }}</div>
                        <div><Tag value="Partiellement payé" severity="warning" /> : {{ detailedStats.statusCounts.partial }}</div>
                        <div><Tag value="Impayé" severity="danger" /> : {{ detailedStats.statusCounts.unpaid }}</div>
                        <div><Tag value="Vide non validé" severity="secondary" /> : {{ detailedStats.statusCounts.freeNotValidated }}</div>
                    </div>
                </div>
                <div class="stats-section">
                    <h4>Paiements</h4>
                    <div><strong>Nombre de paiements :</strong> {{ detailedStats.totalPaymentsCount }}</div>
                    <div><strong>Montant total encaissé :</strong> {{ formatFcfa(detailedStats.totalPaymentsAmount) }}</div>
                    <div><strong>Montant assurances :</strong> {{ formatFcfa(detailedStats.totalInsurance) }}</div>
                    <div><strong>Assurances en attente :</strong> {{ detailedStats.pendingInsurance }}</div>
                    <div v-if="Object.keys(detailedStats.paymentModeBreakdown).length">
                        <strong>Répartition par mode :</strong>
                        <ul>
                            <li v-for="(amount, mode) in detailedStats.paymentModeBreakdown" :key="mode">
                                {{ mode }} : {{ formatFcfa(amount) }}
                            </li>
                        </ul>
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
                        <InputText v-model="devisSearch" placeholder="Patient, téléphone, montant..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Affichage</label>
                        <Select v-model="devisTypeModel" :options="devisTypeOptions" optionLabel="label"
                            optionValue="value" />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <DatePicker v-model="devisRangeModel" selectionMode="range" dateFormat="yy-mm-dd" showIcon
                            fluid />
                    </div>
                    <Button label="Rafraîchir" icon="pi pi-refresh" text @click="emit('refresh-devis')" />
                </div>
            </div>

            <!-- Vue standard -->
            <DataTable v-if="overviewDisplayMode === 'standard'" class="rounded-xl overflow-hidden" :value="filteredDevis" dataKey="id" :loading="devisLoading"
                paginator :rows="10" :rowsPerPageOptions="[5, 10, 20]" responsiveLayout="scroll">
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
                            <Button v-if="canModify(data)" size="small" severity="secondary"
                                icon="pi pi-pencil" @click="handleModify(data)" />
                            <Button v-if="canPreview(data)" size="small" icon="pi pi-eye" severity="info" class="p-button-outlined"
                                @click="handlePreview(data)" />
                            <Button v-if="canPreview(data)" size="small" icon="pi pi-send" severity="help"
                                @click="emit('send-invoice-sms', data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>

            <!-- Vue regroupée améliorée -->
            <DataView
                v-else
                class="grouped-invoices-view"
                :value="groupedInvoices"
                :loading="devisLoading"
                paginator
                :rows="10"
                :rowsPerPageOptions="[5, 10, 20]"
            >
                <template #list="slotProps">
                    <div class="flex flex-col gap-4 p-2">
                        <article
                            v-for="invoice in slotProps.items"
                            :key="invoice.id"
                            class="invoice-card"
                        >
                            <div class="invoice-card-header">
                                <div class="invoice-main">
                                    <div class="invoice-topline">
                                        <p class="invoice-patient">
                                            {{ (invoice.patient && `${invoice.patient.nom || ''} ${invoice.patient.prenom || ''}`.trim()) || invoice.patient || '—' }}
                                        </p>

                                        <p class="invoice-number">Facture #{{ invoice.id }}</p>
                                        <Tag :value="computeStatus(invoice).label" :severity="computeStatus(invoice).severity" />
                                        <Tag v-if="computeInsuranceBadge(invoice)" :value="computeInsuranceBadge(invoice).label" :severity="computeInsuranceBadge(invoice).severity" icon="pi pi-shield" />
                                    </div>
                                    <p class="invoice-subline">
                                        📞 {{ displayPhone(invoice.telephone) }} • 🗓 {{ formatDate(invoice.date) }}
                                    </p>
                                </div>
                                <div class="invoice-amounts">
                                    <p><span>Total :</span> <strong>{{ formatFcfa(invoice.montant) }}</strong></p>
                                    <p><span>Reste :</span> <strong>{{ formatFcfa(invoice.reste) }}</strong></p>
                                    <Tag :value="`${invoice.detailCount || 0} paiement(s) lié(s)`" :severity="invoice.hasDetails ? 'info' : 'secondary'" />
                                </div>
                            </div>

                            <div class="invoice-actions">
                                <Button v-if="!invoice.isRegle" :label="targetIsFree(invoice) ? 'Valider' : 'Régler'" size="small"
                                    :severity="targetIsFree(invoice) ? 'secondary' : 'success'" icon="pi pi-wallet"
                                    @click="targetIsFree(invoice) ? handleValidate(invoice) : handlePay(invoice)" />
                                <Button v-if="canModify(invoice)" size="small" severity="secondary"
                                    icon="pi pi-pencil" @click="handleModify(invoice)" />
                                <Button v-if="canPreview(invoice)" size="small" icon="pi pi-eye" severity="info" class="p-button-outlined"
                                    @click="handlePreview(invoice)" />
                                <Button v-if="canPreview(invoice)" size="small" icon="pi pi-send" severity="help"
                                    @click="emit('send-invoice-sms', invoice)" />
                                <Button
                                    size="small"
                                    text
                                    :label="isInvoiceExpanded(invoice) ? 'Masquer détails' : 'Voir détails'"
                                    :icon="isInvoiceExpanded(invoice) ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                                    @click="toggleInvoiceExpansion(invoice)"
                                />
                            </div>

                            <transition name="fade-slide">
                                <div v-if="isInvoiceExpanded(invoice)" class="invoice-details">
                                    <div v-if="!invoice.detailRows?.length" class="text-sm text-surface-500">
                                        Aucun paiement lié pour cette facture.
                                    </div>
                                    <div v-else class="detail-list">
                                        <div
                                            v-for="(detail, idx) in invoice.detailRows"
                                            :key="`${invoice.id}-${detail.pId || idx}`"
                                            class="detail-row"
                                            :class="detailRowClass(detail)"
                                        >
                                            <div class="detail-meta">
                                                <Tag
                                                    :value="detail.detailLabel"
                                                    :severity="detail.detailType === 'consultation_ticket' ? 'warning' : 'success'"
                                                    :icon="detail.detailType === 'consultation_ticket' ? 'pi pi-ticket' : 'pi pi-wallet'"
                                                />
                                                <span class="detail-date">{{ formatDate(detail.date, true) }}</span>
                                                <span class="detail-mode">{{ detail.mode || '—' }}</span>
                                            </div>
                                            <div class="detail-right">
                                                <strong>{{ formatFcfa(detail.montant) }}</strong>
                                                <Button icon="pi pi-print" text @click="printDetailPayment(detail)" />
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
                        <InputText v-model="paymentsSearch" placeholder="Tapez quelque chose..."
                            fluid />
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
                            <Tag :value="computePaymentModeTag(data).label" :severity="computePaymentModeTag(data).severity" />
                            <Tag :value="computePaymentRoleTag(data).label" :severity="computePaymentRoleTag(data).severity" />
                        </div>
                    </template>
                </Column>
                <Column header="Actions" style="width: 140px">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button :icon="data.type === 'devis' ? 'pi pi-print' : 'pi pi-ticket'" text
                                @click="emit(data.type === 'devis' ? 'print-payment' : 'print-receipt', data)" />
                            <Button icon="pi pi-send" text @click="emit('send-receipt-sms', data)" />
                        </div>
                    </template>
                </Column>
                <template #paginatorend>
                    <div class="payment-paginator-filters">
                        <Select :modelValue="paymentFamilyFilter" :options="paymentFamilyOptions" optionLabel="label"
                            optionValue="value" @update:modelValue="handlePaymentFamilyChange" />
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
    border: 1px solid var(--surface-border);
    border-radius: 16px;
    background: var(--surface-card);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
    padding: 1rem;
    transition: all 0.2s;
}

.invoice-card:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    border-color: var(--primary-color);
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

.invoice-number {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: #0f172a;
}

.invoice-subline {
    margin: 0;
    font-size: 0.85rem;
    color: #64748b;
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
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
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
</style>