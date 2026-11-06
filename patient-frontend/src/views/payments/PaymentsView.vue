<script setup>
import CollectionToolbar from '../../components/CollectionToolbar.vue';
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { fetchPatientPayments } from '../../services/patientPortal';

const authStore = useAuthStore();
const router = useRouter();
const loading = ref(true);
const payments = ref([]);

const totalPaid = computed(() =>
    payments.value.reduce((sum, item) => sum + Number(item?.montant || 0), 0),
);

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
        const res = await fetchPatientPayments(token);
        payments.value = Array.isArray(res?.items) ? res.items : [];
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
    { label: 'Validés', value: 'validated' },
    { label: 'En attente', value: 'pending' },
];
const visibleItems = computed(() =>
    payments.value.filter((item) => {
        if (filter.value === 'validated' && !item.validated) return false;
        if (filter.value === 'pending' && item.validated) return false;
        const terms = [
            item.id,
            item.type,
            item.description,
            item.medecin?.nom,
            item.noteSeance,
            item.statut,
            item.modePaiement,
            item.montant,
            formatDate(item.date),
        ]
            .filter((v) => v != null)
            .join(' ')
            .toLocaleLowerCase('fr');
        return terms.includes(search.value.trim().toLocaleLowerCase('fr'));
    }),
);
</script>

<template>
    <section class="space-y-4">
        <div class="page-hero">
            <div>
                <h1 class="hero-title">Mes paiements</h1>
                <p class="muted m-0 text-sm">
                    Retrouvez vos règlements et suivez leur validation.
                </p>
            </div>
        </div>
        <div class="total-banner">
            <div class="total-icon-wrap">
                <i class="pi pi-wallet total-icon" />
            </div>
            <div>
                <p class="total-label">Montant total réglé</p>
                <Skeleton v-if="loading" width="10rem" height="2rem" />
                <p v-else class="total-value">
                    {{ Math.round(totalPaid).toLocaleString('fr-FR') }} FCFA
                </p>
            </div>
            <Button
                text
                icon="pi pi-folder-open"
                label="Mes documents"
                size="small"
                class="ml-auto"
                @click="router.push('/documents')"
            />
        </div>

        <CollectionToolbar
            v-if="!loading && !loadError"
            v-model:search="search"
            v-model:filter="filter"
            :filters="filters"
            :count="visibleItems.length"
            placeholder="Rechercher un paiement..."
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
            v-else-if="!loadError && payments.length === 0"
            class="empty-card"
        >
            <i class="pi pi-credit-card empty-icon" />
            <p class="muted m-0">Aucun paiement enregistré.</p>
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
                class="payment-card"
            >
                <div class="payment-accent" />
                <div class="payment-body">
                    <div class="payment-top">
                        <div>
                            <p class="m-0 font-bold payment-amount">
                                {{
                                    Number(
                                        item?.montant || 0,
                                    ).toLocaleString('fr-FR')
                                }}
                                FCFA
                            </p>
                            <p class="m-0 text-sm muted mt-1">
                                {{ formatDate(item.date) }}
                            </p>
                        </div>
                        <Tag
                            :value="item.validationStatus || 'En attente'"
                            :severity="item.validated ? 'success' : 'warn'"
                        />
                    </div>
                    <div class="payment-meta">
                        <span class="meta-pill"
                            ><i class="pi pi-credit-card" />
                            {{
                                item.modePaiement || 'Mode non renseigné'
                            }}</span
                        >
                        <span
                            v-if="item.description"
                            class="muted text-xs"
                            >{{ item.description }}</span
                        >
                    </div>
                </div>
            </div>
        </div>
    </section>

</template>
