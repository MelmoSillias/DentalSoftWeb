<template>
    <div class="payments-list">
        <h1>Recettes Paiements Devis</h1>
        <h3>Du {{ startLabel }} au {{ endLabel }}</h3>

        <table>
            <thead>
                <tr>
                    <th>N° Devis</th>
                    <th>Patient</th>
                    <th>Montant</th>
                    <th>Mode de Paiement</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(p, idx) in paiements" :key="idx">
                    <td>DEV-{{ p?.devis?.id || '' }}</td>
                    <td>{{ p?.devis?.fiche?.patient?.nom || '—' }} {{ p?.devis?.fiche?.patient?.prenom || '' }}</td>
                    <td class="right">{{ formatMoney(p?.montant) }}</td>
                    <td>{{ p?.mode?.libelle || '—' }}</td>
                    <td>{{ formatDateTime(p?.date) }}</td>
                </tr>
                <tr v-if="!paiements.length">
                    <td colspan="5" class="center">Aucun paiement effectué sur cette période</td>
                </tr>
            </tbody>
        </table>

        <p class="total">Total des recettes : {{ formatMoney(total) }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    paiements: { type: Array, default: () => [] },
    start: { type: [String, Date], default: '' },
    end: { type: [String, Date], default: '' },
    total: { type: Number, default: 0 }
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
const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
</script>

<style scoped>
.payments-list {
    font-family: sans-serif;
    margin: 20px auto;
    color: #333;
}

h1,
h3 {
    text-align: center;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 30px;
}

td,
th {
    padding: 10px;
    border: 1px solid #ddd;
    font-size: 14px;
}

.right {
    text-align: right;
}

.center {
    text-align: center;
}

.total {
    margin-top: 20px;
    font-weight: bold;
    text-align: right;
}
</style>
