<script setup>
import Button from 'primevue/button';
import DataView from 'primevue/dataview';
import DatePicker from 'primevue/datepicker';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed } from 'vue';

const props = defineProps({
    devis: { type: Array, default: () => [] },
    devisLoading: { type: Boolean, default: false },
    devisType: { type: String, default: 'all' },
    devisRange: { type: Array, default: () => [] }
});

const emit = defineEmits([
    'update:devisType',
    'update:devisRange',
    'refresh-devis',
    'pay',
    'validate-free',
    'modify',
    'preview'
]);

const devisTypeOptions = [
    { label: 'Toutes', value: 'all' },
    { label: 'Factures impayées', value: 'impaye' }
];

const safeDevis = computed(() => 
    {
        console.log(props.devis);
        return (Array.isArray(props.devis) ? props.devis : [])
    });

const devisTypeModel = computed({
    get: () => props.devisType,
    set: (val) => emit('update:devisType', val || 'all')
});

const devisRangeModel = computed({
    get: () => props.devisRange,
    set: (val) => emit('update:devisRange', val || [])
});

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const computeStatus = (row) => {
    const montant = Number(row.montant) || 0;
    const reste = Number(row.reste) || 0;

    if (row.isRegle && reste === 0) return { label: 'Payé', severity: 'success' };
    if (!row.isRegle && reste === 0) return { label: 'Vide non validé', severity: 'secondary' };
    if (reste === montant) return { label: 'Impayé', severity: 'danger' };
    return { label: 'Partiellement payé', severity: 'warning' };
};

const canModify = (row) => (Number(row.montant) === Number(row.reste)) && !row.isRegle;
const canPreview = (row) => !(Number(row.montant) === 0 && Number(row.reste) === 0);
const targetIsFree = (row) => !row.isRegle && Number(row.reste) === 0;

const groups = computed(() => {
    const buckets = { impaye: [], partiel: [], paye: [] };
    safeDevis.value.forEach((row) => {
        const status = computeStatus(row);
        if (status.label === 'Impayé') buckets.impaye.push(row);
        else if (status.label === 'Partiellement payé') buckets.partiel.push(row);
        else buckets.paye.push(row);
    });
    return buckets;
});

const stats = computed(() => {
    const totalRestant = safeDevis.value.reduce((sum, r) => sum + (Number(r.reste) || 0), 0);
    return {
        count: safeDevis.value.length,
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
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="section-card">
            <div class="section-header">
                <div>
                    <p class="section-eyebrow">Vue Factures</p>
                    <p class="section-title">Cartes par statut et liste détaillée, selon vos filtres.</p>
                </div>
                <div class="filters">
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

            <div v-if="!safeDevis.length" class="empty">Aucune facture à afficher pour ces filtres.</div>

            <DataView v-else :value="safeDevis" paginator :rows="6" :rowsPerPageOptions="[6, 12, 24]"
                :loading="devisLoading">
                <template #list="slotProps">
                    <div class="flex flex-col gap-3">
                        <div v-for="(row, index) in slotProps.items" :key="row.id || index" class="dataview-item">
                            <div class="status-chip">
                                <Tag :value="computeStatus(row).label" :severity="computeStatus(row).severity" />
                            </div>

                            <div class="dataview-body">
                                <div class="dv-patient">
                                    <p class="dv-name"> <i class="pi pi-user me-2"></i> {{ formatPatient(row) }}</p>
                                    <p class="dv-phone"> <i class="pi pi-phone me-2"></i> {{ row.telephone || '—' }}</p>
                                    <p class="dv-date"> <i class="pi pi-calendar me-2"></i> {{ row.date || '—' }}</p>
                                </div>

                                <div class="dv-money dark:bg-surface-800">
                                    <div class="kv"><span>Montant</span><span class="font-semibold">{{
                                        formatFcfa(row.montant) }}</span></div>
                                    <div class="kv"><span>Reste</span><span :class="[
                                        'font-semibold',
                                        computeStatus(row).severity === 'danger' ? 'text-red-600' : '',
                                        computeStatus(row).severity === 'warning' ? 'text-orange-600' : ''
                                    ]">{{ formatFcfa(row.reste) }}</span></div>
                                </div>

                                <div class="dv-actions">
                                    <Button v-if="!row.isRegle" :label="targetIsFree(row) ? 'Valider' : 'Régler'" size="small"
                                :severity="targetIsFree(row) ? 'secondary' : 'success'" icon="pi pi-wallet"
                                @click="targetIsFree(row) ? emit('validate-free', row) : emit('pay', row)" />
                            <Button v-if="canModify(row)" label="Modifier" size="small" severity="secondary"
                                icon="pi pi-pencil" @click="emit('modify', row)" />
                            <Button v-if="canPreview(row)" label="Voir" size="small" icon="pi pi-eye" severity="info"
                                @click="emit('preview', row)" />
                                </div>
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
</style>
