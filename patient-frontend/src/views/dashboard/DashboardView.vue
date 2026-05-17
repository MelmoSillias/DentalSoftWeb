<script setup>
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../../layout/AppLayout.vue';
import StatsGrid from './components/StatsGrid.vue';
import NextAppointmentCard from './components/NextAppointmentCard.vue';
import LastConsultationCard from './components/LastConsultationCard.vue';
import { useAuthStore } from '../../stores/auth';
import { fetchPatientAppointments, fetchPatientConsultations, fetchPatientDashboard, fetchPatientPayments } from '../../services/patientPortal';
import Divider from 'primevue/divider';

const authStore = useAuthStore();
const dashboardStats = ref({
    consultations: 0,
    rdvs: 0,
    devisFactures: 0,
    paiements: 0
});
const totalPaid = ref(0);
const appointments = ref([]);
const consultations = ref([]);
const loadError = ref('');
const isLoading = ref(true);

const stats = computed(() => [
    { key: 'consultations', label: 'Consultations', value: dashboardStats.value.consultations, hint: 'Historique total', icon: 'pi pi-stethoscope', color: 'blue' },
    { key: 'spent', label: 'Montant dépensé', value: `${Math.round(totalPaid.value).toLocaleString('fr-FR')} FCFA`, hint: 'Cumul paiements', icon: 'pi pi-wallet', color: 'green' },
    { key: 'rdv', label: 'Rendez-vous', value: dashboardStats.value.rdvs, hint: 'Suivi patient', icon: 'pi pi-calendar', color: 'orange' },
    { key: 'docs', label: 'Documents', value: dashboardStats.value.devisFactures, hint: 'Devis et factures', icon: 'pi pi-file', color: 'purple' }
]);

const statsEmpty = computed(() => {
    return (
        dashboardStats.value.consultations === 0
        && dashboardStats.value.rdvs === 0
        && dashboardStats.value.devisFactures === 0
        && totalPaid.value === 0
    );
});

const appointmentsEmpty = computed(() => appointments.value.length === 0);
const consultationsEmpty = computed(() => consultations.value.length === 0);

const nextAppointment = computed(() => {
    const upcoming = appointments.value
        .filter((item) => item?.dateRdv)
        .sort((a, b) => new Date(a.dateRdv).getTime() - new Date(b.dateRdv).getTime())[0];

    if (!upcoming) {
        return {
            date: '--',
            time: '--',
            doctor: '--',
            status: 'Aucun'
        };
    }

    const when = new Date(upcoming.dateRdv);
    return {
        date: when.toLocaleDateString('fr-FR'),
        time: when.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }),
        doctor: upcoming?.medecin?.nom || '--',
        status: upcoming?.statut || 'Planifié'
    };
});

const lastConsultation = computed(() => {
    const latest = consultations.value
        .filter((item) => item?.date)
        .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime())[0];

    if (!latest) {
        return {
            date: '--',
            doctor: '--',
            summary: 'Aucune consultation disponible pour le moment.'
        };
    }

    return {
        date: new Date(latest.date).toLocaleDateString('fr-FR'),
        doctor: latest?.medecin?.nom || '--',
        summary: latest?.noteSeance || latest?.type || 'Consultation enregistrée.'
    };
});

onMounted(async () => {
    authStore.hydrate();
    const token = authStore.token;
    if (!token) {
        isLoading.value = false;
        return;
    }

    const [dashboard, rdvs, consults, payments] = await Promise.allSettled([
        fetchPatientDashboard(token),
        fetchPatientAppointments(token),
        fetchPatientConsultations(token),
        fetchPatientPayments(token)
    ]);

    if (dashboard.status === 'fulfilled') {
        dashboardStats.value = dashboard.value?.stats || dashboardStats.value;
    } else {
        const message = dashboard.reason instanceof Error ? dashboard.reason.message : 'Erreur dashboard';
        if (message.toLowerCase().includes('introuvable')) {
            loadError.value = 'Compte patient non lié. Demandez à l accueil de créer ou lier votre compte depuis le dossier patient.';
        }
        console.error('Impossible de charger le dashboard patient', dashboard.reason);
    }

    if (rdvs.status === 'fulfilled') {
        appointments.value = Array.isArray(rdvs.value?.items) ? rdvs.value.items : [];
    }

    if (consults.status === 'fulfilled') {
        consultations.value = Array.isArray(consults.value?.items) ? consults.value.items : [];
    } else {
        console.error('Impossible de charger les consultations patient', consults.reason);
    }

    if (payments.status === 'fulfilled') {
        const paymentItems = Array.isArray(payments.value?.items) ? payments.value.items : [];
        totalPaid.value = paymentItems.reduce((sum, p) => sum + Number(p?.montant || 0), 0);
    }

    isLoading.value = false;
});
</script>

<template>
    <AppLayout>
        <section class="dashboard-stack">
            <Message v-if="loadError" severity="warn" :closable="false" class="mb-0">{{ loadError }}</Message>

            <StatsGrid :stats="stats" :loading="isLoading" :empty="!isLoading && statsEmpty" />

            <div class="section-label">
                <i class="pi pi-calendar section-icon" />
                <span>Prochain rendez-vous</span>
            </div>
            <NextAppointmentCard
                :appointment="nextAppointment"
                :loading="isLoading"
                :empty="!isLoading && appointmentsEmpty"
            />

            <div class="section-label">
                <i class="pi pi-file-edit section-icon" />
                <span>Dernière consultation</span>
            </div>
            <LastConsultationCard
                :consultation="lastConsultation"
                :loading="isLoading"
                :empty="!isLoading && consultationsEmpty"
            />
        </section>
    </AppLayout>
</template>

<style scoped>
.dashboard-stack {
    display: grid;
    gap: 0.9rem;
}

.section-label {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--p-text-muted-color);
    padding: 0 0.25rem;
    margin-top: 0.2rem;
}

.section-icon {
    font-size: 0.85rem;
}

.mb-0 {
    margin-bottom: 0;
}
</style>
