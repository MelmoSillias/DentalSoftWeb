<script setup>
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    devis: { type: Array, default: () => [] },
    devisLoading: { type: Boolean, default: false },
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    devisType: { type: String, default: 'all' },
    devisRange: { type: Array, default: () => [] },
    paymentRange: { type: Array, default: () => [] }
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

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const formatDate = (value, withTime = false) => {
    if (!value) return '—';
    const date = new Date(value);
    const datePart = date.toLocaleDateString('fr-FR');
    if (!withTime) return datePart;
    const timePart = date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    return `${datePart} ${timePart}`;
};

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

</script>

<template>
    <div class="flex flex-col gap-5">
        <div class="grid md:grid-cols-3 gap-3 stat-cards" data-tour="caisse-overview.stats">
            <div class="stat-card stat-primary">
                <div class="icon pi pi-file" aria-hidden="true"></div>
                <div>
                    <p class="label">Factures visibles</p>
                    <p class="value">{{ devisTotals.count }}</p>
                    <p class="hint">Selon les filtres actifs</p>
                </div>
            </div>
            <div class="stat-card stat-warning">
                <div class="icon pi pi-wallet" aria-hidden="true"></div>
                <div>
                    <p class="label">Total impayé</p>
                    <p class="value">{{ formatFcfa(devisTotals.restant) }}</p>
                    <p class="hint">À recouvrer</p>
                </div>
            </div>
            <div class="stat-card stat-success">
                <div class="icon pi pi-chart-line" aria-hidden="true"></div>
                <div>
                    <p class="label">Recette période</p>
                    <p class="value">{{ formatFcfa(paymentsTotals.montant) }}</p>
                    <p class="hint">Paiements encaissés</p>
                </div>
            </div>
        </div>

        <div class="section-card" data-tour="caisse-overview.factures">
            <div class="section-header">
                <div>
                    <p class="section-eyebrow">Factures impayées</p>
                    <p class="section-title">Filtrez, réglez ou modifiez une facture avant validation.</p>
                </div>
                <div class="filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="devisSearch" placeholder="Tapez quelque chose..."
                            fluid />
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

            <DataTable class="rounded-xl overflow-hidden" :value="filteredDevis" dataKey="id" :loading="devisLoading"
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
                <Column field="telephone" header="Téléphone" sortable></Column>
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
        </div>

        <div class="section-card" data-tour="caisse-overview.payments">
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
                <Column field="telephone" header="Téléphone" sortable></Column>
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
.stat-cards {
    position: sticky;
    top: 0rem;
    z-index: 10;
}

.stat-card {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 0.75rem;
    padding: 1rem;
    border-radius: 14px;
    color: #0f172a;
    background: linear-gradient(135deg, #f8fafc, #eef2ff);
    border: 1px solid #e2e8f0;
    backdrop-filter: blur(10px);
}
 

.stat-card .icon {
    font-size: 1.4rem; 
    padding: 0.65rem;
    border-radius: 12px;
    color: #0f172a;
    background: rgba(15, 23, 42, 0.08);
}

.stat-card .label {
    font-size: 0.85rem;
    color: #475569;
}

.stat-card .value {
    font-size: 1.5rem;
    font-weight: 700;
}

.stat-card .hint {
    font-size: 0.8rem;
    color: #6b7280;
}

.stat-primary {
    background: linear-gradient(135deg, #e0f2fe71, #eef2ff73);
    border-color: #cbd5e1;
}

.stat-warning {
    background: linear-gradient(135deg, #fff7ed75, #fef3c771);
    border-color: #fde68a;
}

.stat-success {
    background: linear-gradient(135deg, #ecfdf37e, #dcfce783);
    border-color: #bbf7d0;
}

.app-dark .stat-card {
    background: linear-gradient(135deg, #0f172a, #111827);
    color: #e2e8f0;
    border-color: #1f2937;
}

.app-dark .stat-card .icon {
    background: rgba(255, 255, 255, 0.06);
    color: #e2e8f0;
}

.app-dark .stat-card .label {
    color: #cbd5e1;
}

.app-dark .stat-card .hint {
    color: #94a3b8;
}

.app-dark .stat-primary {
    background: linear-gradient(135deg, #0ea5e9, #1e293b);
    border-color: #1f2937;
}

.app-dark .stat-warning {
    background: linear-gradient(135deg, #f59e0b, #1f2937);
    border-color: #1f2937;
}

.app-dark .stat-success {
    background: linear-gradient(135deg, #22c55e, #1f2937);
    border-color: #1f2937;
}

.app-dark .stat-neutral {
    background: linear-gradient(135deg, #475569, #1f2937);
    border-color: #1f2937;
}

.section-card {
    background: var(--surface-card);
    border-radius: 14px;
    padding: 0 0rem 1.25rem 0rem;
    border: 1px solid var(--surface-border);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
}

.section-header {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
    padding: 1rem 1rem 0.95rem;
    border-radius: 12px 12px 0 0;
    background: linear-gradient(135deg, rgba(226, 232, 240, 0.9), rgba(203, 213, 225, 0.72));
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

.filter-item {
    min-width: 220px;
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.filter-item label {
    font-size: 0.85rem;
    color: #475569;
}

 
.app-dark .section-eyebrow {
    color: #dadada;
}
 
 
.app-dark .section-title {
    color: #e2e8f0;
}

.app-dark .section-header {
    background: linear-gradient(135deg, rgba(30, 41, 59, 0.96), rgba(15, 23, 42, 0.88));
}
 

.app-dark .filter-item label {
    font-size: 0.85rem;
    color: #94a3b8;
}

.payment-paginator-filters {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 0.5rem;
    padding-left: 0.75rem;
}

.payment-paginator-filters :deep(.p-select) {
    min-width: 220px;
}

</style>