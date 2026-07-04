<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader :title="title" :doc-id="docId" :date="date" />
        </template>

        <div class="payroll-slip">
            <p class="payroll-slip__title">Bulletin de paie</p>
            <p class="payroll-slip__period">{{ periodLabel }}</p>

            <section class="payroll-slip__section">
                <h2 class="payroll-slip__section-title">Identité du salarié</h2>
                <div class="payroll-slip__grid">
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Nom complet</span>
                        <span class="payroll-slip__value">{{ employeeName || '—' }}</span>
                    </div>
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Matricule</span>
                        <span class="payroll-slip__value">{{ matricule || '—' }}</span>
                    </div>
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Fonction</span>
                        <span class="payroll-slip__value">{{ employeeFonction || '—' }}</span>
                    </div>
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Fréquence</span>
                        <span class="payroll-slip__value">{{ frequenceLabel }}</span>
                    </div>
                </div>
            </section>

            <section class="payroll-slip__section">
                <h2 class="payroll-slip__section-title">Paramètres de rémunération</h2>
                <div class="payroll-slip__grid">
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Type de salaire</span>
                        <span class="payroll-slip__value payroll-slip__value--caps">{{ salaryTypeLabel }}</span>
                    </div>
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Valeur contractuelle</span>
                        <span class="payroll-slip__value">{{ formatSalaryValue(salaryValue, salaryType) }}</span>
                    </div>
                    <div v-if="primeType && primeType !== 'aucune'" class="payroll-slip__field">
                        <span class="payroll-slip__label">Type de prime</span>
                        <span class="payroll-slip__value payroll-slip__value--caps">{{ primeTypeDisplay }}</span>
                    </div>
                    <div v-if="primeType && primeType !== 'aucune'" class="payroll-slip__field">
                        <span class="payroll-slip__label">Valeur prime</span>
                        <span class="payroll-slip__value">{{ formatPrimeValue(primeValue, primeType) }}</span>
                    </div>
                </div>
            </section>

            <section class="payroll-slip__section">
                <h2 class="payroll-slip__section-title">Détail des éléments de paie</h2>
                <table class="payroll-slip__earnings">
                    <thead>
                        <tr>
                            <th>Libellé</th>
                            <th class="right">Base</th>
                            <th class="right">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Salaire de base</td>
                            <td class="right">{{ formatAmount(baseAmount) }}</td>
                            <td class="right">{{ formatAmount(baseSalaryAmount) }}</td>
                        </tr>
                        <tr v-if="primeAmount > 0">
                            <td>Prime</td>
                            <td class="right">{{ primeType === 'actes' ? formatAmount(primeBaseAmount) : '—' }}</td>
                            <td class="right">{{ formatAmount(primeAmount) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="2"><strong>Total calculé</strong></td>
                            <td class="right"><strong>{{ formatAmount(calculatedAmount) }}</strong></td>
                        </tr>
                        <tr>
                            <td colspan="2"><strong>Net versé</strong></td>
                            <td class="right"><strong>{{ formatAmount(paidAmount) }}</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </section>

            <section class="payroll-slip__section">
                <h2 class="payroll-slip__section-title">Informations de règlement</h2>
                <div class="payroll-slip__grid">
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Date de règlement</span>
                        <span class="payroll-slip__value">{{ paidAt || '—' }}</span>
                    </div>
                    <div class="payroll-slip__field">
                        <span class="payroll-slip__label">Mode de règlement</span>
                        <span class="payroll-slip__value">{{ paymentMethodLabel || '—' }}</span>
                    </div>
                    <div v-if="note" class="payroll-slip__field payroll-slip__field--full">
                        <span class="payroll-slip__label">Observations</span>
                        <span class="payroll-slip__value">{{ note }}</span>
                    </div>
                </div>
            </section>

            <section class="payroll-slip__signatures">
                <div class="payroll-slip__signature-box">
                    <p class="payroll-slip__signature-title">Cachet et visa du gestionnaire</p>
                    <div class="payroll-slip__signature-area" />
                </div>
                <div class="payroll-slip__signature-box">
                    <p class="payroll-slip__signature-title">Émargement du bénéficiaire</p>
                    <div class="payroll-slip__signature-area" />
                </div>
            </section>
        </div>
    </PrintA4Page>
</template>

<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';
import { formatPrimeTypeLabel, formatSalaryTypeLabel } from '@/utils/payrollUtils';

const props = defineProps({
    title: { type: String, default: 'Bulletin de paie' },
    date: { type: [String, Date], default: '' },
    docId: { type: [String, Number], default: '' },
    periodLabel: { type: String, default: '' },
    employeeName: { type: String, default: '' },
    employeeFonction: { type: String, default: '' },
    matricule: { type: String, default: '' },
    frequenceSnapshot: { type: String, default: 'mensuel' },
    salaryType: { type: String, default: '' },
    salaryValue: { type: Number, default: null },
    primeType: { type: String, default: 'aucune' },
    primeValue: { type: Number, default: null },
    primeBaseAmount: { type: Number, default: null },
    baseAmount: { type: Number, default: null },
    baseSalaryAmount: { type: Number, default: null },
    primeAmount: { type: Number, default: 0 },
    calculatedAmount: { type: Number, default: 0 },
    paidAmount: { type: Number, default: 0 },
    paidAt: { type: String, default: '' },
    paymentMethodLabel: { type: String, default: '' },
    note: { type: String, default: '' },
    logoSrc: { type: String, default: logoImg }
});

const frequenceLabel = computed(() => (props.frequenceSnapshot === 'journalier' ? 'JOURNALIER' : 'MENSUEL'));
const salaryTypeLabel = computed(() => formatSalaryTypeLabel(props.salaryType));
const primeTypeDisplay = computed(() => formatPrimeTypeLabel(props.primeType));

const formatAmount = (value) => {
    if (value === null || value === undefined) return '—';
    return `${Number(value).toLocaleString('fr-FR')} F CFA`;
};

const formatSalaryValue = (value, type) => {
    if (value === null || value === undefined) return '—';
    if (type === 'pourcentage') return `${value}%`;
    return formatAmount(value);
};

const formatPrimeValue = (value, type) => {
    if (value === null || value === undefined) return '—';
    if (type === 'actes') return `${value}%`;
    return formatAmount(value);
};
</script>

<style scoped>
.payroll-slip__title {
    text-align: center;
    font-size: 1.35rem;
    font-weight: 700;
    margin: 0 0 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.payroll-slip__period {
    text-align: center;
    margin: 0 0 1.25rem;
    color: #475569;
    font-size: 0.95rem;
}

.payroll-slip__section {
    margin-bottom: 1.1rem;
    break-inside: avoid;
}

.payroll-slip__section-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #334155;
    border-bottom: 1px solid #cbd5e1;
    padding-bottom: 0.35rem;
    margin: 0 0 0.65rem;
}

.payroll-slip__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.55rem 1rem;
}

.payroll-slip__field--full {
    grid-column: 1 / -1;
}

.payroll-slip__label {
    display: block;
    font-size: 0.72rem;
    color: #64748b;
    margin-bottom: 0.1rem;
}

.payroll-slip__value {
    display: block;
    font-size: 0.9rem;
    font-weight: 600;
    color: #0f172a;
}

.payroll-slip__value--caps {
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.payroll-slip__earnings {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.payroll-slip__earnings th,
.payroll-slip__earnings td {
    border: 1px solid #cbd5e1;
    padding: 0.45rem 0.55rem;
}

.payroll-slip__earnings th {
    background: #f8fafc;
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.payroll-slip__earnings tfoot td {
    background: #f1f5f9;
}

.right {
    text-align: right;
}

.payroll-slip__signatures {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1.5rem;
    margin-top: 1.5rem;
    break-inside: avoid;
}

.payroll-slip__signature-title {
    font-size: 0.78rem;
    font-weight: 600;
    margin: 0 0 0.5rem;
    color: #334155;
}

.payroll-slip__signature-area {
    height: 4.5rem;
    border: 1px dashed #94a3b8;
    border-radius: 4px;
    background: #fafafa;
}
</style>
