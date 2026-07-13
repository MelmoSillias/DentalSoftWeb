<script setup>
import Button from 'primevue/button';
import DataView from 'primevue/dataview';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';

const props = defineProps({
    factures: { type: Array, default: () => [] },
    facturesLoading: { type: Boolean, default: false },
    factureType: { type: String, default: 'all' },
    factureRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false },
    allowInvoiceModification: { type: Boolean, default: false }
});

const emit = defineEmits([
    'update:factureType',
    'update:factureRange',
    'refresh-factures',
    'pay',
    'validate-free',
    'modify',
    'preview',
    'send-invoice-sms'
]);

const factureTypeOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Impayées (période)', value: 'impaye' },
    { label: 'Toutes les impayées', value: 'impaye_toutes' }
];

const periodFilterDisabled = computed(() => props.factureType === 'impaye_toutes');

const safeFactures = computed(() => (Array.isArray(props.factures) ? props.factures : []));

const factureSearch = ref('');

const normalizeText = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

const matchesQuery = (parts, query) => {
    if (!query) return true;
    return parts.some((part) => normalizeText(part).includes(query));
};

const factureSearchQuery = computed(() => normalizeText(factureSearch.value.trim()));

const factureTypeModel = computed({
    get: () => props.factureType,
    set: (val) => emit('update:factureType', val || 'all')
});

const factureRangeModel = computed({
    get: () => props.factureRange,
    set: (val) => emit('update:factureRange', val || [])
});

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const computeInsuranceBadge = (row) => {
    const insurance = row?.insurance;
    if (!insurance?.hasInsurance) {
        return null;
    }

    return { label: 'Assurance', severity: 'info' };
};

const computeStatus = (row) => {
    const montant = Number(row.montant) || 0;
    const reste = Number(row.reste) || 0;

    if (row.isRegle && reste === 0) return { label: 'Payé', severity: 'success' };
    if (!row.isRegle && reste === 0) return { label: 'Vide non validé', severity: 'secondary' };
    if (reste === montant) return { label: 'Impayé', severity: 'danger' };
    return { label: 'Partiellement payé', severity: 'warning' };
};

const canModify = (row) => props.allowInvoiceModification && !row?.hasPayments && (Number(row.montant) === Number(row.reste)) && !row.isRegle;
const canPreview = (row) => row?.insurance?.hasInsurance || !(Number(row.montant) === 0 && Number(row.reste) === 0);
const targetIsFree = (row) => !row.isRegle && Number(row.reste) === 0;

const filteredFactures = computed(() => {
    const query = factureSearchQuery.value;
    return safeFactures.value.filter((row) => {
        const patient = formatPatient(row);
        const status = computeStatus(row).label;
        const insuranceLabel = computeInsuranceBadge(row)?.label || '';
        return matchesQuery([
            patient,
            row.telephone,
            row.date,
            row.montant,
            row.reste,
            status,
            insuranceLabel,
            row?.insurance?.insuranceModeLabel
        ], query);
    });
});

const groups = computed(() => {
    const buckets = { impaye: [], partiel: [], paye: [] };
    filteredFactures.value.forEach((row) => {
        const status = computeStatus(row);
        if (status.label === 'Impayé') buckets.impaye.push(row);
        else if (status.label === 'Partiellement payé') buckets.partiel.push(row);
        else buckets.paye.push(row);
    });
    return buckets;
});

const stats = computed(() => {
    const totalRestant = filteredFactures.value.reduce((sum, r) => sum + (Number(r.reste) || 0), 0);
    return {
        count: filteredFactures.value.length,
        restant: totalRestant,
        breakdown: `${groups.value.impaye.length}/${groups.value.partiel.length}/${groups.value.paye.length}`
    };
});

const formatPatient = (row) => {
    if (row.patient && typeof row.patient === 'object') {
        return `${row.patient.nom || ''} ${row.patient.prenom || ''}`.trim();
    }
    return row.patient || '—';
};

const displayPhone = (value) => (props.hidePatientPhone ? 'Masqué par l\'administrateur' : (value || '—'));
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="section-card">
            <div class="section-header">
                <div>
                    <p class="section-eyebrow">Vue Factures</p>
                    <p class="section-title">Cartes par statut et liste détaillée, selon vos filtres.</p>
                </div>
                <div class="filters" data-tour="caisse-factures.filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="factureSearch" placeholder="Tapez quelque chose..."
                            fluid />
                    </div>
                    <div class="filter-item">
                        <label>Affichage</label>
                        <Select v-model="factureTypeModel" :options="factureTypeOptions" optionLabel="label"
                            optionValue="value" />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <PanelDatePicker v-model="factureRangeModel" dateFormat="yy-mm-dd" showIcon
                            fluid :disabled="periodFilterDisabled" />
                    </div>
                    <Button label="Rafraîchir" icon="pi pi-refresh" text @click="emit('refresh-factures')" />
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mb-4">
                <div class="stat-card stat-primary">
                    <div class="icon pi pi-file"></div>
                    <div>
                        <p class="label">Factures visibles</p>
                        <p class="value">{{ stats.count }}</p>
                    </div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="icon pi pi-wallet"></div>
                    <div>
                        <p class="label">Total impayé</p>
                        <p class="value">{{ formatFcfa(stats.restant) }}</p>
                    </div>
                </div>
                <div class="stat-card stat-neutral">
                    <div class="icon pi pi-sliders-h"></div>
                    <div>
                        <p class="label">Répartition</p>
                        <p class="value">{{ stats.breakdown }}</p>
                        <p class="hint">Impayées / Partiellement payées / Payées</p>
                    </div>
                </div>
            </div>

            <div v-if="!filteredFactures.length" class="empty">Aucune facture à afficher pour ces filtres.</div>

            <DataView v-else data-tour="caisse-factures.cards" :value="filteredFactures" paginator :rows="6" :rowsPerPageOptions="[6, 12, 24]"
                :loading="facturesLoading">
                <template #list="slotProps">
                    <div class="flex flex-col gap-3 p-1">
                        <div v-for="(row, index) in slotProps.items" :key="row.id || index"
                            class="fct-card"
                            :class="`fct-card--${computeStatus(row).severity}`">

                            <!-- En-tête document -->
                            <div class="fct-header">
                                <div class="fct-doc-badge">
                                    <i class="pi pi-file-invoice"></i>
                                    <span>FACTURE #{{ row.id }}</span>
                                </div>
                                <div class="fct-status-badges">
                                    <Tag :value="computeStatus(row).label" :severity="computeStatus(row).severity" />
                                    <Tag v-if="computeInsuranceBadge(row)"
                                        :value="computeInsuranceBadge(row).label"
                                        :severity="computeInsuranceBadge(row).severity"
                                        icon="pi pi-shield" />
                                </div>
                            </div>

                            <!-- Corps -->
                            <div class="fct-body">
                                <!-- Patient -->
                                <div class="fct-patient">
                                    <p class="fct-patient-name">
                                        <i class="pi pi-user"></i>
                                        {{ formatPatient(row) }}
                                    </p>
                                    <p class="fct-patient-detail">
                                        <i class="pi pi-phone"></i>
                                        {{ displayPhone(row.telephone) }}
                                    </p>
                                    <p class="fct-patient-detail">
                                        <i class="pi pi-calendar"></i>
                                        {{ row.date || '—' }}
                                    </p>
                                </div>

                                <!-- Montants -->
                                <div class="fct-amounts">
                                    <div class="fct-amount-line">
                                        <span class="fct-amount-label">Montant total</span>
                                        <span class="fct-amount-value">{{ formatFcfa(row.montant) }}</span>
                                    </div>
                                    <div class="fct-amount-line fct-amount-line--reste">
                                        <span class="fct-amount-label">Reste à payer</span>
                                        <span class="fct-amount-value fct-amount-reste"
                                            :class="`fct-amount-reste--${computeStatus(row).severity}`">
                                            {{ formatFcfa(row.reste) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="fct-actions" data-tour="caisse-factures.actions">
                                <Button v-if="!row.isRegle"
                                    :label="targetIsFree(row) ? 'Valider' : 'Régler'"
                                    size="small"
                                    :severity="targetIsFree(row) ? 'secondary' : 'success'"
                                    icon="pi pi-wallet"
                                    @click="targetIsFree(row) ? emit('validate-free', row) : emit('pay', row)" />
                                <Button v-if="canModify(row)" label="Modifier" size="small" severity="secondary"
                                    icon="pi pi-pencil" @click="emit('modify', row)" />
                                <Button v-if="canPreview(row)" label="Aperçu" size="small" icon="pi pi-eye"
                                    severity="info" outlined @click="emit('preview', row)" />
                                <Button v-if="canPreview(row)" icon="pi pi-send" size="small" severity="help"
                                    text title="Envoyer facture par SMS"
                                    @click="emit('send-invoice-sms', row)" />
                            </div>
                        </div>
                    </div>
                </template>
            </DataView>
        </div>
    </div>
</template>

<style scoped>
.section-card {
    background: var(--surface-card);
    border-radius: 14px;
    padding: 1.25rem;
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
}

.section-eyebrow {
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #64748b;
}

.app-dark .section-eyebrow {
    color: #dadada;
}

.section-title {
    color: #0f172a;
    font-weight: 600;
}

.app-dark .section-title {
    color: #e2e8f0;
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

.app-dark .filter-item label {
    font-size: 0.85rem;
    color: #94a3b8;
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
}

.stat-card .icon {
    font-size: 1.3rem;
    padding: 0.6rem;
    border-radius: 12px;
    background: rgba(15, 23, 42, 0.08);
}

.stat-card .label {
    font-size: 0.85rem;
    color: #475569;
}

.stat-card .value {
    font-size: 1.4rem;
    font-weight: 700;
}

.stat-card .hint {
    font-size: 0.8rem;
    color: #6b7280;
}

.stat-warning {
    background: linear-gradient(135deg, #fff7ed, #fef3c7);
    border-color: #fde68a;
}

.stat-neutral {
    background: linear-gradient(135deg, #eef2ff, #e2e8f0);
    border-color: #e2e8f0;
}

.kv {
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.35rem;
    font-size: 1.2rem;
}

.empty {
    color: #64748b;
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
}

.dataview-item {
    position: relative;
    padding: 1rem;
    border-radius: 12px;
    border: 1px solid var(--surface-border);
    background: var(--surface-card);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
}

.dataview-body {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    align-items: center;
}

.status-chip {
    position: absolute;
    top: 0.65rem;
    right: 0.65rem;
}

.dv-patient {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.dv-name {
    font-weight: 700;
    color: #0f172a;
    font-size: 1.2rem;
}

.dv-phone,
.dv-date {
    color: #475569;
    font-size: 0.9rem;
}

.dv-money {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem;
}

.dv-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    justify-content: flex-end;
}


.app-dark .dv-name {
    color: #fafcff;
}

  .app-dark .dv-phone,
    .app-dark .dv-date {
        color: #94a3b8;
        font-size: 1.1rem;
    }

.app-dark .dv-money {
    background: #1e293b;
    border: 1px solid #475569;
    border-radius: 10px;
    padding: 0.75rem;
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

/* ─── CARTES FACTURE ─── */

.fct-card {
    border-radius: 14px;
    border: 1px solid var(--surface-border);
    border-left: 5px solid #94a3b8;
    background: var(--surface-card);
    overflow: hidden;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.fct-card:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.fct-card--success   { border-left-color: #22c55e; }
.fct-card--danger    { border-left-color: #ef4444; }
.fct-card--warning   { border-left-color: #f59e0b; }
.fct-card--secondary { border-left-color: #94a3b8; }

/* En-tête document */
.fct-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(90deg, rgba(241,245,249,0.95), rgba(248,250,252,0.8));
    border-bottom: 1px solid var(--surface-border);
    flex-wrap: wrap;
}

.fct-doc-badge {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.72rem;
    font-weight: 700;
    letter-spacing: 0.09em;
    text-transform: uppercase;
    color: #475569;
    background: #e2e8f0;
    border-radius: 6px;
    padding: 0.22rem 0.6rem;
}

.fct-doc-badge .pi { font-size: 0.85rem; color: #64748b; }

.fct-status-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    align-items: center;
}

/* Corps */
.fct-body {
    display: flex;
    gap: 1rem;
    padding: 0.85rem 1rem;
    flex-wrap: wrap;
    align-items: flex-start;
}

/* Bloc patient */
.fct-patient {
    flex: 1 1 200px;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
}

.fct-patient-name {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 0.4rem;
}

.fct-patient-name .pi { color: #6366f1; font-size: 0.9rem; }

.fct-patient-detail {
    margin: 0;
    font-size: 0.83rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.fct-patient-detail .pi { font-size: 0.78rem; }

/* Montants */
.fct-amounts {
    min-width: 165px;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    background: rgba(241, 245, 249, 0.7);
    border-radius: 10px;
    padding: 0.6rem 0.85rem;
    align-self: flex-start;
}

.fct-amount-line {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 0.75rem;
}

.fct-amount-line--reste {
    padding-top: 0.28rem;
    margin-top: 0.1rem;
    border-top: 1px dashed #cbd5e1;
}

.fct-amount-label {
    font-size: 0.78rem;
    color: #64748b;
    white-space: nowrap;
}

.fct-amount-value {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
}

.fct-amount-reste--success   { color: #16a34a; }
.fct-amount-reste--danger    { color: #dc2626; }
.fct-amount-reste--warning   { color: #d97706; }
.fct-amount-reste--secondary { color: #64748b; }

/* Actions */
.fct-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
    padding: 0.6rem 1rem;
    border-top: 1px solid var(--surface-border);
    background: rgba(248, 250, 252, 0.6);
}

/* Dark mode */
.app-dark .fct-header {
    background: linear-gradient(90deg, rgba(30,41,59,0.9), rgba(15,23,42,0.7));
}

.app-dark .fct-doc-badge {
    background: #334155;
    color: #94a3b8;
}

.app-dark .fct-doc-badge .pi { color: #94a3b8; }

.app-dark .fct-patient-name { color: #e2e8f0; }

.app-dark .fct-patient-detail { color: #94a3b8; }

.app-dark .fct-amounts {
    background: rgba(30, 41, 59, 0.6);
}

.app-dark .fct-amount-value { color: #e2e8f0; }

.app-dark .fct-amount-label { color: #94a3b8; }

.app-dark .fct-actions {
    background: rgba(15, 23, 42, 0.4);
}
</style>
