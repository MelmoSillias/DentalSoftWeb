<script setup>
import { computed, onMounted, ref } from 'vue';
import PageShell from '@/components/layout/PageShell.vue';
import PageHeader from '@/components/layout/PageHeader.vue';
import PageSection from '@/components/layout/PageSection.vue';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import VitrineFilterBar from '@/components/vitrine/VitrineFilterBar.vue';
import VitrineEmptyState from '@/components/vitrine/VitrineEmptyState.vue';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { deleteAdminAppreciation, fetchAdminAppreciations, setAdminAppreciationPublished } from '@/services/appreciationAdminService';
import { getHttpErrorMessage } from '@/service/http';
import { apiPrefix } from '@/config';

const token = localStorage.getItem('token');
const confirm = useConfirm();
const toast = useToast();

const loading = ref(true);
const actionLoadingId = ref(null);
const errorMessage = ref('');
const stats = ref({
    total: 0,
    anonymous: 0,
    published: 0,
    averageRating: 0
});
const items = ref([]);

const q = ref('');
const modeFilter = ref('all');
const categoryFilter = ref(null);
const categoryOptions = [
    { label: 'Toutes les catégories', value: null },
    { label: 'Insatisfait (1–2)', value: 'negative' },
    { label: 'Neutre (3)', value: 'neutral' },
    { label: 'Satisfait (4)', value: 'positive' },
    { label: 'Très satisfait (5)', value: 'excellent' }
];
const exportUrl = `${apiPrefix}/appreciations/public/export`;

const modeOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Anonymes', value: 'anonymous' },
    { label: 'Identifiés', value: 'identified' },
    { label: 'Publiés', value: 'published' },
    { label: 'Non publiés', value: 'hidden' }
];

const breadcrumbHome = { icon: 'pi pi-home', to: '/dashboard' };
const breadcrumbItems = [{ label: 'Vitrine' }, { label: 'Les avis patients' }];

const filteredItems = computed(() => {
    const needle = String(q.value || '')
        .toLowerCase()
        .trim();

    return items.value.filter((item) => {
        const rating = Number(item.rating);
        if (categoryFilter.value === 'negative' && rating > 2) return false;
        if (categoryFilter.value === 'neutral' && rating !== 3) return false;
        if (categoryFilter.value === 'positive' && rating !== 4) return false;
        if (categoryFilter.value === 'excellent' && rating !== 5) return false;
        if (modeFilter.value === 'anonymous' && item.isAnonymous !== true) return false;
        if (modeFilter.value === 'identified' && item.isAnonymous === true) return false;
        if (modeFilter.value === 'published' && item.isPublished !== true) return false;
        if (modeFilter.value === 'hidden' && item.isPublished === true) return false;

        if (!needle) return true;

        const haystack = [item.comment, item.authorName, item.authorEmail, item.patientName, String(item.consultationId || '')].filter(Boolean).join(' ').toLowerCase();

        return haystack.includes(needle);
    });
});

function formatDate(value) {
    if (!value) return '--';
    const dt = new Date(value);
    if (Number.isNaN(dt.getTime())) return '--';
    return dt.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
}

function ratingLabel(rating) {
    const n = Number(rating || 0);
    if (n <= 2) return 'Insatisfait';
    if (n === 3) return 'Neutre';
    if (n === 4) return 'Satisfait';
    return 'Très satisfait';
}

function stars(rating) {
    const n = Math.max(1, Math.min(5, Number(rating || 0)));
    return '★'.repeat(n) + '☆'.repeat(5 - n);
}

function applyResponse(res) {
    stats.value = {
        total: Number(res?.stats?.total || 0),
        anonymous: Number(res?.stats?.anonymous || 0),
        published: Number(res?.stats?.published || 0),
        averageRating: Number(res?.stats?.averageRating || 0)
    };

    items.value = (Array.isArray(res?.items) ? res.items : []).map((item) => ({
        ...item,
        patientName: item.patientName || (item.patientId ? `Patient #${item.patientId}` : 'Patient non lié')
    }));
}

async function load() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const res = await fetchAdminAppreciations(token, { limit: 300 });
        applyResponse(res);
    } catch (error) {
        errorMessage.value = getHttpErrorMessage(error, 'Impossible de charger les avis patients.');
    } finally {
        loading.value = false;
    }
}

function updateLocalItem(updatedItem) {
    if (!updatedItem?.id) return;
    items.value = items.value.map((item) => (item.id === updatedItem.id ? { ...item, ...updatedItem } : item));
    stats.value = {
        ...stats.value,
        published: items.value.filter((item) => item.isPublished === true).length
    };
}

function removeLocalItem(id) {
    items.value = items.value.filter((item) => item.id !== id);
    stats.value = {
        total: Math.max(0, stats.value.total - 1),
        anonymous: items.value.filter((item) => item.isAnonymous === true).length,
        published: items.value.filter((item) => item.isPublished === true).length,
        averageRating: items.value.length ? items.value.reduce((sum, item) => sum + Number(item.rating || 0), 0) / items.value.length : 0
    };
}

function handleTogglePublish(event, item) {
    if (!item?.id) return;

    const willPublish = item.isPublished !== true;
    confirm.require({
        target: event?.currentTarget,
        message: willPublish ? "Publier cet avis sur l'API publique ?" : "Masquer cet avis de l'API publique ?",
        icon: willPublish ? 'pi pi-globe' : 'pi pi-eye-slash',
        acceptLabel: willPublish ? 'Publier' : 'Masquer',
        rejectLabel: 'Annuler',
        accept: async () => {
            actionLoadingId.value = item.id;
            try {
                const res = await setAdminAppreciationPublished(token, item.id, willPublish);
                updateLocalItem(res?.item);
                toast.add({
                    severity: 'success',
                    summary: willPublish ? 'Avis publié' : 'Avis masqué',
                    detail: willPublish ? "L'avis est visible via l'API publique." : "L'avis n'est plus visible via l'API publique.",
                    life: 3000
                });
            } catch (error) {
                toast.add({
                    severity: 'error',
                    summary: 'Erreur',
                    detail: getHttpErrorMessage(error, "Impossible de modifier la visibilité de l'avis."),
                    life: 4000
                });
            } finally {
                actionLoadingId.value = null;
            }
        }
    });
}

function handleDelete(event, item) {
    if (!item?.id) return;

    confirm.require({
        target: event?.currentTarget,
        message: 'Supprimer définitivement cet avis ? Cette action est irréversible.',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Oui, supprimer',
        rejectLabel: 'Annuler',
        acceptClass: 'p-button-danger',
        accept: async () => {
            actionLoadingId.value = item.id;
            try {
                await deleteAdminAppreciation(token, item.id);
                removeLocalItem(item.id);
                toast.add({
                    severity: 'success',
                    summary: 'Avis supprimé',
                    detail: "L'avis a été supprimé.",
                    life: 3000
                });
            } catch (error) {
                toast.add({
                    severity: 'error',
                    summary: 'Erreur',
                    detail: getHttpErrorMessage(error, "Impossible de supprimer l'avis."),
                    life: 4000
                });
            } finally {
                actionLoadingId.value = null;
            }
        }
    });
}

onMounted(load);
</script>

<template>
    <PageShell>
        <ConfirmPopup />

        <template #header>
            <PageHeader title="Les avis patients" subtitle="Modérez et publiez les retours patients." icon="pi pi-star-fill" tour-id="admin-avis-retours.header" :breadcrumb-items="breadcrumbItems" :breadcrumb-home="breadcrumbHome">
                <template #actions>
                    <Button as="a" :href="exportUrl" target="_blank" rel="noopener" icon="pi pi-download" label="Export site web (JSON)" severity="secondary" outlined size="small" />
                    <Button icon="pi pi-refresh" label="Actualiser" severity="secondary" outlined size="small" :loading="loading" @click="load" />
                </template>
            </PageHeader>
        </template>

        <template #toolbar>
            <div class="page-kpi-grid">
                <div class="page-kpi-card">
                    <div>
                        <p class="page-kpi-label">Total avis</p>
                        <p class="page-kpi-value">{{ stats.total }}</p>
                    </div>
                    <i class="pi pi-chart-line page-kpi-icon text-primary opacity-80"></i>
                </div>
                <div class="page-kpi-card">
                    <div>
                        <p class="page-kpi-label">Avis anonymes</p>
                        <p class="page-kpi-value">{{ stats.anonymous }}</p>
                    </div>
                    <i class="pi pi-user-minus page-kpi-icon text-info opacity-80"></i>
                </div>
                <div class="page-kpi-card">
                    <div>
                        <p class="page-kpi-label">Avis publiés</p>
                        <p class="page-kpi-value">{{ stats.published }}</p>
                    </div>
                    <i class="pi pi-globe page-kpi-icon text-success opacity-80"></i>
                </div>
                <div class="page-kpi-card">
                    <div>
                        <p class="page-kpi-label">Note moyenne</p>
                        <p class="page-kpi-value">{{ stats.averageRating.toFixed(2) }}/5</p>
                    </div>
                    <i class="pi pi-star-fill page-kpi-icon text-warning opacity-80"></i>
                </div>
            </div>
        </template>

        <PageSection title="Liste des avis" subtitle="Consultez les retours et choisissez les avis visibles sur le site." padded>
            <template #headerActions><Tag :value="`${filteredItems.length} avis affiché${filteredItems.length > 1 ? 's' : ''}`" severity="secondary" /></template>
            <VitrineFilterBar>
                <div class="vitrine-filter vitrine-filter--search">
                    <label for="review-search">Rechercher un avis</label>
                    <IconField><InputIcon class="pi pi-search" /><InputText id="review-search" v-model="q" placeholder="Commentaire, auteur ou patient…" fluid /></IconField>
                </div>
                <div class="vitrine-filter">
                    <label for="review-mode">Visibilité et auteur</label><Select input-id="review-mode" v-model="modeFilter" :options="modeOptions" option-label="label" option-value="value" aria-label="Visibilité et auteur" fluid />
                </div>
                <div class="vitrine-filter">
                    <label for="review-category">Satisfaction</label
                    ><Select input-id="review-category" v-model="categoryFilter" :options="categoryOptions" option-label="label" option-value="value" placeholder="Toutes les catégories" aria-label="Satisfaction" fluid />
                </div>
                <Button
                    v-if="q || modeFilter !== 'all' || categoryFilter"
                    label="Effacer"
                    icon="pi pi-filter-slash"
                    severity="secondary"
                    text
                    @click="
                        q = '';
                        modeFilter = 'all';
                        categoryFilter = null;
                    "
                />
            </VitrineFilterBar>
            <div v-if="loading" class="feedback-list" role="status" aria-label="Chargement des avis"><Skeleton v-for="n in 3" :key="n" height="9rem" /></div>
            <Message v-else-if="errorMessage" severity="error" :closable="false">{{ errorMessage }}</Message>
            <VitrineEmptyState
                v-else-if="!filteredItems.length"
                icon="pi pi-star"
                :title="items.length ? 'Aucun avis pour ces filtres' : 'Aucun avis reçu'"
                :description="items.length ? 'Modifiez la recherche ou effacez les filtres pour retrouver les avis.' : 'Les retours des patients apparaîtront ici pour être consultés et publiés.'"
            />
            <div v-else class="feedback-list">
                <article v-for="item in filteredItems" :key="item.id" class="feedback-item">
                    <header class="feedback-header">
                        <div class="feedback-identity">
                            <span class="feedback-avatar" aria-hidden="true"><i :class="item.isAnonymous ? 'pi pi-user-minus' : 'pi pi-user'" /></span>
                            <div>
                                <h4>{{ item.isAnonymous ? 'Patient anonyme' : item.authorName || item.patientName }}</h4>
                                <span class="feedback-date">{{ formatDate(item.createdAt) }}</span>
                            </div>
                        </div>
                        <Tag :value="item.isPublished ? 'Publié' : 'Non publié'" :severity="item.isPublished ? 'success' : 'secondary'" :icon="item.isPublished ? 'pi pi-globe' : 'pi pi-eye-slash'" />
                    </header>
                    <div class="feedback-rating">
                        <span class="feedback-stars" role="img" :aria-label="`${item.rating} sur 5`">{{ stars(item.rating) }}</span
                        ><span>{{ ratingLabel(item.rating) }}</span>
                    </div>
                    <p class="feedback-comment">{{ item.comment || 'Aucun commentaire ajouté.' }}</p>
                    <footer class="feedback-footer">
                        <div class="feedback-meta">
                            <span v-if="!item.isAnonymous"><i class="pi pi-id-card" aria-hidden="true" /> {{ item.patientName }}</span>
                            <span v-if="!item.isAnonymous && item.authorEmail"><i class="pi pi-envelope" aria-hidden="true" /> {{ item.authorEmail }}</span>
                            <span v-if="item.consultationId"><i class="pi pi-calendar" aria-hidden="true" /> Consultation #{{ item.consultationId }}</span>
                        </div>
                        <div class="feedback-actions">
                            <Button
                                :icon="item.isPublished ? 'pi pi-eye-slash' : 'pi pi-globe'"
                                :label="item.isPublished ? 'Masquer' : 'Publier'"
                                :severity="item.isPublished ? 'secondary' : 'primary'"
                                size="small"
                                outlined
                                :loading="actionLoadingId === item.id"
                                :disabled="actionLoadingId !== null"
                                @click="handleTogglePublish($event, item)"
                            />
                            <Button icon="pi pi-trash" label="Supprimer" severity="danger" size="small" text :loading="actionLoadingId === item.id" :disabled="actionLoadingId !== null" @click="handleDelete($event, item)" />
                        </div>
                    </footer>
                </article>
            </div>
        </PageSection>
    </PageShell>
</template>

<style scoped>
.feedback-list {
    display: grid;
    gap: 0.75rem;
}
.feedback-item {
    padding: 1rem;
    border: 1px solid var(--surface-border);
    border-radius: var(--page-section-radius);
    background: var(--surface-card);
    min-width: 0;
}
.feedback-header,
.feedback-footer {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.feedback-identity {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    min-width: 0;
}
.feedback-identity h4 {
    margin: 0 0 0.125rem;
    font-weight: 600;
    overflow-wrap: anywhere;
}
.feedback-avatar {
    display: grid;
    place-items: center;
    flex-shrink: 0;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 50%;
    background: color-mix(in srgb, var(--p-primary-color) 8%, var(--surface-card));
    color: var(--p-primary-color);
}
.feedback-date,
.feedback-meta {
    color: var(--text-color-secondary);
    font-size: var(--page-kpi-meta-size);
}
.feedback-rating {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.625rem;
    margin-top: 0.875rem;
    color: var(--text-color-secondary);
}
.feedback-stars {
    color: var(--p-primary-color);
    letter-spacing: 0.15em;
    font-size: 1rem;
}
.feedback-comment {
    margin: 0.625rem 0 1rem;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
    line-height: 1.6;
}
.feedback-footer {
    padding-top: 0.75rem;
    border-top: 1px solid var(--surface-border);
}
.feedback-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem 1rem;
    min-width: 0;
    flex: 1 1 16rem;
}
.feedback-meta span {
    display: inline-flex;
    align-items: baseline;
    gap: 0.375rem;
    overflow-wrap: anywhere;
    min-width: 0;
}
.feedback-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.375rem;
}
@media (max-width: 639px) {
    .feedback-item {
        padding: 0.75rem;
    }
    .feedback-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
</style>
