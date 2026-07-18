<template>
    <PrintTicketPage :logo-src="logoSrc">
        <PrintTicketHeader title="Reçu de paiement" />

        <hr />

        <table class="print-ticket-table small">
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
                <tr>
                    <td>Facture N°</td>
                    <td class="right">{{ paiement?.devis?.id || '—' }}</td>
                </tr>
                <tr>
                    <td>Montant facture</td>
                    <td class="right">{{ formatMoney(paiement?.devis?.total) }}</td>
                </tr>
                <tr>
                    <td>Reste à payer</td>
                    <td class="right">{{ formatMoney(paiement?.devis?.reste) }}</td>
                </tr>
            </tbody>
        </table>

        <hr />

        <div v-if="paiement?.assurance" class="assurance-block">
            <p class="bold center">Informations assurance</p>
            <table class="print-ticket-table small">
                <tbody>
                    <tr>
                        <td>Assurance</td>
                        <td class="right">{{ paiement.assurance.nom || '—' }} ({{ paiement.assurance.code || '—' }})</td>
                    </tr>
                    <tr>
                        <td>Taux</td>
                        <td class="right">{{ paiement.assurance.tauxCouverture ?? '—' }}%</td>
                    </tr>
                    <tr>
                        <td>Montant total</td>
                        <td class="right">{{ formatMoney(paiement.assurance.montantTotal) }}</td>
                    </tr>
                    <tr>
                        <td>Part assurance</td>
                        <td class="right">{{ formatMoney(paiement.assurance.montantAssurance) }}</td>
                    </tr>
                    <tr>
                        <td>Part patient</td>
                        <td class="right">{{ formatMoney(paiement.assurance.montantPatient) }}</td>
                    </tr>
                    <tr>
                        <td>Reste patient</td>
                        <td class="right">{{ formatMoney(paiement.assurance.restePatient) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <hr v-if="paiement?.assurance" />

        <table class="print-ticket-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="right">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ paiement?.assurance ? 'Paiement facture assurance' : 'Paiement devis' }} #{{ paiement?.devis?.id || '—' }}</td>
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

.center {
    text-align: center;
    font-weight: 700;
}

.right {
    text-align: right;
}

.bold {
    font-weight: 800;
}

.assurance-block {
    margin: 4px 0;
}
</style>
