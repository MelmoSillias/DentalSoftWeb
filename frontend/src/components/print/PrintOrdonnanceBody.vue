<template>
    <div class="print-ordo-container" :style="{ '--watermark': `url(${logoSrc})` }">
        <div class="print-ordo-content">
            <div class="ordo-header">
                <img :src="headerSrc" alt="Logo clinique" />
                <h1>Ordonnance</h1>
                <hr />
            </div>

            <div class="ordo-infos">
                <div>
                    <strong>Patient :</strong> {{ data?.patient || '—' }}<br />
                    <span class="ordo-date"><strong>Date :</strong> {{ formatDate(data?.date) }}</span>
                    <div v-if="data?.note" style="margin-top: 6px">{{ data.note }}</div>
                </div>
            </div>

            <table class="ordo-table">
                <thead>
                    <tr>
                        <th>Désignation</th>
                        <th width="30%">Posologie</th>
                        <th width="20%">Fréquence</th>
                        <th width="10%">Qté</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(ligne, idx) in data?.lignes || []" :key="idx">
                        <td>{{ ligne.designation || '—' }}</td>
                        <td>{{ ligne.posologie || '—' }} <small>unités</small></td>
                        <td>{{ ligne.frequence || '—' }} <small>par jour</small></td>
                        <td>{{ ligne.quantite ?? '—' }}</td>
                    </tr>
                    <tr v-if="!(data?.lignes && data.lignes.length)">
                        <td colspan="4">Aucune prescription renseignée.</td>
                    </tr>
                </tbody>
            </table>

            <div class="ordo-footer">Merci de suivre rigoureusement la posologie indiquée.</div>

            <div class="signature-zone">
                <hr />
                Signature et cachet du médecin
            </div>
        </div>
    </div>
</template>

<script setup>
import headerImg from '@/assets/header.jpeg';
import logoImg from '@/assets/logo.png';

defineProps({
    data: { type: Object, default: () => ({}) },
    headerSrc: { type: String, default: headerImg },
    logoSrc: { type: String, default: logoImg }
});

const formatDate = (value) => {
    if (!value) return '—';
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return date.toLocaleDateString('fr-FR');
};
</script>

<style scoped>
.print-ordo-container {
    position: relative;
    max-width: 210mm;
    margin: 12mm auto;
    padding: 18mm 20mm;
    background: #fff;
    box-shadow: 0 0 10mm rgba(0, 0, 0, 0.08);
    font-family: 'Times New Roman', Times, serif;
    color: #000;
}

.print-ordo-container::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--watermark) center no-repeat;
    background-size: 55%;
    opacity: 0.05;
    pointer-events: none;
}

.print-ordo-content {
    position: relative;
    z-index: 1;
}

.ordo-header {
    text-align: center;
    margin-bottom: 2.2rem;
}

.ordo-header img {
    max-height: 85px;
    margin-bottom: 1rem;
}

.ordo-header h1 {
    font-size: 1.8rem;
    margin: 0;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.ordo-header hr {
    margin-top: 1rem;
    border: none;
    border-top: 2px solid #000;
}

.ordo-infos {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.8rem;
    font-size: 1rem;
}

.ordo-infos div {
    line-height: 1.6;
}

.ordo-date {
    font-weight: bold;
    margin-top: 0.3rem;
}

.ordo-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2rem;
    font-size: 0.95rem;
}

.ordo-table thead th {
    text-align: left;
    border-bottom: 2px solid #000;
    padding: 8px 6px;
    font-weight: bold;
}

.ordo-table tbody td {
    padding: 10px 6px;
    vertical-align: top;
    border-bottom: 1px solid #ddd;
}

.ordo-table td:first-child {
    font-weight: bold;
}

.ordo-footer {
    font-size: 0.95rem;
    margin-top: 2.5rem;
    padding-top: 1rem;
    border-top: 1px solid #aaa;
    color: #333;
}

.signature-zone {
    margin-top: 3rem;
    width: 45%;
    margin-left: auto;
    text-align: center;
    font-size: 0.95rem;
}

.signature-zone hr {
    margin-bottom: 6px;
    border: none;
    border-top: 1px solid #000;
}
</style>
