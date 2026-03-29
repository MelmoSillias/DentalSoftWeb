<script setup>
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import InputText from 'primevue/inputtext';
import ProgressBar from 'primevue/progressbar';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';

const props = defineProps({
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    paymentRange: { type: Array, default: () => [] }
});

const emit = defineEmits([
    'update:paymentRange',
    'refresh-payments',
    'print-payments',
    'print-payment',
    'print-receipt',
    'send-receipt-sms'
]);

const paymentRangeModel = computed({
    get: () => props.paymentRange,
    set: (val) => emit('update:paymentRange', val || [])
});

const paymentsSearch = ref('');

const normalizeText = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

const matchesQuery = (parts, query) => {
    if (!query) return true;
    return parts.some((part) => normalizeText(part).includes(query));
};

const paymentsSearchQuery = computed(() => normalizeText(paymentsSearch.value.trim()));

const filteredPayments = computed(() => {
    const list = Array.isArray(props.payments) ? props.payments : [];
    const query = paymentsSearchQuery.value;
    return list.filter((p) => matchesQuery([
        p.patient,
        p.telephone,
        p.date,
        formatDate(p.date, true),
        p.montant,
        p.mode,
        p.type
    ], query));
});

const totals = computed(() => {
    const list = filteredPayments.value;
    const total = list.reduce((sum, p) => sum + (Number(p.montant) || 0), 0);
    return { count: list.length, montant: total };
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

const paymentsByMode = computed(() => {
    const bucket = {};
    filteredPayments.value.forEach((p) => {
        const key = p.mode || 'Autre';
        bucket[key] = bucket[key] || [];
        bucket[key].push(p);
    });
    return bucket;
});

const miniChart = computed(() => {
    const byDay = {};
    filteredPayments.value.forEach((p) => {
        const key = p.date ? new Date(p.date).toISOString().slice(0, 10) : '—';
        byDay[key] = (byDay[key] || 0) + (Number(p.montant) || 0);
    });
    const keys = Object.keys(byDay).sort().slice(-7);
    const max = Math.max(...keys.map((k) => byDay[k]), 1);
    return keys.map((key) => ({ day: key, value: byDay[key], pct: Math.round((byDay[key] / max) * 100) }));
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="section-card">
            <div class="section-header">
                <div>
                    <p class="section-eyebrow text-success">Vue Paiements</p>
                    <p class="section-title">Période, montants et ventilation par mode de paiement.</p>
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
            <div class="grid md:grid-cols-3 gap-3 mb-4">
                <div class="stat-card stat-primary">
                    <div class="icon pi pi-wallet"></div>
                    <div>
                        <p class="label">Paiements visibles</p>
                        <p class="value">{{ totals.count }}</p>
                    </div>
                </div>
                <div class="stat-card stat-success">
                    <div class="icon pi pi-chart-line"></div>
                    <div>
                        <p class="label">Recette (période)</p>
                        <p class="value">{{ formatFcfa(totals.montant) }}</p>
                    </div>
                </div>
                <!-- <div class="stat-card stat-neutral">
                    <div class="icon pi pi-calendar"></div>
                    <div class="mini-chart">
                        <p class="label">Montants par jour (7 derniers)</p>
                        <div v-if="!miniChart.length" class="hint">Aucune donnée</div>
                        <div v-for="entry in miniChart" :key="entry.day" class="bar-row">
                            <div class="bar-meta">
                                <span>{{ entry.day }}</span>
                                <span>{{ formatFcfa(entry.value) }}</span>
                            </div>
                            <ProgressBar :value="entry.pct" :showValue="false" />
                        </div>
                    </div>
                </div> -->
            </div>

            <Accordion v-if="Object.keys(paymentsByMode).length">
                <AccordionPanel v-for="(list, mode) in paymentsByMode" :key="mode" :header="`${mode} (${list.length})`">
                    <div class="flex flex-col gap-2">
                        <div v-for="row in list" :key="row.pId" class="payment-row">
                            <div>
                                <div class="text-sm text-gray-500">{{ formatDate(row.date, true) }}</div>
                                <div class="font-semibold">{{ row.patient || '—' }}</div>
                                <div class="text-sm text-gray-600">{{ row.telephone || '' }}</div>
                                <Tag :value="row.mode || '—'" severity="success" />
                            </div>
                            <div class="text-right">
                                <div class="font-semibold">{{ formatFcfa(row.montant) }}</div>
                                <div class="flex gap-2 justify-end mt-2">
                                    <Button :icon="row.type === 'devis' ? 'pi pi-print' : 'pi pi-ticket'" text
                                        @click="emit(row.type === 'devis' ? 'print-payment' : 'print-receipt', row)" />
                                    <Button icon="pi pi-send" text @click="emit('send-receipt-sms', row)" />
                                </div>
                            </div>
                        </div>
                    </div>
                </AccordionPanel>
            </Accordion>
            <div v-else class="hint">Aucun paiement à afficher pour cette période.</div>
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
    color: #22c55e;
}

.section-title {
    color: #0f172a;
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

.stat-success {
    background: linear-gradient(135deg, #ecfdf3, #dcfce7);
    border-color: #bbf7d0;
}

.stat-neutral {
    background: linear-gradient(135deg, #eef2ff, #e2e8f0);
    border-color: #e2e8f0;
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

.mini-chart {
    display: flex;
    flex-direction: column;
    gap: 0.4rem;
}

.bar-row {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.bar-meta {
    display: flex;
    justify-content: space-between;
    font-size: 0.8rem;
    color: #475569;
}

.app-dark .bar-meta { 
    color: #94a3b8;
}

.payment-row {
    padding: 0.9rem;
    border: 1px solid var(--surface-border);
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.hint {
    color: #6b7280;
    font-size: 0.9rem;
}


.app-dark .section-eyebrow {
    color: #dadada;
}
 
 
.app-dark .section-title {
    color: #e2e8f0;
}
 

.app-dark .filter-item label {
    font-size: 0.85rem;
    color: #94a3b8;
}
</style>
