<script setup>
import Accordion from 'primevue/accordion';
import AccordionContent from 'primevue/accordioncontent';
import AccordionHeader from 'primevue/accordionheader';
import AccordionPanel from 'primevue/accordionpanel';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { computed, ref } from 'vue';
import PanelDatePicker from '@/components/common/PanelDatePicker.vue';
import PageSection from '@/components/layout/PageSection.vue';
import { useInternetFeatures } from '@/composables/useInternetFeatures';

const { isInternetFeaturesEnabled } = useInternetFeatures();

const props = defineProps({
    payments: { type: Array, default: () => [] },
    paymentsLoading: { type: Boolean, default: false },
    paymentRange: { type: Array, default: () => [] },
    hidePatientPhone: { type: Boolean, default: false }
});

const emit = defineEmits(['update:paymentRange', 'refresh-payments', 'print-payments', 'print-payment', 'print-receipt', 'send-receipt-sms']);

const paymentRangeModel = computed({
    get: () => props.paymentRange,
    set: (val) => emit('update:paymentRange', val || [])
});

const paymentsSearch = ref('');
const paymentModeFilter = ref('all');

const normalizeText = (value) =>
    String(value ?? '')
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

const isInvoiceStylePayment = (payment) => ['devis', 'facture', 'facture_assurance', 'service_cabinet'].includes(payment?.type);
const isCabinetPayment = (payment) => payment?.type === 'service_cabinet';

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
    return list.filter((p) => {
        if (paymentModeFilter.value !== 'all' && Number(p?.modeId) !== Number(paymentModeFilter.value)) {
            return false;
        }

        return matchesQuery([p.patient, p.telephone, p.date, formatDate(p.date, true), p.montant, p.mode, p.type, p.insuranceStatus, computeModeTag(p).label], query);
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

const displayPhone = (value) => (props.hidePatientPhone ? "Masqué par l'administrateur" : value || '');

const paymentsByMode = computed(() => {
    const bucket = {};
    filteredPayments.value.forEach((p) => {
        const key = p.mode || 'Autre';
        bucket[key] = bucket[key] || [];
        bucket[key].push(p);
    });
    return bucket;
});

const paymentDayCount = computed(() => {
    const days = new Set();
    filteredPayments.value.forEach((payment) => {
        if (!payment?.date) return;
        const date = new Date(payment.date);
        if (Number.isNaN(date.getTime())) return;
        days.add(date.toISOString().slice(0, 10));
    });
    return days.size;
});
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="page-kpi-grid" data-tour="caisse-paiements.totals">
            <div class="page-kpi-card border-primary-200/70 bg-gradient-to-br from-primary-50/80 to-primary-100/50 dark:border-primary-800/40 dark:from-primary-900/30 dark:to-primary-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-primary-700 dark:text-primary-300">Paiements visibles</p>
                    <p class="page-kpi-value text-primary-900 dark:text-primary-100">{{ totals.count }}</p>
                </div>
                <i class="pi pi-wallet page-kpi-icon text-primary-500"></i>
            </div>
            <div class="page-kpi-card border-emerald-200/70 bg-gradient-to-br from-emerald-50/80 to-emerald-100/50 dark:border-emerald-800/40 dark:from-emerald-900/20 dark:to-emerald-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-emerald-700 dark:text-emerald-300">Encaissements (période)</p>
                    <p class="page-kpi-value truncate text-emerald-900 dark:text-emerald-100">{{ formatFcfa(totals.montant) }}</p>
                </div>
                <i class="pi pi-chart-line page-kpi-icon text-emerald-500"></i>
            </div>
            <div class="page-kpi-card border-sky-200/70 bg-gradient-to-br from-sky-50/80 to-sky-100/50 dark:border-sky-800/40 dark:from-sky-900/20 dark:to-sky-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-sky-700 dark:text-sky-300">Modes de paiement</p>
                    <p class="page-kpi-value text-sky-900 dark:text-sky-100">{{ Object.keys(paymentsByMode).length }}</p>
                </div>
                <i class="pi pi-credit-card page-kpi-icon text-sky-500"></i>
            </div>
            <div class="page-kpi-card border-slate-200/70 bg-gradient-to-br from-slate-50/80 to-slate-100/50 dark:border-slate-800/40 dark:from-slate-900/20 dark:to-slate-800/20">
                <div class="min-w-0 flex-1">
                    <p class="page-kpi-label text-slate-600 dark:text-slate-300">Jours avec encaissement</p>
                    <p class="page-kpi-value text-slate-900 dark:text-surface-100">{{ paymentDayCount }}</p>
                </div>
                <i class="pi pi-calendar page-kpi-icon text-slate-500"></i>
            </div>
        </div>

        <PageSection title="Paiements" subtitle="Période, montants et ventilation par mode de paiement." tour-id="caisse-paiements.section">
            <template #headerActions>
                <div class="filters" data-tour="caisse-paiements.filters">
                    <div class="filter-item">
                        <label>Recherche</label>
                        <InputText v-model="paymentsSearch" placeholder="Recherche..." fluid />
                    </div>
                    <div class="filter-item">
                        <label>Période</label>
                        <PanelDatePicker v-model="paymentRangeModel" dateFormat="yy-mm-dd" showIcon fluid placeholder="Période" />
                    </div>
                    <div class="filter-item">
                        <label>Mode de paiement</label>
                        <Select v-model="paymentModeFilter" :options="paymentModeOptions" optionLabel="label" optionValue="value" placeholder="Mode" fluid />
                    </div>
                    <Button icon="pi pi-print" severity="primary" aria-label="Imprimer la période" v-tooltip.top="'Imprimer la période'" class="!px-2" @click="emit('print-payments')" />
                    <Button icon="pi pi-refresh" text aria-label="Rafraîchir" v-tooltip.top="'Rafraîchir'" class="!px-2" @click="emit('refresh-payments')" />
                </div>
            </template>

            <div class="flex flex-col gap-4 p-3 sm:p-4">
                <Accordion v-if="Object.keys(paymentsByMode).length" multiple data-tour="caisse-paiements.accordion">
                <AccordionPanel v-for="(list, mode) in paymentsByMode" :key="mode" :value="String(mode)">
                    <AccordionHeader>
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
                    </AccordionHeader>
                    <AccordionContent>
                        <div class="pay-grid">
                            <article
                                v-for="row in list"
                                :key="row.pId"
                                class="pay-receipt"
                                :class="isInsurancePayment(row) ? 'pay-receipt--insurance' : 'pay-receipt--client'"
                            >
                                <header class="pay-receipt-top">
                                    <div class="pay-receipt-brand">
                                        <span class="pay-receipt-type">
                                            {{ isInvoiceStylePayment(row) ? 'REÇU DE PAIEMENT' : 'TICKET' }}
                                        </span>
                                        <span class="pay-receipt-number">N° {{ row.pId }}</span>
                                    </div>
                                    <div class="pay-receipt-meta">
                                        <span class="pay-receipt-date">{{ formatDate(row.date, true) }}</span>
                                        <Tag :value="computeModeTag(row).label" :severity="isInsurancePayment(row) ? 'info' : 'success'" />
                                    </div>
                                </header>

                                <div class="pay-receipt-divider" aria-hidden="true"></div>

                                <section class="pay-receipt-party">
                                    <p class="pay-receipt-party-label">Reçu de</p>
                                    <p class="pay-receipt-party-name">{{ row.patient || '—' }}</p>
                                    <p v-if="displayPhone(row.telephone)" class="pay-receipt-party-line">Tél. {{ displayPhone(row.telephone) }}</p>
                                    <p v-if="isCabinetPayment(row) || isInsurancePayment(row) || row.insuranceStatus === 'pending'" class="pay-receipt-tags">
                                        <Tag v-if="isCabinetPayment(row)" value="Service cabinet" severity="warn" icon="pi pi-building" />
                                        <Tag v-if="isInsurancePayment(row)" value="Assurance" severity="info" icon="pi pi-shield" />
                                        <Tag v-if="row.insuranceStatus === 'pending'" value="En attente" severity="warn" />
                                    </p>
                                </section>

                                <table class="pay-receipt-table">
                                    <thead>
                                        <tr>
                                            <th>Libellé</th>
                                            <th class="pay-col-amount">Montant</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Mode · {{ row.mode || '—' }}</td>
                                            <td class="pay-col-amount">{{ formatFcfa(row.montant) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>Montant encaissé</td>
                                            <td
                                                class="pay-col-amount pay-total"
                                                :class="isInsurancePayment(row) ? 'pay-total--insurance' : 'pay-total--client'"
                                            >
                                                {{ formatFcfa(row.montant) }}
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>

                                <footer class="pay-receipt-actions" data-tour="caisse-paiements.row-actions">
                                    <Button
                                        :icon="isInvoiceStylePayment(row) ? 'pi pi-print' : 'pi pi-ticket'"
                                        :label="isInvoiceStylePayment(row) ? 'Reçu' : 'Ticket'"
                                        size="small"
                                        severity="secondary"
                                        outlined
                                        @click="emit(isInvoiceStylePayment(row) ? 'print-payment' : 'print-receipt', row)"
                                    />
                                    <Button
                                        v-if="isInternetFeaturesEnabled"
                                        icon="pi pi-send"
                                        label="SMS"
                                        size="small"
                                        severity="help"
                                        text
                                        @click="emit('send-receipt-sms', row)"
                                    />
                                </footer>
                            </article>
                        </div>
                    </AccordionContent>
                </AccordionPanel>
            </Accordion>
                <div v-else class="hint">Aucun paiement à afficher pour cette période.</div>
            </div>
        </PageSection>
    </div>
</template>

<style scoped>
.hint {
    color: #6b7280;
    font-size: 0.9rem;
}

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
    color: var(--text-color);
}

.pay-mode-badge .pi {
    color: var(--text-color-secondary);
}

.pay-accordion-count {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
    border: 1px solid color-mix(in srgb, var(--surface-border) 85%, transparent);
    border-radius: var(--page-section-radius);
    padding: 0.1rem 0.55rem;
}

.pay-accordion-total {
    margin-left: auto;
    font-size: 0.92rem;
    font-weight: 700;
    color: var(--text-color);
    font-variant-numeric: tabular-nums;
}

/* Grille reçus : 1 → 2 → 3 colonnes */
.pay-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
    padding: 0.35rem 0 0.15rem;
}

@media (min-width: 768px) {
    .pay-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (min-width: 1200px) {
    .pay-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}

.pay-receipt {
    display: flex;
    flex-direction: column;
    border: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    border-radius: var(--page-section-radius);
    background: var(--surface-card);
    box-shadow: 0 1px 2px color-mix(in srgb, var(--text-color) 4%, transparent);
    overflow: hidden;
    min-height: 100%;
}

.pay-receipt-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.85rem 1rem 0.65rem;
}

.pay-receipt-brand {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.pay-receipt-type {
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-color-secondary);
}

.pay-receipt-number {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-color);
    font-variant-numeric: tabular-nums;
}

.pay-receipt-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 0.35rem;
    flex-shrink: 0;
}

.pay-receipt-date {
    font-size: 0.78rem;
    color: var(--text-color-secondary);
    font-variant-numeric: tabular-nums;
    text-align: right;
}

.pay-receipt-divider {
    height: 1px;
    margin: 0 1rem;
    background: color-mix(in srgb, var(--surface-border) 85%, transparent);
}

.pay-receipt-party {
    padding: 0.75rem 1rem 0.5rem;
}

.pay-receipt-party-label {
    margin: 0 0 0.2rem;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--text-color-secondary);
}

.pay-receipt-party-name {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--text-color);
    line-height: 1.3;
}

.pay-receipt-party-line {
    margin: 0.25rem 0 0;
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}

.pay-receipt-tags {
    margin: 0.45rem 0 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
}

.pay-receipt-table {
    width: calc(100% - 2rem);
    margin: 0.35rem 1rem 0.75rem;
    border-collapse: collapse;
    font-size: 0.82rem;
}

.pay-receipt-table th {
    text-align: left;
    font-size: 0.68rem;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--text-color-secondary);
    border-bottom: 1px solid color-mix(in srgb, var(--surface-border) 85%, transparent);
    padding: 0.35rem 0;
}

.pay-receipt-table td {
    padding: 0.4rem 0;
    color: var(--text-color);
    border-bottom: 1px solid color-mix(in srgb, var(--surface-border) 55%, transparent);
}

.pay-receipt-table tfoot td {
    border-bottom: none;
    border-top: 1px solid color-mix(in srgb, var(--surface-border) 85%, transparent);
    padding-top: 0.55rem;
    font-weight: 700;
    color: var(--text-color);
}

.pay-col-amount {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.pay-total--client {
    color: #059669;
}

.pay-total--insurance {
    color: #1d4ed8;
}

.pay-receipt-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.65rem 1rem 0.85rem;
    margin-top: auto;
    border-top: 1px solid color-mix(in srgb, var(--surface-border) 80%, transparent);
    background: color-mix(in srgb, var(--surface-card) 92%, var(--text-color) 4%);
}

.app-dark .pay-total--client {
    color: #4ade80;
}

.app-dark .pay-total--insurance {
    color: #93c5fd;
}
</style>
