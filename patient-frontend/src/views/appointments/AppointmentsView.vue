<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../../layout/AppLayout.vue';
import { useAuthStore } from '../../stores/auth';
import { fetchPatientAppointments } from '../../services/patientPortal';

const authStore = useAuthStore();
const loading = ref(true);
const appointments = ref([]);

function formatDate(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '--';
    return date.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
}

onMounted(async () => {
    authStore.hydrate();
    const token = authStore.token;
    if (!token) {
        loading.value = false;
        return;
    }

    try {
        const res = await fetchPatientAppointments(token);
        appointments.value = Array.isArray(res?.items) ? res.items : [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <section class="space-y-4">
            <div class="page-hero">
                <div>
                    <h2 class="hero-title">Rendez-vous</h2>
                    <p class="muted m-0 text-sm">Suivez vos prochains rendez-vous et leur statut.</p>
                </div>
            </div>

            <div v-if="loading" class="grid gap-3">
                <Skeleton height="5.5rem" border-radius="16px" />
                <Skeleton height="5.5rem" border-radius="16px" />
            </div>

            <div v-else-if="appointments.length === 0" class="empty-card">
                <i class="pi pi-calendar-times empty-icon" />
                <p class="muted m-0">Aucun rendez-vous planifié pour le moment.</p>
            </div>

            <div v-else class="grid gap-3">
                <div v-for="item in appointments" :key="item.id" class="rdv-card">
                    <div class="rdv-accent" />
                    <div class="rdv-body">
                        <div class="rdv-top">
                            <div>
                                <p class="m-0 font-semibold">{{ formatDate(item.dateRdv) }}</p>
                                <p class="m-0 text-sm muted mt-1">{{ item?.medecin?.nom || '--' }}</p>
                            </div>
                            <Tag :value="item.statut || 'Planifié'" severity="info" />
                        </div>
                        <p v-if="item.description" class="m-0 text-sm muted rdv-desc">{{ item.description }}</p>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.page-hero {
    background: #fff;
    border-radius: var(--pp-radius);
    padding: 1.1rem 1.2rem;
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    border-left: 4px solid #2563eb;
}

.hero-title {
    margin: 0 0 0.2rem;
    font-size: 1.1rem;
    font-weight: 700;
}

.empty-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    padding: 2.5rem 1rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
    border: 1px dashed var(--p-surface-300);
}

.empty-icon {
    font-size: 2.2rem;
    color: var(--p-text-muted-color);
    opacity: 0.4;
}

.rdv-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    display: flex;
    overflow: hidden;
    transition: box-shadow 0.2s ease, transform 0.18s ease;
}

.rdv-card:hover {
    box-shadow: var(--pp-shadow-md);
    transform: translateY(-2px);
}

.rdv-accent {
    width: 4px;
    background: var(--pp-gradient);
    flex-shrink: 0;
}

.rdv-body {
    flex: 1;
    padding: 0.9rem 1rem;
    display: grid;
    gap: 0.45rem;
}

.rdv-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.rdv-desc {
    border-top: 1px solid var(--p-surface-100);
    padding-top: 0.45rem;
    font-style: italic;
}

.mt-1 {
    margin-top: 0.25rem;
}
</style>
