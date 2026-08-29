<template>
    <PrintA4Page :logo-src="logoSrc">
        <template #header>
            <PrintDocumentHeader
                :title="title || 'Facture assurance'"
                :doc-id="doc?.id"
                :date="doc?.date || doc?.dateFacture"
            />
        </template>

        <div class="print-info-card">
            <div class="patient-infos">
                <strong>Patient :</strong>
                {{ doc?.patient?.nom || '—' }} {{ doc?.patient?.prenom || '' }}
            </div>
            <div class="muted">Tél : {{ doc?.patient?.telephone || 'Non renseigné' }}</div>
        </div>

        <div class="print-info-card assurance-card">
            <div class="assurance-title">
                <strong>Assurance :</strong>
                {{ doc?.assurance?.nom || doc?.assuranceSnapshot?.nom || '—' }}
                <span v-if="doc?.assurance?.code || doc?.assuranceSnapshot?.code" class="muted">
                    ({{ doc?.assurance?.code || doc?.assuranceSnapshot?.code }})
                </span>
            </div>

            <div v-if="assureFields.length" class="assure-grid">
                <div v-for="field in assureFields" :key="field.key" class="assure-field">
                    <span class="assure-label">{{ field.label }}</span>
                    <span class="assure-value">{{ field.value }}</span>
                </div>
            </div>
            <div v-else class="muted">Aucune information assuré renseignée.</div>
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
                <tr v-for="(ligne, idx) in lignes" :key="idx">
                    <td>
                        {{ ligne.designation }}
                        <span v-if="ligne.attribution === 'cabinet'" class="cabinet-tag">(Service cabinet)</span>
                    </td>
                    <td>{{ ligne.qte }}</td>
                    <td>{{ formatMoney(ligne.montant) }}</td>
                    <td>{{ formatMoney(ligne.total) }}</td>
                </tr>
                <tr v-if="!lignes.length">
                    <td colspan="4" class="empty">Aucune ligne.</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" style="text-align: right">Total TTC</th>
                    <th>{{ formatMoney(doc?.montantTotal ?? doc?.montant) }}</th>
                </tr>
                <tr>
                    <th colspan="3" style="text-align: right">Taux de couverture</th>
                    <th>{{ tauxCouvertureLabel }}</th>
                </tr>
                <tr>
                    <th colspan="3" style="text-align: right">Part assurance</th>
                    <th>{{ formatMoney(doc?.montantAssurance) }}</th>
                </tr>
                <tr>
                    <th colspan="3" style="text-align: right">Part patient</th>
                    <th>{{ formatMoney(doc?.montantPatient) }}</th>
                </tr>
                <tr v-if="restePatient != null">
                    <th colspan="3" style="text-align: right">Reste à payer</th>
                    <th>{{ formatMoney(restePatient) }}</th>
                </tr>
            </tfoot>
        </table>

        <p v-if="doc?.cabinetServicesFootnote || doc?.hasCabinetServices" class="cabinet-footnote">
            {{ doc?.cabinetServicesFootnote || 'Les services marqués « Service cabinet » sont facturés par le cabinet et ne relèvent pas de l\'honoraire du praticien.' }}
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

<script setup>
import { computed } from 'vue';
import PrintA4Page from './PrintA4Page.vue';
import PrintDocumentHeader from './PrintDocumentHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    doc: { type: Object, default: () => ({}) },
    title: { type: String, default: 'Facture assurance' },
    logoSrc: { type: String, default: logoImg }
});

const assureFields = computed(() =>
    Array.isArray(props.doc?.assureFields) ? props.doc.assureFields : []
);

const lignes = computed(() => {
    if (Array.isArray(props.doc?.contenus) && props.doc.contenus.length) {
        return props.doc.contenus;
    }
    return (props.doc?.lignes || []).map((line) => ({
        designation: line.designation,
        qte: line.quantite ?? line.qte ?? 1,
        montant: line.prix ?? line.montant ?? 0,
        total: line.total ?? 0,
        attribution: line.attribution ?? 'medecin'
    }));
});

const tauxCouvertureLabel = computed(() => {
    const rate = props.doc?.tauxCouverture ?? props.doc?.assurance?.tauxCouverture;
    return rate == null || rate === '' ? '—' : `${rate}%`;
});

const restePatient = computed(() => {
    if (props.doc?.restePatient != null) return props.doc.restePatient;
    if (props.doc?.assurance?.restePatient != null) return props.doc.assurance.restePatient;
    return null;
});

const formatMoney = (value) => {
    const num = Number(value || 0);
    return `${num.toLocaleString('fr-FR')} FCFA`;
};
</script>

<style scoped>
.assurance-card {
    margin-top: -4mm;
}

.assurance-title {
    margin-bottom: 8px;
}

.assure-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px 16px;
}

.assure-field {
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.assure-label {
    font-size: 8.5pt;
    color: #586574;
    font-weight: 600;
}

.assure-value {
    font-size: 10.5pt;
    color: #111827;
}

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
