<script setup>
import Accordion from 'primevue/accordion';
import AccordionPanel from 'primevue/accordionpanel';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import { useInternetFeatures } from '@/composables/useInternetFeatures';

const { isInternetFeaturesEnabled } = useInternetFeatures();

const props = defineProps({
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    paymentRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false }
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
const paymentModeFilter = ref('all');

const normalizeText = (value) => String(value ?? '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');

const matchesQuery = (parts, query) => {
    if (!query) return true;
    return parts.some((part) => normalizeText(part).includes(query));
};

const paymentsSearchQuery = computed(() => normalizeText(paymentsSearch.value.trim()));

const isInsurancePayment = (payment) => {
    if (payment?.type === 'facture_assurance') return true;
    const role = String(payment?.rolePaiement || '').toLowerCase();
    return role === 'patient_insurance';
};

const isInvoiceStylePayment = (payment) =>
    ['devis', 'facture', 'facture_assurance'].includes(payment?.type);

const computeModeTag = (payment) => {
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
    return list.filter((p) => {
        if (paymentModeFilter.value !== 'all' && Number(p?.modeId) !== Number(paymentModeFilter.value)) {
            return false;
        }

        return matchesQuery([
            p.patient,
            p.telephone,
            p.date,
            formatDate(p.date, true),
            p.montant,
            p.mode,
            p.type,
            p.insuranceStatus,
            computeModeTag(p).label
        ], query);
    });
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

const displayPhone = (value) => (props.hidePatientPhone ? 'Masqué par l\'administrateur' : (value || ''));

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
                <div class="filters" data-tour="caisse-paiements.filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="paymentsSearch" placeholder="Tapez quelque chose..."
                            fluid />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <PanelDatePicker v-model="paymentRangeModel" dateFormat="yy-mm-dd" showIcon
                            fluid />
                    </div>
                    <div class="filter-item">
                        <label>Mode de paiement</label>
                        <Select v-model="paymentModeFilter" :options="paymentModeOptions" optionLabel="label"
                            optionValue="value" />
                    </div>
                    <Button label="Imprimer la période" icon="pi pi-print" severity="primary"
                        @click="emit('print-payments')" />
                    <Button label="Rafraîchir" icon="pi pi-refresh" text @click="emit('refresh-payments')" />
                </div>
            </div>
            <div class="grid md:grid-cols-3 gap-3 mb-4" data-tour="caisse-paiements.totals">
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

            <Accordion v-if="Object.keys(paymentsByMode).length" data-tour="caisse-paiements.accordion">
                <AccordionPanel v-for="(list, mode) in paymentsByMode" :key="mode">
                    <template #header>
                        <div class="pay-accordion-header">
                            <div class="pay-mode-badge">
                                <i :class="mode.toLowerCase().includes('assur') ? 'pi pi-shield' : 'pi pi-receipt'"></i>
                                <span>{{ mode }}</span>
                            </div>
                            <span class="pay-accordion-count">{{ list.length }} transaction(s)</span>
                            <span class="pay-accordion-total">
                                {{ formatFcfa(list.reduce((s, p) => s + (Number(p.montant) || 0), 0)) }}
                            </span>
                        </div>
                    </template>
                    <div class="pay-list">
                        <div v-for="row in list" :key="row.pId" class="pay-row"
                            :class="isInsurancePayment(row) ? 'pay-row--insurance' : 'pay-row--client'">
                            <div class="pay-role-icon">
                                <i :class="isInsurancePayment(row) ? 'pi pi-shield' : 'pi pi-wallet'"></i>
                            </div>

                            <div class="pay-info">
                                <span class="pay-patient">{{ row.patient || '—' }}</span>
                                <span class="pay-meta">
                                    <i class="pi pi-clock"></i> {{ formatDate(row.date, true) }}
                                    <span v-if="!props.hidePatientPhone && row.telephone">
                                        · <i class="pi pi-phone"></i> {{ row.telephone }}
                                    </span>
                                </span>
                            </div>

                            <div class="pay-tags">
                                <Tag :value="computeModeTag(row).label" :severity="computeModeTag(row).severity" />
                                <Tag v-if="isInsurancePayment(row)" value="Assurance" severity="info" icon="pi pi-shield" />
                                <Tag v-if="row.insuranceStatus === 'pending'"
                                    value="En attente" severity="warning" icon="pi pi-clock" />
                            </div>

                            <div class="pay-right">
                                <strong class="pay-amount"
                                    :class="isInsurancePayment(row) ? 'pay-amount--insurance' : 'pay-amount--client'">
                                    {{ formatFcfa(row.montant) }}
                                </strong>
                                <div class="pay-actions" data-tour="caisse-paiements.row-actions">
                                    <Button
                                        :icon="isInvoiceStylePayment(row) ? 'pi pi-print' : 'pi pi-ticket'"
                                        size="small" text rounded
                                        :title="isInvoiceStylePayment(row) ? 'Imprimer reçu' : 'Imprimer ticket'"
                                        @click="emit(isInvoiceStylePayment(row) ? 'print-payment' : 'print-receipt', row)" />
                                    <Button v-if="isInternetFeaturesEnabled" icon="pi pi-send" size="small" text rounded title="Envoyer par SMS"
                                        @click="emit('send-receipt-sms', row)" />
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

/* ─── ACCORDION PAIEMENTS ─── */

/* En-tête accordion */
.pay-accordion-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
    width: 100%;
}

.pay-mode-badge {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.82rem;
    font-weight: 700;
    color: #0f172a;
}

.pay-mode-badge .pi { color: #6366f1; }

.pay-accordion-count {
    font-size: 0.78rem;
    color: #64748b;
    background: #e2e8f0;
    border-radius: 20px;
    padding: 0.1rem 0.55rem;
}

.pay-accordion-total {
    margin-left: auto;
    font-size: 0.92rem;
    font-weight: 700;
    color: #059669;
}

/* Liste de transactions */
.pay-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.25rem 0;
}

/* Ligne de transaction */
.pay-row {
    display: flex;
    align-items: center;
    gap: 0.85rem;
    padding: 0.7rem 0.85rem;
    border-radius: 12px;
    border: 1px solid var(--surface-border);
    border-left: 4px solid transparent;
    background: var(--surface-card);
    flex-wrap: wrap;
    transition: background 0.15s ease;
}

.pay-row:hover {
    background: rgba(241, 245, 249, 0.7);
}

.pay-row--client   { border-left-color: #22c55e; }
.pay-row--insurance { border-left-color: #6366f1; background: rgba(99, 102, 241, 0.04); }

/* Icône rôle */
.pay-role-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.pay-row--client .pay-role-icon {
    background: #dcfce7;
    color: #16a34a;
}

.pay-row--insurance .pay-role-icon {
    background: #e0e7ff;
    color: #4f46e5;
}

.pay-role-icon .pi { font-size: 0.95rem; }

/* Info patient */
.pay-info {
    flex: 1 1 160px;
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pay-patient {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.pay-meta {
    font-size: 0.78rem;
    color: #64748b;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}

.pay-meta .pi { font-size: 0.72rem; }

/* Tags */
.pay-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    align-items: center;
}

/* Montant + actions */
.pay-right {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-left: auto;
}

.pay-amount {
    font-size: 1rem;
    font-weight: 700;
}

.pay-amount--client   { color: #059669; }
.pay-amount--insurance { color: #4f46e5; }

.pay-actions {
    display: flex;
    gap: 0.1rem;
}

/* Dark mode */
.app-dark .pay-mode-badge { color: #e2e8f0; }
.app-dark .pay-accordion-count { background: #334155; color: #94a3b8; }
.app-dark .pay-accordion-total { color: #4ade80; }

.app-dark .pay-row { background: rgba(30, 41, 59, 0.4); }
.app-dark .pay-row:hover { background: rgba(30, 41, 59, 0.7); }
.app-dark .pay-row--insurance { background: rgba(99, 102, 241, 0.08); }

.app-dark .pay-row--client .pay-role-icon {
    background: #14532d;
    color: #4ade80;
}

.app-dark .pay-row--insurance .pay-role-icon {
    background: #312e81;
    color: #a5b4fc;
}

.app-dark .pay-patient { color: #e2e8f0; }
.app-dark .pay-meta { color: #94a3b8; }
.app-dark .pay-amount--client { color: #4ade80; }
.app-dark .pay-amount--insurance { color: #a5b4fc; }
</style>
