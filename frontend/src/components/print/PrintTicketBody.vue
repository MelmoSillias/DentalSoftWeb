<template>
    <PrintTicketPage :logo-src="logoSrc">
        <PrintTicketHeader title="Ticket de consultation" />

        <hr />

        <table class="print-ticket-table small">
            <tbody>
                <tr>
                    <td>N° consultation</td>
                    <td class="right">{{ paiement?.consultation?.id || '—' }}</td>
                </tr>
                <tr>
                    <td>N° de passage</td>
                    <td class="right">{{ paiement?.consultation?.numeroPassage || '—' }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td class="right">{{ dateLabel }}</td>
                </tr>
                <tr>
                    <td>Patient</td>
                    <td class="right">
                        {{ paiement?.consultation?.patient?.nom || '—' }}
                        {{ paiement?.consultation?.patient?.prenom || '' }}
                    </td>
                </tr>
                <tr>
                    <td>Mode</td>
                    <td class="right">{{ paiement?.mode?.libelle || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <hr />

        <table class="print-ticket-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Ticket de consultation #{{ paiement?.consultation?.id || '—' }}</td>
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
    </PrintTicketPage>
</template>

<script setup>
import { computed } from 'vue';
import PrintTicketPage from './PrintTicketPage.vue';
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
.small {
    font-size: 12px;
    font-weight: 600;
}

.right {
    text-align: right;
}

.bold {
    font-weight: 800;
}
</style>
