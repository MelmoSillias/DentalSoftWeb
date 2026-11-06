<script setup>
import CollectionToolbar from '../../components/CollectionToolbar.vue';
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../../stores/auth';
import {
    fetchConsultationDetail,
    fetchPatientAppreciations,
    fetchPatientConsultations,
    submitConsultationAppreciation,
} from '../../services/patientPortal';

const authStore = useAuthStore();
const loading = ref(true);
const submitting = ref({});
const feedbackErrors = ref({});
const detailById = ref({});
const loadingDetail = ref({});
const detailErrors = ref({});
const expandedId = ref(null);

async function toggleDetail(consultationId) {
    if (expandedId.value === consultationId) {
        expandedId.value = null;
        return;
    }
    expandedId.value = consultationId;
    if (!detailById.value[consultationId]) {
        loadingDetail.value = {
            ...loadingDetail.value,
            [consultationId]: true,
        };
        detailErrors.value[consultationId] = '';
        try {
            const detail = await fetchConsultationDetail(
                authStore.token,
                consultationId,
            );
            detailById.value = {
                ...detailById.value,
                [consultationId]: detail,
            };
        } catch {
            detailErrors.value[consultationId] =
                'Ce détail est indisponible. Réessayez dans un instant.';
        } finally {
            loadingDetail.value = {
                ...loadingDetail.value,
                [consultationId]: false,
            };
        }
    }
}

function formatPrice(val) {
    return Number(val || 0).toLocaleString('fr-FR') + ' FCFA';
}
const consultations = ref([]);
const appreciationByConsultation = ref({});
const formByConsultation = reactive({});

const closedWithoutFeedback = computed(() =>
    consultations.value.filter((item) => {
        const isClosed = Number(item?.statut || 0) === 1;
        if (!isClosed) return false;
        return !appreciationByConsultation.value[item.id];
    }),
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

function statusLabel(statut) {
    return Number(statut || 0) === 1 ? 'Clôturée' : 'En cours';
}

function ensureForm(consultationId) {
    if (!formByConsultation[consultationId]) {
        formByConsultation[consultationId] = {
            rating: 5,
            comment: '',
        };
    }

    return formByConsultation[consultationId];
}

function setRating(consultationId, rating) {
    const form = ensureForm(consultationId);
    form.rating = rating;
}

async function sendAppreciation(consultationId) {
    const token = authStore.token;
    if (!token) return;

    const form = ensureForm(consultationId);
    if (!String(form.comment || '').trim()) {
        return;
    }

    submitting.value = {
        ...submitting.value,
        [consultationId]: true,
    };

    feedbackErrors.value[consultationId] = '';
    try {
        const response = await submitConsultationAppreciation(
            token,
            consultationId,
            {
                rating: Number(form.rating || 5),
                comment: String(form.comment || '').trim(),
                anonymous: false,
            },
        );

        appreciationByConsultation.value = {
            ...appreciationByConsultation.value,
            [consultationId]: response?.item || { consultationId },
        };
    } catch {
        feedbackErrors.value[consultationId] =
            'Votre avis n’a pas pu être envoyé. Veuillez réessayer.';
    } finally {
        submitting.value = {
            ...submitting.value,
            [consultationId]: false,
        };
    }
}

onMounted(async () => {
    authStore.hydrate();
    const token = authStore.token;
    if (!token) {
        loading.value = false;
        return;
    }

    const [consultationRes, appreciationRes] = await Promise.allSettled([
        fetchPatientConsultations(token),
        fetchPatientAppreciations(token),
    ]);

    if (consultationRes.status === 'fulfilled') {
        consultations.value = Array.isArray(consultationRes.value?.items)
            ? consultationRes.value.items
            : [];
    }

    if (consultationRes.status === 'rejected')
        loadError.value =
            'Impossible de charger vos consultations. Veuillez réessayer.';

    if (appreciationRes.status === 'fulfilled') {
        const map = {};
        const items = Array.isArray(appreciationRes.value?.items)
            ? appreciationRes.value.items
            : [];
        for (const item of items) {
            if (item?.consultationId) {
                map[item.consultationId] = item;
            }
        }
        appreciationByConsultation.value = map;
    }

    loading.value = false;
});

function reloadPage() {
    window.location.reload();
}
const search = ref('');
const filter = ref('all');
const loadError = ref('');
const filters = [
    { label: 'Toutes', value: 'all' },
    { label: 'En cours', value: 'open' },
    { label: 'Clôturées', value: 'closed' },
];
const visibleItems = computed(() =>
    consultations.value.filter((item) => {
        if (filter.value === 'open' && Number(item.statut) === 1) return false;
        if (filter.value === 'closed' && Number(item.statut) !== 1)
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
                <h2 class="hero-title">Consultations</h2>
                <p class="muted m-0 text-sm">
                    Historique de vos séances et formulaire d'appréciation
                    après clôture.
                </p>
            </div>
            <Tag
                v-if="!loading && closedWithoutFeedback.length > 0"
                :value="`${closedWithoutFeedback.length} avis en attente`"
                severity="warn"
            />
        </div>

        <CollectionToolbar
            v-if="!loading && !loadError"
            v-model:search="search"
            v-model:filter="filter"
            :filters="filters"
            :count="visibleItems.length"
            placeholder="Rechercher une consultation..."
        />
        <Message v-if="loadError" severity="error" :closable="false"
            >{{ loadError }}
            <button type="button" class="retry-link" @click="reloadPage">
                Réessayer
            </button></Message
        >
        <div v-if="loading" class="grid gap-3">
            <Skeleton height="6rem" border-radius="16px" />
            <Skeleton height="6rem" border-radius="16px" />
            <Skeleton height="6rem" border-radius="16px" />
        </div>

        <div
            v-else-if="!loadError && consultations.length === 0"
            class="empty-card"
        >
            <i class="pi pi-file-edit empty-icon" />
            <p class="muted m-0">
                Aucune consultation disponible pour le moment.
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
                class="consult-card"
            >
                <div
                    class="consult-accent"
                    :class="
                        Number(item.statut || 0) === 1
                            ? 'accent-closed'
                            : 'accent-open'
                    "
                />
                <div class="consult-body">
                    <div class="consult-top">
                        <div>
                            <p class="m-0 font-semibold">
                                {{ item.type || 'Consultation' }}
                            </p>
                            <p class="m-0 text-sm muted mt-1">
                                {{ formatDate(item.date) }} ·
                                {{ item?.medecin?.nom || '--' }}
                            </p>
                        </div>
                        <Tag
                            :value="statusLabel(item.statut)"
                            :severity="
                                Number(item.statut || 0) === 1
                                    ? 'success'
                                    : 'info'
                            "
                        />
                    </div>

                    <p
                        v-if="item.noteSeance"
                        class="m-0 text-sm muted consult-note"
                    >
                        {{ item.noteSeance }}
                    </p>

                    <!-- Bouton détail actes -->
                    <button
                        type="button"
                        class="detail-toggle-btn"
                        :aria-expanded="expandedId === item.id"
                        @click="toggleDetail(item.id)"
                    >
                        <i
                            class="pi"
                            :class="
                                expandedId === item.id
                                    ? 'pi-chevron-up'
                                    : 'pi-list'
                            "
                        />
                        {{
                            expandedId === item.id
                                ? 'Masquer les actes'
                                : 'Voir les actes'
                        }}
                    </button>

                    <!-- Détail actes -->
                    <div v-if="expandedId === item.id" class="actes-detail">
                        <div
                            v-if="loadingDetail[item.id]"
                            class="actes-loading"
                        >
                            <i class="pi pi-spin pi-spinner" />
                            Chargement...
                        </div>
                        <Message
                            v-else-if="detailErrors[item.id]"
                            severity="error"
                            :closable="false"
                            >{{ detailErrors[item.id] }}</Message
                        >
                        <template v-else-if="detailById[item.id]">
                            <div
                                v-if="
                                    detailById[item.id].actes?.length === 0
                                "
                                class="actes-empty"
                            >
                                Aucun acte enregistré pour cette
                                consultation.
                            </div>
                            <div v-else>
                                <div class="acte-row acte-header">
                                    <span>Acte</span><span>Dent</span
                                    ><span class="text-right">Qté</span
                                    ><span class="text-right">Prix</span
                                    ><span class="text-right">Total</span>
                                </div>
                                <div
                                    v-for="(acte, idx) in detailById[
                                        item.id
                                    ].actes"
                                    :key="idx"
                                    class="acte-row"
                                >
                                    <span class="acte-type">{{
                                        acte.type || acte.description || '-'
                                    }}</span>
                                    <span class="muted">{{
                                        acte.dent || '-'
                                    }}</span>
                                    <span class="text-right">{{
                                        acte.quantite
                                    }}</span>
                                    <span class="text-right muted">{{
                                        formatPrice(acte.prix)
                                    }}</span>
                                    <span class="text-right acte-total">{{
                                        formatPrice(acte.total)
                                    }}</span>
                                </div>
                                <div class="actes-total-row">
                                    <span>Total actes</span>
                                    <span class="actes-total-val">{{
                                        formatPrice(
                                            detailById[item.id].totalActes,
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div
                        v-if="Number(item.statut || 0) === 1"
                        class="feedback-zone"
                    >
                        <Message
                            v-if="appreciationByConsultation[item.id]"
                            severity="success"
                            :closable="false"
                            size="small"
                        >
                            Merci, votre appréciation a bien été
                            enregistrée.
                        </Message>

                        <div v-else class="feedback-form">
                            <Message
                                v-if="feedbackErrors[item.id]"
                                severity="error"
                                :closable="false"
                                >{{ feedbackErrors[item.id] }}</Message
                            >
                            <p
                                class="text-sm font-semibold m-0 feedback-prompt"
                            >
                                Donnez votre avis sur cette consultation
                            </p>
                            <div class="stars-row">
                                <button
                                    v-for="star in 5"
                                    :key="`${item.id}-star-${star}`"
                                    type="button"
                                    class="star-btn"
                                    :aria-label="`${star} sur 5`"
                                    :aria-pressed="
                                        star <= ensureForm(item.id).rating
                                    "
                                    :class="{
                                        active:
                                            star <=
                                            ensureForm(item.id).rating,
                                    }"
                                    @click="setRating(item.id, star)"
                                >
                                    <i class="pi pi-star-fill" />
                                </button>
                            </div>
                            <textarea
                                v-model="ensureForm(item.id).comment"
                                rows="3"
                                class="feedback-textarea"
                                placeholder="Partagez votre ressenti sur cette consultation..."
                            />
                            <Button
                                label="Envoyer mon avis"
                                icon="pi pi-send"
                                size="small"
                                :loading="submitting[item.id] === true"
                                :disabled="!ensureForm(item.id).comment"
                                @click="sendAppreciation(item.id)"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</template>
