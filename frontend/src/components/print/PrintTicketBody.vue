<template>
    <PrintTicketPage :logo-src="logoSrc">
        <PrintTicketHeader title="Ticket de consultation" />

        <hr />

        <table class="print-ticket-table small">
            <tbody>
                <tr>
                    <td>N° consultation</td>
                    <td class="right">{{ consultationIdLabel }}</td>
                </tr>
                <tr>
                    <td>N° de passage</td>
                    <td class="right">{{ numeroPassageLabel }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td class="right">{{ dateLabel }}</td>
                </tr>
                <tr>
                    <td>Patient</td>
                    <td class="right">{{ patientLabel }}</td>
                </tr>
                <tr>
                    <td>Mode</td>
                    <td class="right">{{ modeLabel }}</td>
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
                    <td>Ticket de consultation #{{ consultationIdLabel }}</td>
                    <td class="right">{{ formatMoney(props.paiement?.montant) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td class="bold">Total payé</td>
                    <td class="right bold">{{ formatMoney(props.paiement?.montant) }}</td>
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

const consultation = computed(() => props.paiement?.consultation || null);

const consultationId = computed(() => {
    const value =
        consultation.value?.id ??
        props.paiement?.consultationId ??
        props.paiement?.consultation_id ??
        null;
    const numeric = Number(value);
    return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
});

const numeroPassage = computed(() => {
    const value =
        consultation.value?.numeroPassage ??
        consultation.value?.numero_passage ??
        props.paiement?.numeroPassage ??
        props.paiement?.numero_passage ??
        null;
    const numeric = Number(value);
    return Number.isFinite(numeric) && numeric > 0 ? numeric : null;
});

const consultationIdLabel = computed(() => consultationId.value ?? '—');
const numeroPassageLabel = computed(() => numeroPassage.value ?? '—');

const patientLabel = computed(() => {
    const patient = consultation.value?.patient;
    const nom = String(patient?.nom || '').trim();
    const prenom = String(patient?.prenom || '').trim();
    const full = `${nom} ${prenom}`.trim();
    return full || '—';
});

const modeLabel = computed(() => props.paiement?.mode?.libelle || '—');

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
