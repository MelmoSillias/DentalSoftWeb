<script setup>
import { computed, onMounted, ref } from 'vue';
import Breadcrumb from 'primevue/breadcrumb';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';
import Card from 'primevue/card';
import Badge from 'primevue/badge';
import Toast from 'primevue/toast';
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { deleteAdminAppreciation, fetchAdminAppreciations, setAdminAppreciationPublished } from '@/services/appreciationAdminService';
import { getHttpErrorMessage } from '@/service/http';

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

const modeOptions = [
    { label: 'Tous', value: 'all' },
    { label: 'Anonymes', value: 'anonymous' },
    { label: 'Identifiés', value: 'identified' },
    { label: 'Publiés', value: 'published' },
    { label: 'Non publiés', value: 'hidden' }
];

const breadcrumbHome = { icon: 'pi pi-home', to: '/' };
const breadcrumbItems = [{ label: 'Administration' }, { label: 'Avis & retours patients' }];

const filteredItems = computed(() => {
    const needle = String(q.value || '')
        .toLowerCase()
        .trim();

    return items.value.filter((item) => {
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

function ratingSeverity(rating) {
    const n = Number(rating || 0);
    if (n <= 2) return 'danger';
    if (n === 3) return 'warning';
    return 'success';
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
    <Toast />
    <ConfirmPopup />
    <div class="space-y-6 p-4 md:p-6">
        <!-- En-tête avec fil d'Ariane -->
        <Card class="shadow-sm">
            <template #content>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <h2 class="text-2xl font-semibold m-0 flex items-center gap-2">
                        <i class="pi pi-star-fill text-primary"></i>
                        Avis & retours patients
                    </h2>
                    <Button icon="pi pi-refresh" label="Actualiser" severity="secondary" outlined rounded @click="load" :loading="loading" />
                </div>
                <Breadcrumb :home="breadcrumbHome" :model="breadcrumbItems" class="bg-transparent p-0" />
            </template>
        </Card>

        <!-- Cartes statistiques -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <Card class="stat-card">
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-surface-500 dark:text-surface-400 text-sm m-0">Total avis</p>
                            <p class="text-3xl font-bold m-0 mt-1">{{ stats.total }}</p>
                        </div>
                        <i class="pi pi-chart-line text-3xl text-primary opacity-80"></i>
                    </div>
                </template>
            </Card>

            <Card class="stat-card">
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-surface-500 dark:text-surface-400 text-sm m-0">Avis anonymes</p>
                            <p class="text-3xl font-bold m-0 mt-1">{{ stats.anonymous }}</p>
                        </div>
                        <i class="pi pi-user-minus text-3xl text-info opacity-80"></i>
                    </div>
                </template>
            </Card>

            <Card class="stat-card">
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-surface-500 dark:text-surface-400 text-sm m-0">Avis publiés</p>
                            <p class="text-3xl font-bold m-0 mt-1">{{ stats.published }}</p>
                        </div>
                        <i class="pi pi-globe text-3xl text-success opacity-80"></i>
                    </div>
                </template>
            </Card>

            <Card class="stat-card">
                <template #content>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-surface-500 dark:text-surface-400 text-sm m-0">Note moyenne</p>
                            <p class="text-3xl font-bold m-0 mt-1">{{ stats.averageRating.toFixed(2) }}/5</p>
                        </div>
                        <i class="pi pi-star-fill text-3xl text-warning opacity-80"></i>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Zone de filtres et liste -->
        <Card class="shadow-sm">
            <template #content>
                <div class="flex flex-col md:flex-row gap-4 mb-6">
                    <!-- Recherche avec IconField PrimeVue V4 -->
                    <IconField class="flex-1" iconPosition="left">
                        <InputIcon class="pi pi-search" />
                        <InputText v-model="q" placeholder="Rechercher commentaire, auteur, patient..." class="w-full" fluid />
                    </IconField>

                    <Select v-model="modeFilter" :options="modeOptions" optionLabel="label" optionValue="value" placeholder="Filtrer par statut" class="w-full md:w-64" fluid />
                </div>

                <!-- États de chargement / erreur / vide -->
                <div v-if="loading" class="grid gap-3">
                    <Skeleton height="7rem" class="rounded-lg" />
                    <Skeleton height="7rem" class="rounded-lg" />
                    <Skeleton height="7rem" class="rounded-lg" />
                </div>

                <Message v-else-if="errorMessage" severity="error" :closable="false" class="rounded-lg">
                    {{ errorMessage }}
                </Message>

                <Message v-else-if="filteredItems.length === 0" severity="info" :closable="false" class="rounded-lg">
                    <i class="pi pi-info-circle mr-2"></i>
                    Aucun avis ne correspond au filtre courant.
                </Message>

                <!-- Liste des avis améliorée -->
                <div v-else class="grid gap-4">
                    <article v-for="item in filteredItems" :key="item.id" class="feedback-item p-4 rounded-xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 transition-all duration-200 hover:shadow-md">
                        <header class="flex flex-wrap justify-between items-start gap-3 mb-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <Tag :value="stars(item.rating)" :severity="ratingSeverity(item.rating)" rounded />
                                <Tag :value="ratingLabel(item.rating)" :severity="ratingSeverity(item.rating)" rounded />
                                <Tag :value="item.isAnonymous ? 'Anonyme' : 'Identifié'" :severity="item.isAnonymous ? 'secondary' : 'info'" rounded />
                                <Tag :value="item.isPublished ? 'Publié' : 'Non publié'" :severity="item.isPublished ? 'success' : 'warning'" rounded />
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge :value="formatDate(item.createdAt)" severity="secondary" class="whitespace-nowrap" />
                                <Button
                                    :icon="item.isPublished ? 'pi pi-eye-slash' : 'pi pi-globe'"
                                    :label="item.isPublished ? 'Masquer' : 'Publier'"
                                    :severity="item.isPublished ? 'warning' : 'success'"
                                    size="small"
                                    outlined
                                    rounded
                                    :loading="actionLoadingId === item.id"
                                    @click="handleTogglePublish($event, item)"
                                />
                                <Button icon="pi pi-trash" label="Supprimer" severity="danger" size="small" outlined rounded :loading="actionLoadingId === item.id" @click="handleDelete($event, item)" />
                            </div>
                        </header>

                        <p class="m-0 text-surface-700 dark:text-surface-300 leading-relaxed mb-4">
                            {{ item.comment }}
                        </p>

                        <footer class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-surface-500 dark:text-surface-400 border-t border-surface-100 dark:border-surface-800 pt-3">
                            <div class="flex items-center gap-1">
                                <i class="pi pi-user text-xs"></i>
                                <span>Auteur: {{ item.isAnonymous ? 'Masqué' : item.authorName || 'N/A' }}</span>
                            </div>
                            <div v-if="!item.isAnonymous && item.authorEmail" class="flex items-center gap-1">
                                <i class="pi pi-envelope text-xs"></i>
                                <span>{{ item.authorEmail }}</span>
                            </div>
                            <div class="flex items-center gap-1">
                                <i class="pi pi-id-card text-xs"></i>
                                <span>{{ item.patientName }}</span>
                            </div>
                            <div v-if="item.consultationId" class="flex items-center gap-1">
                                <i class="pi pi-calendar text-xs"></i>
                                <span>Consultation #{{ item.consultationId }}</span>
                            </div>
                        </footer>
                    </article>
                </div>
            </template>
        </Card>
    </div>
</template>

<style scoped>
/* Style personnalisé pour les cartes statistiques */
.stat-card :deep(.p-card-content) {
    padding: 1.25rem;
}

.stat-card {
    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
}

/* Animation subtile pour les items d'avis */
.feedback-item {
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.feedback-item:hover {
    border-color: var(--primary-color);
}

/* Ajustements pour dark mode */
:root.dark .feedback-item {
    background-color: var(--surface-900);
}
</style>
