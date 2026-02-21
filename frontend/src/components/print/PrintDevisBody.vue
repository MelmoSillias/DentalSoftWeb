<template>
    <div class="page" :style="{ '--watermark': `url(${logoSrc})` }">
        <div class="content">
            <PrintDocumentHeader :title="title" :doc-id="doc?.id" :date="doc?.date" />

            <div class="card patient">
                <div>
                    <div class="patient-infos">
                        <strong>Patient :</strong>
                        {{ doc?.patient?.nom || '—' }} {{ doc?.patient?.prenom || '' }}
                    </div>
                    <div class="muted">Tel : {{ doc?.patient?.telephone || 'Non renseigné' }}</div>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th style="width: 8%">Qté</th>
                        <th style="width: 22%">Prix unitaire</th>
                        <th style="width: 22%">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, idx) in doc?.contenus || []" :key="idx">
                        <td>{{ ligne.designation }}</td>
                        <td>{{ ligne.qte }}</td>
                        <td>{{ formatMoney(ligne.montant) }}</td>
                        <td>{{ formatMoney(ligne.total) }}</td>
                    </tr>
                    <tr v-if="!(doc?.contenus && doc.contenus.length)">
                        <td colspan="4">Aucune ligne.</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="totals">
                        <th colspan="3">Total TTC</th>
                        <th>{{ formatMoney(doc?.montant) }}</th>
                    </tr>
                    <tr v-if="doc?.reste !== undefined && doc?.reste !== null" class="totals">
                        <th colspan="3">Reste à payer</th>
                        <th>{{ formatMoney(doc?.reste) }}</th>
                    </tr>
                </tfoot>
            </table>

            <div class="sign-row">
                <div class="sign-col">
                    <p>Signature du patient</p>
                    <div class="sign-line"></div>
                </div>
                <div class="sign-col">
                    <p>Cachet de la clinique</p>
                    <div class="sign-line"></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import PrintDocumentHeader from './PrintDocumentHeader.vue';

import logoImg from '@/assets/logo.png';

defineProps({
    doc: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Devis' },
    logoSrc: { type: String, default: logoImg }
});

const formatMoney = (value) => {
    const num = Number(value || 0);
    return `${num.toLocaleString('fr-FR')} FCFA`;
};
</script>

<style scoped>
.page {
    position: relative;
    max-width: 210mm;
    margin: 12mm auto;
    padding: 14mm 16mm;
    background: #fff;
    box-shadow: 0 0 8mm rgba(0, 0, 0, 0.08);
    overflow: hidden;
}

.page::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--watermark) center center no-repeat;
    background-size: 70%;
    opacity: 0.1;
    pointer-events: none;
    z-index: 0;
}

.content {
    position: relative;
    z-index: 1;
}

.card {
    border: 1px solid #cfd8e3;
    padding: 12px 14px;
    background: #fafbfd9a;
}

.patient {
    margin-bottom: 12mm;
    font-size: 14pt;
    display: flex;
    justify-content: space-between;
    gap: 14px;
}

.muted {
    color: #586574;
    font-size: 11pt;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 11pt;
}

th,
td {
    border: 1px solid #cfd8e3;
    padding: 9px 10px;
    vertical-align: top;
}

th {
    background: #eef2f783;
    text-align: left;
    font-weight: 700;
}

tfoot th {
    text-align: right;
}

.totals th {
    font-size: 13pt;
}

.sign-row {
    margin-top: 28px;
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

.sign-col {
    width: 48%;
    text-align: center;
    color: #586574;
}

.sign-line {
    border-top: 1px solid #ccc;
    width: 85%;
    margin: 28px auto 0;
    height: 1px;
}
</style>
