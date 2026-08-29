<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader
                title="Encaissements paiements devis"
                :date="end"
                :doc-id="periodId"
            />
        </template>

        <p class="period">Période du {{ startLabel }} au {{ endLabel }}</p>

        <table class="print-doc-table">
            <thead>
                <tr>
                    <th>N° Devis</th>
                    <th>Patient</th>
                    <th style="text-align: right">Montant</th>
                    <th>Mode de paiement</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(p, idx) in paiements" :key="idx">
                    <td>DEV-{{ p?.devis?.id || '' }}</td>
                    <td>{{ p?.devis?.fiche?.patient?.nom || '—' }} {{ p?.devis?.fiche?.patient?.prenom || '' }}</td>
                    <td style="text-align: right">{{ formatMoney(p?.montant) }}</td>
                    <td>{{ p?.mode?.libelle || '—' }}</td>
                    <td>{{ formatDateTime(p?.date) }}</td>
                </tr>
                <tr v-if="!paiements.length">
                    <td colspan="5" class="empty">Aucun paiement effectué sur cette période</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" style="text-align: right">Total des encaissements</th>
                    <th style="text-align: right">{{ formatMoney(total) }}</th>
                    <th colspan="2" />
                </tr>
            </tfoot>
        </table>
    </PrintA4Page>
</template>

<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    paiements: { type: Array, default: () => [] },
    start: { type: [String, Date], default: '' },
    end: { type: [String, Date], default: '' },
    total: { type: Number, default: 0 },
    logoSrc: { type: String, default: logoImg }
});

const formatDate = (value) => {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('fr-FR');
};

const formatDateTime = (value) => {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return `${date.toLocaleDateString('fr-FR')} ${date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
};

const startLabel = computed(() => formatDate(props.start));
const endLabel = computed(() => formatDate(props.end));
const periodId = computed(() => {
    const start = startLabel.value.replace(/\//g, '');
    const end = endLabel.value.replace(/\//g, '');
    return start && end ? `${start}-${end}` : '';
});
const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
</script>

<style scoped>
.period {
    text-align: center;
    color: #586574;
    font-size: 10.5pt;
    margin: -4mm 0 10px;
}
</style>
