<template>
    <div class="ticket" :style="{ '--watermark': `url(${logoSrc})` }">
        <div class="small content">
            <PrintTicketHeader title="Reçu de paiement" />

            <hr />

            <table class="small">
                <tbody>
                    <tr>
                        <td>Reçu N°</td>
                        <td class="right">{{ paiement?.id || '—' }}</td>
                    </tr>
                    <tr>
                        <td>Date</td>
                        <td class="right">{{ dateLabel }}</td>
                    </tr>
                    <tr>
                        <td>Patient</td>
                        <td class="right">
                            {{ paiement?.devis?.fiche?.patient?.nom || '—' }}
                            {{ paiement?.devis?.fiche?.patient?.prenom || '' }}
                        </td>
                    </tr>
                    <tr>
                        <td>Mode</td>
                        <td class="right">{{ paiement?.mode?.libelle || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <hr />

            <table>
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Paiement devis #{{ paiement?.devis?.id || '—' }}</td>
                        <td class="right">{{ formatMoney(paiement?.montant) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="bold">Total payé</td>
                        <td class="right bold">{{ formatMoney(paiement?.montant) }}</td>
                    </tr>
                </tfoot>
            </table>

            <hr />

            <div class="small center">
                Merci de votre confiance !<br />
                Tél: +223 77 27 28 61 / +223 44 51 61 85
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import PrintTicketHeader from './PrintTicketHeader.vue';
import logoImg from '@/assets/logo.png';

const props = defineProps({
    paiement: { type: Object, default: () => ({}) },
    logoSrc: { type: String, default: logoImg }
});

const dateLabel = computed(() => {
    const raw = props.paiement?.date ? new Date(props.paiement.date) : null;
    if (!raw || Number.isNaN(raw.getTime())) return props.paiement?.date || '—';
    return `${raw.toLocaleDateString('fr-FR')} ${raw.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`;
});

const formatMoney = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;
</script>

<style scoped>
.ticket {
    position: relative;
    width: 80mm;
    padding: 2mm;
    color: #000;
    font-family: Arial, sans-serif;
    font-size: 14px;
    overflow: hidden;
}

.ticket::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--watermark) center center no-repeat;
    background-size: 70% auto;
    opacity: 0.06;
    pointer-events: none;
    z-index: 0;
}

.content {
    position: relative;
    z-index: 1;
}

.small {
    font-size: 12px;
}

.center {
    text-align: center;
}

.right {
    text-align: right;
}

.bold {
    font-weight: 700;
}

hr {
    border: none;
    border-top: 1px dashed #000;
    margin: 3px 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 3px 0;
}

th,
td {
    padding: 1px 0;
}

th {
    font-weight: 700;
}
</style>
