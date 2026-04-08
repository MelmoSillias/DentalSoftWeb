<script setup>
import { computed } from 'vue';
import AppLayout from '../../layout/AppLayout.vue';
import StatsGrid from './components/StatsGrid.vue';
import NextAppointmentCard from './components/NextAppointmentCard.vue';
import LastConsultationCard from './components/LastConsultationCard.vue';

const stats = computed(() => [
    { key: 'consultations', label: 'Consultations', value: 12, hint: 'Historique total' },
    { key: 'spent', label: 'Montant dépensé', value: '325 000 FCFA', hint: 'Cumul 12 mois' },
    { key: 'rdv', label: 'Rendez-vous', value: 5, hint: 'Programmés' },
    { key: 'docs', label: 'Documents', value: 8, hint: 'Factures et reçus' }
]);

const nextAppointment = {
    date: '12 avril 2026',
    time: '09:30',
    doctor: 'Dr. Keita',
    status: 'Confirmé'
};

const lastConsultation = {
    date: '30 mars 2026',
    doctor: 'Dr. Diallo',
    summary: 'Contrôle post-traitement, évolution favorable, prochaine visite recommandée dans 3 mois.'
};
</script>

<template>
    <AppLayout>
        <section class="dashboard-stack">
            <StatsGrid :stats="stats" />
            <NextAppointmentCard :appointment="nextAppointment" />
            <LastConsultationCard :consultation="lastConsultation" />
        </section>
    </AppLayout>
</template>

<style scoped>
.dashboard-stack {
    display: grid;
    gap: 0.9rem;
}
</style>
