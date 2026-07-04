<template>
    <PrintTicketPage :logo-src="logoSrc">
        <PrintTicketHeader :title="title || 'Facture assurance'" />

        <hr />

        <table class="print-ticket-table small">
            <tbody>
                <tr>
                    <td>Facture N°</td>
                    <td class="right">{{ doc?.id || '—' }}</td>
                </tr>
                <tr>
                    <td>Date</td>
                    <td class="right">{{ doc?.dateFacture || '—' }}</td>
                </tr>
                <tr>
                    <td>Patient</td>
                    <td class="right">
                        {{ doc?.patient?.nom || '—' }}
                        {{ doc?.patient?.prenom || '' }}
                    </td>
                </tr>
                <tr>
                    <td>Téléphone</td>
                    <td class="right">{{ doc?.patient?.telephone || '—' }}</td>
                </tr>
            </tbody>
        </table>

        <hr />

        <div v-if="doc?.assurance" class="assurance-block">
            <p class="bold center">Prise en charge assurance</p>
            <table class="print-ticket-table small">
                <tbody>
                    <tr>
                        <td>Assurance</td>
                        <td class="right">{{ doc.assurance.nom || '—' }} ({{ doc.assurance.code || '—' }})</td>
                    </tr>
                    <tr>
                        <td>Taux couverture</td>
                        <td class="right">{{ doc.tauxCouverture ?? doc.assurance.tauxCouverture ?? '—' }}%</td>
                    </tr>
                    <tr>
                        <td>Montant total</td>
                        <td class="right">{{ formatMoney(doc.montantTotal) }}</td>
                    </tr>
                    <tr>
                        <td>Part assurance</td>
                        <td class="right">{{ formatMoney(doc.montantAssurance) }}</td>
                    </tr>
                    <tr>
                        <td>Part patient</td>
                        <td class="right">{{ formatMoney(doc.montantPatient) }}</td>
                    </tr>
                    <tr v-if="doc.assurance.restePatient != null">
                        <td>Reste patient</td>
                        <td class="right">{{ formatMoney(doc.assurance.restePatient) }}</td>
                    </tr>
                    <tr>
                        <td>Statut</td>
                        <td class="right">{{ doc.insuranceStatus || '—' }}</td>
                    </tr>
                    <tr v-if="doc.lot">
                        <td>Lot</td>
                        <td class="right">#{{ doc.lot.id }} — {{ doc.lot.statut }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <hr />

        <table class="print-ticket-table">
            <thead>
                <tr>
                    <th>Désignation</th>
                    <th class="right">Qté</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(line, index) in doc?.lignes || []" :key="index">
                    <td>{{ line.designation || '—' }}</td>
                    <td class="right">{{ line.quantite || 1 }}</td>
                    <td class="right">{{ formatMoney(line.total) }}</td>
                </tr>
            </tbody>
        </table>

        <template #footer>
            Document généré par DentalSoft<br />
            Tél : {{ cabinetPhone }}
        </template>
    </PrintTicketPage>
</template>

<script setup>
import { computed } from 'vue';
import PrintTicketPage from './PrintTicketPage.vue';
import PrintTicketHeader from './PrintTicketHeader.vue';
import logoImg from '@/assets/logo.png';
import cabinetConfig from '@/cabinetConfig';

defineProps({
    doc: { type: Object, default: null },
    title: { type: String, default: 'Facture assurance' }
});

const logoSrc = logoImg;
const cabinetPhone = computed(() => cabinetConfig.cabinetPhone || '—');

const formatMoney = (value) => {
    const amount = Number(value || 0);
    return `${amount.toLocaleString('fr-FR')} FCFA`;
};
</script>

<style scoped>
.small {
    font-size: 12px;
    font-weight: 600;
}

.right {
    text-align: right;
}

.center {
    text-align: center;
    font-weight: 700;
}

.bold {
    font-weight: 800;
}

.assurance-block {
    margin: 4px 0;
}
</style>
