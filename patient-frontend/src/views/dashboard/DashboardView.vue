<script setup>
import { computed, onMounted, ref } from 'vue';
import StatsGrid from './components/StatsGrid.vue';
import NextAppointmentCard from './components/NextAppointmentCard.vue';
import LastConsultationCard from './components/LastConsultationCard.vue';
import { useAuthStore } from '../../stores/auth';
import {
    fetchPatientAppointments,
    fetchPatientConsultations,
    fetchPatientDashboard,
    fetchPatientPayments,
} from '../../services/patientPortal';

const authStore = useAuthStore();
const dashboardStats = ref({
    consultations: 0,
    rdvs: 0,
    devisFactures: 0,
    paiements: 0,
});
const totalPaid = ref(0);
const appointments = ref([]);
const consultations = ref([]);
const loadError = ref('');
const sectionErrors = ref({ stats: '', appointments: '', consultations: '' });
const isLoading = ref(true);

const firstName = computed(
    () => (authStore.user?.name || 'à vous').split(' ')[0],
);

const stats = computed(() => [
    {
        key: 'consultations',
        label: 'Consultations',
        value: dashboardStats.value.consultations,
        hint: 'Historique total',
        icon: 'pi pi-heart',
        color: 'blue',
    },
    {
        key: 'spent',
        label: 'Total réglé',
        value: `${Math.round(totalPaid.value).toLocaleString('fr-FR')} FCFA`,
        hint: 'Paiements enregistrés',
        icon: 'pi pi-wallet',
        color: 'green',
    },
    {
        key: 'rdv',
        label: 'Rendez-vous',
        value: dashboardStats.value.rdvs,
        hint: 'Suivi patient',
        icon: 'pi pi-calendar',
        color: 'orange',
    },
    {
        key: 'docs',
        label: 'Documents',
        value: dashboardStats.value.devisFactures,
        hint: 'Devis et factures',
        icon: 'pi pi-file',
        color: 'purple',
    },
]);

const appointmentsEmpty = computed(
    () =>
        !appointments.value.some(
            (item) =>
                item?.dateRdv &&
                new Date(item.dateRdv).getTime() >= Date.now() &&
                !/annul|cancel/i.test(item.statut || ''),
        ),
);
const consultationsEmpty = computed(() => consultations.value.length === 0);

const nextAppointment = computed(() => {
    const upcoming = appointments.value
        .filter(
            (item) =>
                item?.dateRdv &&
                new Date(item.dateRdv).getTime() >= Date.now() &&
                !/annul|cancel/i.test(item.statut || ''),
        )
        .sort(
            (a, b) =>
                new Date(a.dateRdv).getTime() - new Date(b.dateRdv).getTime(),
        )[0];

    if (!upcoming) {
        return {
            date: '--',
            time: '--',
            doctor: '--',
            status: 'Aucun',
        };
    }

    const when = new Date(upcoming.dateRdv);
    return {
        date: when.toLocaleDateString('fr-FR'),
        time: when.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit',
        }),
        doctor: upcoming?.medecin?.nom || '--',
        status: upcoming?.statut || 'Planifié',
    };
});

const lastConsultation = computed(() => {
    const latest = consultations.value
        .filter((item) => item?.date)
        .sort(
            (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime(),
        )[0];

    if (!latest) {
        return {
            date: '--',
            doctor: '--',
            summary: 'Aucune consultation disponible pour le moment.',
        };
    }

    return {
        date: new Date(latest.date).toLocaleDateString('fr-FR'),
        doctor: latest?.medecin?.nom || '--',
        summary:
            latest?.noteSeance || latest?.type || 'Consultation enregistrée.',
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
        fetchPatientPayments(token),
    ]);

    if (dashboard.status === 'fulfilled') {
        dashboardStats.value = dashboard.value?.stats || dashboardStats.value;
    } else {
        const message =
            dashboard.reason instanceof Error
                ? dashboard.reason.message
                : 'Erreur dashboard';
        if (message.toLowerCase().includes('introuvable')) {
            loadError.value =
                'Compte patient non lié. Demandez à l accueil de créer ou lier votre compte depuis le dossier patient.';
        }
        sectionErrors.value.stats =
            'Votre récapitulatif est momentanément indisponible.';
    }

    if (rdvs.status === 'fulfilled') {
        appointments.value = Array.isArray(rdvs.value?.items)
            ? rdvs.value.items
            : [];
    } else {
        sectionErrors.value.appointments =
            'Vos rendez-vous n’ont pas pu être chargés.';
    }

    if (consults.status === 'fulfilled') {
        consultations.value = Array.isArray(consults.value?.items)
            ? consults.value.items
            : [];
    } else {
        sectionErrors.value.consultations =
            'Vos consultations n’ont pas pu être chargées.';
    }

    if (payments.status === 'fulfilled') {
        const paymentItems = Array.isArray(payments.value?.items)
            ? payments.value.items
            : [];
        totalPaid.value = paymentItems.reduce(
            (sum, p) => sum + Number(p?.montant || 0),
            0,
        );
    } else {
        sectionErrors.value.stats =
            'Votre récapitulatif est momentanément indisponible.';
    }

    isLoading.value = false;
});
</script>
<template>
    <section class="dashboard-stack">
        <div class="welcome-row">
            <div>
                <h1>
                    Bonjour, <span>{{ firstName }}.</span>
                </h1>
                <p class="muted">
                    Un nouveau jour pour prendre soin de votre sourire.
                </p>
            </div>
            <span class="welcome-status"
                ><span class="status-dot" />Mon espace patient</span
            >
        </div>
        <Message v-if="loadError" severity="warn" :closable="false">{{
            loadError
        }}</Message>
        <section class="overview-hero">
            <div class="hero-copy">
                <span class="hero-kicker">VOTRE SANTÉ, NOTRE PRIORITÉ</span>
                <h2>
                    Un sourire serein,<br />un suivi en toute simplicité.
                </h2>
                <p>Votre cabinet vous accompagne à chaque étape.</p>
                <RouterLink class="hero-link" to="/rendez-vous"
                    >Mes rendez-vous<i class="pi pi-arrow-right"
                /></RouterLink>
            </div>
            <div class="hero-art" aria-hidden="true">
                <svg viewBox="0 0 100 100" fill="none">
                    <path
                        d="M30 18C12 18 12 38 21 57c6 14 7 28 15 28 8 0 4-29 14-29s6 29 14 29c8 0 9-14 15-28 9-19 9-39-9-39-10 0-11 6-20 6s-10-6-20-6Z"
                        stroke="currentColor"
                        stroke-width="3"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M38 35c7 4 17 4 24 0"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                    /></svg
                ><span class="hero-art-dot" />
            </div>
        </section>
        <Message
            v-if="sectionErrors.stats"
            severity="warn"
            :closable="false"
            >{{ sectionErrors.stats }}</Message
        ><StatsGrid v-else :stats="stats" :loading="isLoading" />
        <div class="dashboard-columns">
            <section class="dashboard-section">
                <div class="section-heading">
                    <h2>Prochain rendez-vous</h2>
                    <RouterLink to="/rendez-vous"
                        >Tout voir<i class="pi pi-arrow-right"
                    /></RouterLink>
                </div>
                <Message
                    v-if="sectionErrors.appointments"
                    severity="warn"
                    :closable="false"
                    >{{ sectionErrors.appointments }}</Message
                ><NextAppointmentCard
                    v-else
                    :appointment="nextAppointment"
                    :loading="isLoading"
                    :empty="!isLoading && appointmentsEmpty"
                />
            </section>
            <section class="dashboard-section">
                <div class="section-heading">
                    <h2>Dernière consultation</h2>
                    <RouterLink to="/consultations"
                        >Mon historique<i class="pi pi-arrow-right"
                    /></RouterLink>
                </div>
                <Message
                    v-if="sectionErrors.consultations"
                    severity="warn"
                    :closable="false"
                    >{{ sectionErrors.consultations }}</Message
                ><LastConsultationCard
                    v-else
                    :consultation="lastConsultation"
                    :loading="isLoading"
                    :empty="!isLoading && consultationsEmpty"
                />
            </section>
        </div>
        <aside class="care-tip">
            <i class="pi pi-sparkles" aria-hidden="true" />
            <div>
                <strong>Le petit geste qui fait sourire</strong>
                <p>
                    Deux minutes de brossage, deux fois par jour : une bonne
                    habitude pour votre santé bucco-dentaire.
                </p>
            </div>
        </aside>
    </section>
</template>
