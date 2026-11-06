<script setup>
import CollectionToolbar from '../../components/CollectionToolbar.vue';
import { computed, onMounted, ref } from 'vue';
import AppLayout from '../../layout/AppLayout.vue';
import { useAuthStore } from '../../stores/auth';
import {
    fetchDocumentDetail,
    fetchPatientDocuments,
} from '../../services/patientPortal';

const authStore = useAuthStore();
const loading = ref(true);
const documents = ref([]);
const detailById = ref({});
const loadingDetail = ref({});
const detailErrors = ref({});
const expandedId = ref(null);

async function toggleDetail(docId) {
    if (expandedId.value === docId) {
        expandedId.value = null;
        return;
    }
    expandedId.value = docId;
    if (!detailById.value[docId]) {
        loadingDetail.value = { ...loadingDetail.value, [docId]: true };
        detailErrors.value[docId] = '';
        try {
            const detail = await fetchDocumentDetail(authStore.token, docId);
            detailById.value = { ...detailById.value, [docId]: detail };
        } catch {
            detailErrors.value[docId] =
                'Ce détail est indisponible. Réessayez dans un instant.';
        } finally {
            loadingDetail.value = { ...loadingDetail.value, [docId]: false };
        }
    }
}

function formatPrice(val) {
    return Number(val || 0).toLocaleString('fr-FR') + ' FCFA';
}

function formatDate(value) {
    if (!value) return '--';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '--';
    return date.toLocaleDateString('fr-FR');
}

onMounted(async () => {
    authStore.hydrate();
    const token = authStore.token;
    if (!token) {
        loading.value = false;
        return;
    }

    try {
        const res = await fetchPatientDocuments(token);
        documents.value = Array.isArray(res?.items) ? res.items : [];
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
    { label: 'Réglés', value: 'paid' },
    { label: 'À régler', value: 'unpaid' },
];
const visibleItems = computed(() =>
    documents.value.filter((item) => {
        if (filter.value === 'paid' && item.statut !== 'reglee') return false;
        if (filter.value === 'unpaid' && item.statut === 'reglee') return false;
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
    <AppLayout>
        <section class="space-y-4">
            <div class="page-hero">
                <i class="pi pi-folder-open hero-icon" />
                <div>
                    <h2 class="hero-title">Documents</h2>
                    <p class="muted m-0 text-sm">
                        Vos factures et documents de consultation.
                    </p>
                </div>
            </div>

            <CollectionToolbar
                v-if="!loading && !loadError"
                v-model:search="search"
                v-model:filter="filter"
                :filters="filters"
                :count="visibleItems.length"
                placeholder="Rechercher un document..."
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
                v-else-if="!loadError && documents.length === 0"
                class="empty-card"
            >
                <i class="pi pi-file empty-icon" />
                <p class="muted m-0">Aucun document trouvé.</p>
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
                    class="doc-card"
                >
                    <div class="doc-file-icon">
                        <i class="pi pi-file-pdf" />
                    </div>
                    <div class="doc-body">
                        <div class="doc-top">
                            <div>
                                <p class="m-0 font-semibold">
                                    Facture #{{ item.id }}
                                </p>
                                <p class="m-0 text-sm muted mt-1">
                                    {{ formatDate(item.date) }}
                                </p>
                            </div>
                            <Tag
                                :value="
                                    item.statut === 'reglee'
                                        ? 'Réglée'
                                        : 'Ouverte'
                                "
                                :severity="
                                    item.statut === 'reglee'
                                        ? 'success'
                                        : 'warn'
                                "
                            />
                        </div>
                        <div class="doc-amounts">
                            <span class="amount-chip amount-total">
                                <i class="pi pi-money-bill" />
                                {{
                                    Number(item.montant || 0).toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                FCFA
                            </span>
                            <span
                                v-if="Number(item.reste || 0) > 0"
                                class="amount-chip amount-rest"
                            >
                                <i class="pi pi-hourglass" />
                                Reste:
                                {{
                                    Number(item.reste || 0).toLocaleString(
                                        'fr-FR',
                                    )
                                }}
                                FCFA
                            </span>
                        </div>

                        <!-- Bouton détail -->
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
                                    ? 'Masquer les lignes'
                                    : 'Voir le détail'
                            }}
                        </button>

                        <!-- Détail lignes -->
                        <div v-if="expandedId === item.id" class="doc-detail">
                            <div
                                v-if="loadingDetail[item.id]"
                                class="detail-loading"
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
                                    v-if="!detailById[item.id].lignes?.length"
                                    class="detail-empty"
                                >
                                    Aucune ligne disponible.
                                </div>
                                <div v-else>
                                    <div class="ligne-row ligne-header">
                                        <span>Acte</span><span>Dent</span
                                        ><span class="text-right">Qté</span
                                        ><span class="text-right">P.U.</span
                                        ><span class="text-right">Total</span>
                                    </div>
                                    <div
                                        v-for="(ligne, idx) in detailById[
                                            item.id
                                        ].lignes"
                                        :key="idx"
                                        class="ligne-row"
                                    >
                                        <span class="ligne-type">{{
                                            ligne.designation ||
                                            ligne.type ||
                                            '-'
                                        }}</span>
                                        <span class="muted">{{
                                            ligne.dent || '-'
                                        }}</span>
                                        <span class="text-right">{{
                                            ligne.quantite || ligne.qte
                                        }}</span>
                                        <span class="text-right muted">{{
                                            formatPrice(
                                                ligne.prix || ligne.montant,
                                            )
                                        }}</span>
                                        <span class="text-right ligne-total">{{
                                            formatPrice(ligne.total)
                                        }}</span>
                                    </div>
                                    <div class="doc-totals">
                                        <div class="total-row">
                                            <span>Montant total</span>
                                            <span
                                                class="total-chip total-green"
                                                >{{
                                                    formatPrice(
                                                        detailById[item.id]
                                                            .montantTotal,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                Number(
                                                    detailById[item.id]
                                                        .restePatient,
                                                ) > 0
                                            "
                                            class="total-row"
                                        >
                                            <span>Reste à payer</span>
                                            <span
                                                class="total-chip total-orange"
                                                >{{
                                                    formatPrice(
                                                        detailById[item.id]
                                                            .restePatient,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                Number(
                                                    detailById[item.id]
                                                        .patientPaid,
                                                ) > 0
                                            "
                                            class="total-row"
                                        >
                                            <span>Déjà payé</span>
                                            <span
                                                class="total-chip total-blue"
                                                >{{
                                                    formatPrice(
                                                        detailById[item.id]
                                                            .patientPaid,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
