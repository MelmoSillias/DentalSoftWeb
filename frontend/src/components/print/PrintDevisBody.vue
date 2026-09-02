<script setup>
import PrintA4Page from './PrintA4Page.vue';
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

<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader :title="title" :doc-id="doc?.id" :date="doc?.date" />
        </template>

        <div class="print-info-card">
            <div class="patient-infos">
                <strong>Patient :</strong>
                {{ doc?.patient?.nom || '—' }} {{ doc?.patient?.prenom || '' }}
            </div>
            <div class="muted">Tél : {{ doc?.patient?.telephone || 'Non renseigné' }}</div>
        </div>

        <table class="print-doc-table">
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
                    <td>
                        {{ ligne.designation }}
                        <span v-if="ligne.attribution === 'cabinet'" class="cabinet-tag">(Service cabinet)</span>
                    </td>
                    <td>{{ ligne.qte }}</td>
                    <td>{{ formatMoney(ligne.montant) }}</td>
                    <td>{{ formatMoney(ligne.total) }}</td>
                </tr>
                <tr v-if="!(doc?.contenus && doc.contenus.length)">
                    <td colspan="4" class="empty">Aucune ligne.</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" style="text-align: right">Total TTC</th>
                    <th>{{ formatMoney(doc?.montant) }}</th>
                </tr>
                <tr v-if="doc?.reste !== undefined && doc?.reste !== null">
                    <th colspan="3" style="text-align: right">Reste à payer</th>
                    <th>{{ formatMoney(doc?.reste) }}</th>
                </tr>
            </tfoot>
        </table>

        <p v-if="doc?.cabinetServicesFootnote || doc?.hasCabinetServices" class="cabinet-footnote">
            {{ doc?.cabinetServicesFootnote || "Les services marqués « Service cabinet » sont facturés par le cabinet et ne relèvent pas de l'honoraire du praticien." }}
        </p>

        <div class="sign-row">
            <div class="sign-col">
                <p>Signature du patient</p>
                <div class="sign-line" />
            </div>
            <div class="sign-col">
                <p>Cachet du cabinet</p>
                <div class="sign-line" />
            </div>
        </div>
    </PrintA4Page>
</template>

<style scoped>
.sign-row {
    margin-top: 20px;
    display: flex;
    justify-content: space-between;
    gap: 20px;
}

.sign-col {
    width: 48%;
    text-align: center;
    color: #586574;
    font-size: 10pt;
}

.sign-line {
    border-top: 1px solid #ccc;
    width: 85%;
    margin: 24px auto 0;
    height: 1px;
}

.cabinet-tag {
    margin-left: 0.35rem;
    font-size: 9pt;
    color: #b45309;
    font-weight: 600;
}

.cabinet-footnote {
    margin-top: 12px;
    font-size: 9pt;
    color: #92400e;
    line-height: 1.4;
}
</style>
