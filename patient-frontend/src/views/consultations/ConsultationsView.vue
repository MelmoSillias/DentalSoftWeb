<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import AppLayout from '../../layout/AppLayout.vue';
import { useAuthStore } from '../../stores/auth';
import { fetchConsultationDetail, fetchPatientAppreciations, fetchPatientConsultations, submitConsultationAppreciation } from '../../services/patientPortal';

const authStore = useAuthStore();
const loading = ref(true);
const submitting = ref({});
const detailById = ref({});
const loadingDetail = ref({});
const expandedId = ref(null);

async function toggleDetail(consultationId) {
    if (expandedId.value === consultationId) {
        expandedId.value = null;
        return;
    }
    expandedId.value = consultationId;
    if (!detailById.value[consultationId]) {
        loadingDetail.value = { ...loadingDetail.value, [consultationId]: true };
        try {
            const detail = await fetchConsultationDetail(authStore.token, consultationId);
            detailById.value = { ...detailById.value, [consultationId]: detail };
        } catch {
            // ignore
        } finally {
            loadingDetail.value = { ...loadingDetail.value, [consultationId]: false };
        }
    }
}

function formatPrice(val) {
    return Number(val || 0).toLocaleString('fr-FR') + ' FCFA';
}
const consultations = ref([]);
const appreciationByConsultation = ref({});
const formByConsultation = reactive({});

const closedWithoutFeedback = computed(() => consultations.value.filter((item) => {
    const isClosed = Number(item?.statut || 0) === 1;
    if (!isClosed) return false;
    return !appreciationByConsultation.value[item.id];
}));

function formatDate(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '--';
    return date.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
}

function statusLabel(statut) {
    return Number(statut || 0) === 1 ? 'Clôturée' : 'En cours';
}

function ensureForm(consultationId) {
    if (!formByConsultation[consultationId]) {
        formByConsultation[consultationId] = {
            rating: 5,
            comment: ''
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
        [consultationId]: true
    };

    try {
        const response = await submitConsultationAppreciation(token, consultationId, {
            rating: Number(form.rating || 5),
            comment: String(form.comment || '').trim(),
            anonymous: false
        });

        appreciationByConsultation.value = {
            ...appreciationByConsultation.value,
            [consultationId]: response?.item || { consultationId }
        };
    } finally {
        submitting.value = {
            ...submitting.value,
            [consultationId]: false
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
        fetchPatientAppreciations(token)
    ]);

    if (consultationRes.status === 'fulfilled') {
        consultations.value = Array.isArray(consultationRes.value?.items) ? consultationRes.value.items : [];
    }

    if (appreciationRes.status === 'fulfilled') {
        const map = {};
        const items = Array.isArray(appreciationRes.value?.items) ? appreciationRes.value.items : [];
        for (const item of items) {
            if (item?.consultationId) {
                map[item.consultationId] = item;
            }
        }
        appreciationByConsultation.value = map;
    }

    loading.value = false;
});
</script>

<template>
    <AppLayout>
        <section class="space-y-4">
            <div class="page-hero">
                <div>
                    <h2 class="hero-title">Consultations</h2>
                    <p class="muted m-0 text-sm">Historique de vos séances et formulaire d'appréciation après clôture.</p>
                </div>
                <Tag v-if="!loading && closedWithoutFeedback.length > 0" :value="`${closedWithoutFeedback.length} avis en attente`" severity="warn" />
            </div>

            <div v-if="loading" class="grid gap-3">
                <Skeleton height="6rem" border-radius="16px" />
                <Skeleton height="6rem" border-radius="16px" />
                <Skeleton height="6rem" border-radius="16px" />
            </div>

            <div v-else-if="consultations.length === 0" class="empty-card">
                <i class="pi pi-file-edit empty-icon" />
                <p class="muted m-0">Aucune consultation disponible pour le moment.</p>
            </div>

            <div v-else class="grid gap-3">
                <div v-for="item in consultations" :key="item.id" class="consult-card">
                    <div class="consult-accent" :class="Number(item.statut || 0) === 1 ? 'accent-closed' : 'accent-open'" />
                    <div class="consult-body">
                        <div class="consult-top">
                            <div>
                                <p class="m-0 font-semibold">{{ item.type || 'Consultation' }}</p>
                                <p class="m-0 text-sm muted mt-1">{{ formatDate(item.date) }} · {{ item?.medecin?.nom || '--' }}</p>
                            </div>
                            <Tag :value="statusLabel(item.statut)" :severity="Number(item.statut || 0) === 1 ? 'success' : 'info'" />
                        </div>

                        <p v-if="item.noteSeance" class="m-0 text-sm muted consult-note">{{ item.noteSeance }}</p>

                        <!-- Bouton détail actes -->
                        <button type="button" class="detail-toggle-btn" @click="toggleDetail(item.id)">
                            <i class="pi" :class="expandedId === item.id ? 'pi-chevron-up' : 'pi-list'" />
                            {{ expandedId === item.id ? 'Masquer les actes' : 'Voir les actes' }}
                        </button>

                        <!-- Détail actes -->
                        <div v-if="expandedId === item.id" class="actes-detail">
                            <div v-if="loadingDetail[item.id]" class="actes-loading">
                                <i class="pi pi-spin pi-spinner" /> Chargement...
                            </div>
                            <template v-else-if="detailById[item.id]">
                                <div v-if="detailById[item.id].actes?.length === 0" class="actes-empty">
                                    Aucun acte enregistré pour cette consultation.
                                </div>
                                <div v-else>
                                    <div class="acte-row acte-header">
                                        <span>Acte</span><span>Dent</span><span class="text-right">Qté</span><span class="text-right">Prix</span><span class="text-right">Total</span>
                                    </div>
                                    <div v-for="(acte, idx) in detailById[item.id].actes" :key="idx" class="acte-row">
                                        <span class="acte-type">{{ acte.type || acte.description || '-' }}</span>
                                        <span class="muted">{{ acte.dent || '-' }}</span>
                                        <span class="text-right">{{ acte.quantite }}</span>
                                        <span class="text-right muted">{{ formatPrice(acte.prix) }}</span>
                                        <span class="text-right acte-total">{{ formatPrice(acte.total) }}</span>
                                    </div>
                                    <div class="actes-total-row">
                                        <span>Total actes</span>
                                        <span class="actes-total-val">{{ formatPrice(detailById[item.id].totalActes) }}</span>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div v-if="Number(item.statut || 0) === 1" class="feedback-zone">
                            <Message v-if="appreciationByConsultation[item.id]" severity="success" :closable="false" size="small">
                                Merci, votre appréciation a bien été enregistrée.
                            </Message>

                            <div v-else class="feedback-form">
                                <p class="text-sm font-semibold m-0 feedback-prompt">Donnez votre avis sur cette consultation</p>
                                <div class="stars-row">
                                    <button
                                        v-for="star in 5"
                                        :key="`${item.id}-star-${star}`"
                                        type="button"
                                        class="star-btn"
                                        :class="{ active: star <= ensureForm(item.id).rating }"
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
    </AppLayout>
</template>

<style scoped>
.page-hero {
    background: #fff;
    border-radius: var(--pp-radius);
    padding: 1.1rem 1.2rem;
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    border-left: 4px solid #0891b2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
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

.consult-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    display: flex;
    overflow: hidden;
    transition: box-shadow 0.2s ease, transform 0.18s ease;
}

.consult-card:hover {
    box-shadow: var(--pp-shadow-md);
}

.consult-accent {
    width: 4px;
    flex-shrink: 0;
}

.accent-closed { background: #22c55e; }
.accent-open { background: #3b82f6; }

.consult-body {
    flex: 1;
    padding: 0.9rem 1rem;
    display: grid;
    gap: 0.6rem;
}

.consult-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.consult-note {
    font-style: italic;
    border-top: 1px solid var(--p-surface-100);
    padding-top: 0.5rem;
}

.mt-1 { margin-top: 0.25rem; }

/* Feedback */
.feedback-zone {
    border-top: 1px solid var(--p-surface-100);
    padding-top: 0.75rem;
}

.feedback-form {
    display: grid;
    gap: 0.65rem;
}

.feedback-prompt {
    color: var(--p-text-color);
}

.stars-row {
    display: flex;
    gap: 0.3rem;
}

.star-btn {
    border: none;
    background: transparent;
    color: #d1d5db;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.1rem;
    transition: color 0.15s ease, transform 0.1s ease;
    line-height: 1;
}

.star-btn:hover,
.star-btn.active {
    color: #f59e0b;
    transform: scale(1.15);
}

.feedback-textarea {
    width: 100%;
    border: 1.5px solid var(--p-surface-200);
    border-radius: var(--pp-radius-sm);
    padding: 0.65rem 0.75rem;
    font-family: inherit;
    font-size: 0.875rem;
    resize: vertical;
    min-height: 80px;
    background: var(--p-surface-50);
    transition: border-color 0.15s ease;
    outline: none;
}

.feedback-textarea:focus {
    border-color: #2563eb;
    background: #fff;
}

/* Détail actes */
.detail-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: #2563eb;
    background: #eff6ff;
    border: none;
    border-radius: 999px;
    padding: 0.35rem 0.85rem;
    cursor: pointer;
    transition: background 0.15s ease;
    align-self: flex-start;
}
.detail-toggle-btn:hover { background: #dbeafe; }

.actes-detail {
    background: #f8fafc;
    border-radius: var(--pp-radius-sm);
    border: 1px solid var(--p-surface-200);
    padding: 0.75rem;
    font-size: 0.82rem;
}
.actes-loading {
    color: var(--p-text-muted-color);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
}
.actes-empty {
    color: var(--p-text-muted-color);
    font-style: italic;
}
.acte-row {
    display: grid;
    grid-template-columns: 2fr 1fr 0.5fr 1fr 1fr;
    gap: 0.4rem;
    padding: 0.4rem 0;
    border-bottom: 1px solid var(--p-surface-100);
    align-items: center;
}
.acte-header {
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--p-text-muted-color);
    border-bottom: 2px solid var(--p-surface-200);
}
.acte-type { font-weight: 600; color: var(--p-text-color); }
.acte-total { font-weight: 700; color: #2563eb; }
.text-right { text-align: right; }
.actes-total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 0.6rem;
    font-weight: 700;
    font-size: 0.85rem;
}
.actes-total-val {
    color: #16a34a;
    background: #f0fdf4;
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
}
</style>
