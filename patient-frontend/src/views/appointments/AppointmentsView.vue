<script setup>
import CollectionToolbar from '../../components/CollectionToolbar.vue';
import { computed, onMounted, ref } from 'vue';
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
    return date.toLocaleString('fr-FR', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
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
    } catch {
        loadError.value =
            'Impossible de charger vos données. Veuillez réessayer.';
    } finally {
        loading.value = false;
    }
});

function reloadPage() {
    window.location.reload();
}
const search = ref('');
const filter = ref('all');
const loadError = ref('');
const filters = [
    { label: 'Tous', value: 'all' },
    { label: 'À venir', value: 'upcoming' },
    { label: 'Passés', value: 'past' },
];
const visibleItems = computed(() =>
    appointments.value.filter((item) => {
        if (
            filter.value === 'upcoming' &&
            (new Date(item.dateRdv) < new Date() ||
                /annul|cancel/i.test(item.statut || ''))
        )
            return false;
        if (filter.value === 'past' && new Date(item.dateRdv) >= new Date())
            return false;
        const terms = [
            item.id,
            item.type,
            item.description,
            item.medecin?.nom,
            item.noteSeance,
            item.statut,
            item.modePaiement,
            item.montant,
            formatDate(item.dateRdv),
        ]
            .filter((v) => v != null)
            .join(' ')
            .toLocaleLowerCase('fr');
        return terms.includes(search.value.trim().toLocaleLowerCase('fr'));
    }),
);
</script>

<template>
    <AppLayout>
        <section class="space-y-4">
            <div class="page-hero">
                <div>
                    <h2 class="hero-title">Rendez-vous</h2>
                    <p class="muted m-0 text-sm">
                        Suivez vos prochains rendez-vous et leur statut.
                    </p>
                </div>
            </div>

            <CollectionToolbar
                v-if="!loading && !loadError"
                v-model:search="search"
                v-model:filter="filter"
                :filters="filters"
                :count="visibleItems.length"
                placeholder="Rechercher un rendez-vous..."
            />
            <Message v-if="loadError" severity="error" :closable="false"
                >{{ loadError }}
                <button type="button" class="retry-link" @click="reloadPage">
                    Réessayer
                </button></Message
            >
            <div v-if="loading" class="grid gap-3">
                <Skeleton height="5.5rem" border-radius="16px" />
                <Skeleton height="5.5rem" border-radius="16px" />
            </div>

            <div
                v-else-if="!loadError && appointments.length === 0"
                class="empty-card"
            >
                <i class="pi pi-calendar-times empty-icon" />
                <p class="muted m-0">
                    Aucun rendez-vous planifié pour le moment.
                </p>
            </div>

            <div
                v-else-if="!loadError && visibleItems.length === 0"
                class="empty-card"
            >
                <i class="pi pi-search empty-icon" />
                <p class="muted">Aucun résultat pour ces critères.</p>
                <Button
                    label="Réinitialiser les filtres"
                    text
                    @click="
                        search = '';
                        filter = 'all';
                    "
                />
            </div>
            <div v-else-if="!loadError" class="grid gap-3">
                <div
                    v-for="item in visibleItems"
                    :key="item.id"
                    class="rdv-card"
                >
                    <div class="rdv-accent" />
                    <div class="rdv-body">
                        <div class="rdv-top">
                            <div>
                                <p class="m-0 font-semibold">
                                    {{ formatDate(item.dateRdv) }}
                                </p>
                                <p class="m-0 text-sm muted mt-1">
                                    {{ item?.medecin?.nom || '--' }}
                                </p>
                            </div>
                            <Tag
                                :value="item.statut || 'Planifié'"
                                severity="info"
                            />
                        </div>
                        <p
                            v-if="item.description"
                            class="m-0 text-sm muted rdv-desc"
                        >
                            {{ item.description }}
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
