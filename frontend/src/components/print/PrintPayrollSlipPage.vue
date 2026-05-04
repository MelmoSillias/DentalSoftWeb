<template>
    <div class="page">
        <div class="content">
            <PrintDocumentHeader :title="title" :doc-id="docId" :date="date" />

            <div class="subtitle">Fiche de paie - {{ periodLabel }}</div>

            <table class="table">
                <tbody>
                    <tr>
                        <th>Employe</th>
                        <td>{{ employeeName }}</td>
                    </tr>
                    <tr>
                        <th>Fonction</th>
                        <td>{{ employeeFonction || '-' }}</td>
                    </tr>
                    <tr>
                        <th>Type de salaire</th>
                        <td>{{ salaryType || '-' }}</td>
                    </tr>
                    <tr>
                        <th>Valeur salaire</th>
                        <td>{{ formatAmount(salaryValue) }}</td>
                    </tr>
                    <tr>
                        <th>Base mensuelle</th>
                        <td>{{ formatAmount(baseAmount) }}</td>
                    </tr>
                    <tr>
                        <th>Montant calcule</th>
                        <td><strong>{{ formatAmount(calculatedAmount) }}</strong></td>
                    </tr>
                    <tr>
                        <th>Montant verse</th>
                        <td><strong>{{ formatAmount(paidAmount) }}</strong></td>
                    </tr>
                    <tr>
                        <th>Date paiement</th>
                        <td>{{ paidAt || '-' }}</td>
                    </tr>
                    <tr>
                        <th>Note</th>
                        <td>{{ note || '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import PrintDocumentHeader from './PrintDocumentHeader.vue';

const props = defineProps({
    title: { type: String, default: 'Fiche de paie' },
    date: { type: [String, Date], default: '' },
    docId: { type: [String, Number], default: '' },
    periodLabel: { type: String, default: '' },
    employeeName: { type: String, default: '' },
    employeeFonction: { type: String, default: '' },
    salaryType: { type: String, default: '' },
    salaryValue: { type: Number, default: null },
    baseAmount: { type: Number, default: null },
    calculatedAmount: { type: Number, default: 0 },
    paidAmount: { type: Number, default: 0 },
    paidAt: { type: String, default: '' },
    note: { type: String, default: '' }
});

const formatAmount = (value) => {
    if (value === null || value === undefined) return '-';
    return `${Number(value).toLocaleString('fr-FR')} F CFA`;
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
}

.subtitle {
    color: #586574;
    font-size: 11pt;
    margin: 6px 0 14px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11pt;
}

.table th,
.table td {
    border: 1px solid #cfd8e3;
    padding: 8px 10px;
    vertical-align: top;
}

.table th {
    width: 35%;
    background: #f6f8fb;
    text-align: left;
    font-weight: 700;
}
</style>
