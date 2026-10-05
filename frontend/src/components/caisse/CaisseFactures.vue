<script setup>
import Button from 'primevue/button';
import DataView from 'primevue/dataview';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import PageSection from '@/components/layout/PageSection.vue';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { canModifyFacture, canPreviewFacture, canSettleFacture, computeFactureStatus, computePriorReliquat, isCabinetServiceFacture, isInsuranceFactureRow, targetIsFreeFacture } from '@/utils/factureRow';

const { isInternetFeaturesEnabled } = useInternetFeatures();

const props = defineProps({
    factures: { type: Array, default: () => [] },
    facturesLoading: { type: Boolean, default: false },
    factureType: { type: String, default: 'all' },
    factureRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false },
    allowInvoiceModification: { type: Boolean, default: false }
});

const emit = defineEmits(['update:factureType', 'update:factureRange', 'refresh-factures', 'pay', 'validate-free', 'modify', 'preview', 'send-invoice-sms']);

const factureTypeOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Impayées (période)', value: 'impaye' },
    { label: 'Toutes les impayées', value: 'impaye_toutes' }
];

const periodFilterDisabled = computed(() => props.factureType === 'impaye_toutes');

const safeFactures = computed(() => (Array.isArray(props.factures) ? props.factures : []));

const factureSearch = ref('');
const factureOrigin = ref('all');
const factureOriginOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Consultations', value: 'consultation' },
    { label: 'Services cabinet', value: 'service_cabinet' }
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

const factureTypeModel = computed({
    get: () => props.factureType,
    set: (val) => emit('update:factureType', val || 'all')
});

const factureRangeModel = computed({
    get: () => props.factureRange,
    set: (val) => emit('update:factureRange', val || [])
});

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const isInsuranceRow = (row) => isInsuranceFactureRow(row);
const isCabinetRow = (row) => isCabinetServiceFacture(row);
const documentLabel = (row) => (isCabinetRow(row) ? 'SERVICE CABINET' : isInsuranceRow(row) ? 'FACTURE ASSURANCE' : 'FACTURE');
const matchesOrigin = (row) => {
    if (factureOrigin.value === 'service_cabinet') return isCabinetRow(row);
    if (factureOrigin.value === 'consultation') return !isCabinetRow(row);
    return true;
};

const computeInsuranceBadge = (row) => {
    if (!isInsuranceRow(row)) {
        return null;
    }

    const nom = row?.insurance?.assuranceNom;
    return { label: nom ? `${nom}` : 'Assurance', severity: 'info' };
};

const computeStatus = (row) => computeFactureStatus(row);

const canModify = (row) => canModifyFacture(row, { allowInvoiceModification: props.allowInvoiceModification });
const canSettle = (row) => canSettleFacture(row);
const canPreview = (row) => canPreviewFacture(row);
const targetIsFree = (row) => targetIsFreeFacture(row);

const filteredFactures = computed(() => {
    const query = factureSearchQuery.value;
    return safeFactures.value.filter((row) => {
        if (!matchesOrigin(row)) return false;
        const patient = formatPatient(row);
        const status = computeStatus(row).label;
        const insuranceLabel = computeInsuranceBadge(row)?.label || '';
        return matchesQuery([patient, row.telephone, row.date, row.montant, row.reste, status, insuranceLabel, row?.insurance?.insuranceModeLabel], query);
    });
});

const groups = computed(() => {
    const buckets = { impaye: [], partiel: [], paye: [] };
    filteredFactures.value.forEach((row) => {
        const status = computeStatus(row);
        if (status.label === 'Impayé') buckets.impaye.push(row);
        else if (status.label === 'Partiellement payé') buckets.partiel.push(row);
        else if (status.label === 'Payé') buckets.paye.push(row);
    });
    return buckets;
});

const stats = computed(() => {
    const totalRestant = filteredFactures.value.reduce((sum, r) => sum + (Number(r.reste) || 0), 0);
    return {
        count: filteredFactures.value.length,
        restant: totalRestant
    };
});

const formatPatient = (row) => {
    if (row.patient && typeof row.patient === 'object') {
        return `${row.patient.nom || ''} ${row.patient.prenom || ''}`.trim();
    }
    return row.patient || '—';
};

const priorReliquatAmount = (row) => computePriorReliquat(row);

const displayPhone = (value) => (props.hidePatientPhone ? "Masqué par l'administrateur" : value || '—');
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="page-kpi-grid">
            <div class="page-kpi-card border-primary-200/70 bg-gradient-to-br from-primary-50/80 to-primary-100/50 dark:border-primary-800/40 dark:from-primary-900/30 dark:to-primary-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-primary-700 dark:text-primary-300">Factures visibles</p>
                    <p class="page-kpi-value text-primary-900 dark:text-primary-100">{{ stats.count }}</p>
                </div>
                <i class="pi pi-file page-kpi-icon text-primary-500"></i>
            </div>
            <div class="page-kpi-card border-amber-200/70 bg-gradient-to-br from-amber-50/80 to-amber-100/50 dark:border-amber-800/40 dark:from-amber-900/20 dark:to-amber-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-amber-700 dark:text-amber-300">Total impayé</p>
                    <p class="page-kpi-value truncate text-amber-900 dark:text-amber-100">{{ formatFcfa(stats.restant) }}</p>
                </div>
                <i class="pi pi-wallet page-kpi-icon text-amber-500"></i>
            </div>
            <div class="page-kpi-card border-rose-200/70 bg-gradient-to-br from-rose-50/80 to-rose-100/50 dark:border-rose-800/40 dark:from-rose-900/20 dark:to-rose-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-rose-700 dark:text-rose-300">Impayées</p>
                    <p class="page-kpi-value text-rose-900 dark:text-rose-100">{{ groups.impaye.length }}</p>
                </div>
                <i class="pi pi-exclamation-circle page-kpi-icon text-rose-500"></i>
            </div>
            <div class="page-kpi-card border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-100/50 dark:border-slate-800/40 dark:from-slate-900/20 dark:to-slate-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-slate-600 dark:text-slate-300">Partielles / payées</p>
                    <p class="page-kpi-value text-slate-900 dark:text-surface-100">{{ groups.partiel.length }} / {{ groups.paye.length }}</p>
                </div>
                <i class="pi pi-sliders-h page-kpi-icon text-slate-500"></i>
            </div>
        </div>

        <PageSection title="Factures" subtitle="Documents facture regroupés selon vos filtres." tour-id="caisse-factures.section">
            <template #headerActions>
                <div class="filters" data-tour="caisse-factures.filters">
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

            <div class="flex flex-col gap-4 p-3 sm:p-4">
                <div v-if="!filteredFactures.length" class="empty px-1">Aucune facture à afficher pour ces filtres.</div>

            <DataView v-else data-tour="caisse-factures.cards" :value="filteredFactures" paginator :rows="9" :rowsPerPageOptions="[9, 12, 24]" :loading="facturesLoading">
                <template #list="slotProps">
                    <div class="fct-grid p-1">
                        <article
                            v-for="(row, index) in slotProps.items"
                            :key="row.id || index"
                            class="fct-invoice"
                            :class="`fct-invoice--${computeStatus(row).severity}`"
                        >
                            <header class="fct-invoice-top">
                                <div class="fct-invoice-brand">
                                    <span class="fct-invoice-type">{{ documentLabel(row) }}</span>
                                    <span class="fct-invoice-number">N° {{ row.id }}</span>
                                </div>
                                <div class="fct-invoice-meta">
                                    <span class="fct-invoice-date">{{ row.date || '—' }}</span>
                                    <Tag :value="computeStatus(row).label" :severity="computeStatus(row).severity" />
                                </div>
                            </header>

                            <div class="fct-invoice-divider" aria-hidden="true"></div>

                            <section class="fct-invoice-party">
                                <p class="fct-invoice-party-label">Facturé à</p>
                                <p class="fct-invoice-party-name">
                                    {{ formatPatient(row) }}
                                    <i
                                        v-if="priorReliquatAmount(row) > 0"
                                        v-tooltip.top="`Reliquat : ${priorReliquatAmount(row).toLocaleString('fr-FR')} FCFA`"
                                        class="pi pi-wallet fct-reliquat-icon"
                                        aria-label="Reliquat patient"
                                    ></i>
                                </p>
                                <p class="fct-invoice-party-line">Tél. {{ displayPhone(row.telephone) }}</p>
                                <p v-if="isCabinetRow(row) || computeInsuranceBadge(row)" class="fct-invoice-tags">
                                    <Tag v-if="isCabinetRow(row)" value="Service cabinet" severity="warn" icon="pi pi-building" />
                                    <Tag v-if="computeInsuranceBadge(row)" :value="computeInsuranceBadge(row).label" severity="info" icon="pi pi-shield" />
                                </p>
                            </section>

                            <table class="fct-invoice-table">
                                <thead>
                                    <tr>
                                        <th>Désignation</th>
                                        <th class="fct-col-amount">Montant</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="isInsuranceRow(row)">
                                        <td>Total facture</td>
                                        <td class="fct-col-amount">{{ formatFcfa(row.insurance?.montantTotal ?? row.montantTotal) }}</td>
                                    </tr>
                                    <tr>
                                        <td>{{ isInsuranceRow(row) ? 'Part patient' : 'Montant total' }}</td>
                                        <td class="fct-col-amount">{{ formatFcfa(row.montant) }}</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td>Reste à payer</td>
                                        <td class="fct-col-amount fct-reste" :class="`fct-reste--${computeStatus(row).severity}`">
                                            {{ formatFcfa(row.reste) }}
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>

                            <footer class="fct-invoice-actions" data-tour="caisse-factures.actions">
                                <Button
                                    v-if="canSettle(row)"
                                    :label="targetIsFree(row) ? 'Valider' : 'Régler'"
                                    size="small"
                                    :severity="targetIsFree(row) ? 'secondary' : 'success'"
                                    icon="pi pi-wallet"
                                    @click="targetIsFree(row) ? emit('validate-free', row) : emit('pay', row)"
                                />
                                <Button v-if="canModify(row)" label="Modifier" size="small" severity="secondary" icon="pi pi-pencil" @click="emit('modify', row)" />
                                <Button v-if="canPreview(row)" label="Aperçu" size="small" icon="pi pi-eye" severity="info" outlined @click="emit('preview', row)" />
                                <Button v-if="canPreview(row) && isInternetFeaturesEnabled" icon="pi pi-send" size="small" severity="help" text title="Envoyer facture par SMS" @click="emit('send-invoice-sms', row)" />
                            </footer>
                        </article>
                    </div>
                </template>
            </DataView>
            </div>
        </PageSection>
    </div>
</template>

<style scoped>
.empty {
    color: #64748b;
    font-size: 0.95rem;
    margin-bottom: 0.5rem;
}

/* Grille : 1 → 2 → 3 colonnes */
.fct-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
}

@media (min-width: 768px) {
    .fct-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1200px) {
    .fct-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.fct-invoice {
    display: flex;
    flex-direction: column;
    border: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    border-radius: var(--page-section-radius);
    background: var(--surface-card);
    box-shadow: 0 1px 2px color-mix(in srgb, var(--text-color) 4%, transparent);
    overflow: hidden;
    min-height: 100%;
}

.fct-invoice-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem 0.65rem;
}

.fct-invoice-brand {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.fct-invoice-type {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-color-secondary);
}

.fct-invoice-number {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-color);
    font-variant-numeric: tabular-nums;
}

.fct-invoice-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.35rem;
    flex-shrink: 0;
}

.fct-invoice-date {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
    font-variant-numeric: tabular-nums;
}

.fct-invoice-divider {
    height: 1px;
    margin: 0 1rem;
    background: color-mix(in srgb, var(--surface-border) 85%, transparent);
}

.fct-invoice-party {
    padding: 0.75rem 1rem 0.5rem;
}

.fct-invoice-party-label {
    margin: 0 0 0.2rem;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-color-secondary);
}

.fct-invoice-party-name {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-color);
    display: flex;
    align-items: center;
    gap: 0.35rem;
    line-height: 1.3;
}

.fct-reliquat-icon {
    font-size: 0.85rem;
    color: #d97706;
}

.fct-invoice-party-line {
    margin: 0.25rem 0 0;
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

.fct-invoice-tags {
    margin: 0.45rem 0 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.fct-invoice-table {
    width: calc(100% - 2rem);
    margin: 0.35rem 1rem 0.75rem;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.fct-invoice-table th {
    text-align: left;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-color-secondary);
    border-bottom: 1px solid color-mix(in srgb, var(--surface-border) 85%, transparent);
    padding: 0.35rem 0;
}

.fct-invoice-table td {
    padding: 0.4rem 0;
    color: var(--text-color);
    border-bottom: 1px solid color-mix(in srgb, var(--surface-border) 55%, transparent);
}

.fct-invoice-table tfoot td {
    border-bottom: none;
    border-top: 1px solid color-mix(in srgb, var(--surface-border) 85%, transparent);
    padding-top: 0.55rem;
    font-weight: 700;
    color: var(--text-color);
}

.fct-col-amount {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.fct-reste--success {
    color: #16a34a;
}
.fct-reste--danger {
    color: #dc2626;
}
.fct-reste--warning {
    color: #d97706;
}
.fct-reste--secondary {
    color: #64748b;
}

.fct-invoice-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.65rem 1rem 0.85rem;
    margin-top: auto;
    border-top: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    background: color-mix(in srgb, var(--surface-card) 92%, var(--text-color) 4%);
}
</style>
