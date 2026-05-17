<script setup>
import { onMounted, ref } from 'vue';
import AppLayout from '../../layout/AppLayout.vue';
import { useAuthStore } from '../../stores/auth';
import { fetchDocumentDetail, fetchPatientDocuments } from '../../services/patientPortal';

const authStore = useAuthStore();
const loading = ref(true);
const documents = ref([]);
const detailById = ref({});
const loadingDetail = ref({});
const expandedId = ref(null);

async function toggleDetail(docId) {
    if (expandedId.value === docId) {
        expandedId.value = null;
        return;
    }
    expandedId.value = docId;
    if (!detailById.value[docId]) {
        loadingDetail.value = { ...loadingDetail.value, [docId]: true };
        try {
            const detail = await fetchDocumentDetail(authStore.token, docId);
            detailById.value = { ...detailById.value, [docId]: detail };
        } catch {
            // ignore
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
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <section class="space-y-4">
            <div class="page-hero">
                <i class="pi pi-folder-open hero-icon" />
                <div>
                    <h2 class="hero-title">Documents</h2>
                    <p class="muted m-0 text-sm">Vos factures et documents de consultation.</p>
                </div>
            </div>

            <div v-if="loading" class="grid gap-3">
                <Skeleton height="5.5rem" border-radius="16px" />
                <Skeleton height="5.5rem" border-radius="16px" />
            </div>

            <div v-else-if="documents.length === 0" class="empty-card">
                <i class="pi pi-file empty-icon" />
                <p class="muted m-0">Aucun document trouvé.</p>
            </div>

            <div v-else class="grid gap-3">
                <div v-for="item in documents" :key="item.id" class="doc-card">
                    <div class="doc-file-icon">
                        <i class="pi pi-file-pdf" />
                    </div>
                    <div class="doc-body">
                        <div class="doc-top">
                            <div>
                                <p class="m-0 font-semibold">Facture #{{ item.id }}</p>
                                <p class="m-0 text-sm muted mt-1">{{ formatDate(item.date) }}</p>
                            </div>
                            <Tag :value="item.statut === 'reglee' ? 'Réglée' : 'Ouverte'" :severity="item.statut === 'reglee' ? 'success' : 'warn'" />
                        </div>
                        <div class="doc-amounts">
                            <span class="amount-chip amount-total">
                                <i class="pi pi-money-bill" />
                                {{ Number(item.montant || 0).toLocaleString('fr-FR') }} FCFA
                            </span>
                            <span v-if="Number(item.reste || 0) > 0" class="amount-chip amount-rest">
                                <i class="pi pi-hourglass" />
                                Reste: {{ Number(item.reste || 0).toLocaleString('fr-FR') }} FCFA
                            </span>
                        </div>

                        <!-- Bouton détail -->
                        <button type="button" class="detail-toggle-btn" @click="toggleDetail(item.id)">
                            <i class="pi" :class="expandedId === item.id ? 'pi-chevron-up' : 'pi-list'" />
                            {{ expandedId === item.id ? 'Masquer les lignes' : 'Voir le détail' }}
                        </button>

                        <!-- Détail lignes -->
                        <div v-if="expandedId === item.id" class="doc-detail">
                            <div v-if="loadingDetail[item.id]" class="detail-loading">
                                <i class="pi pi-spin pi-spinner" /> Chargement...
                            </div>
                            <template v-else-if="detailById[item.id]">
                                <div v-if="!detailById[item.id].lignes?.length" class="detail-empty">
                                    Aucune ligne disponible.
                                </div>
                                <div v-else>
                                    <div class="ligne-row ligne-header">
                                        <span>Acte</span><span>Dent</span><span class="text-right">Qté</span><span class="text-right">P.U.</span><span class="text-right">Total</span>
                                    </div>
                                    <div v-for="(ligne, idx) in detailById[item.id].lignes" :key="idx" class="ligne-row">
                                        <span class="ligne-type">{{ ligne.designation || ligne.type || '-' }}</span>
                                        <span class="muted">{{ ligne.dent || '-' }}</span>
                                        <span class="text-right">{{ ligne.quantite || ligne.qte }}</span>
                                        <span class="text-right muted">{{ formatPrice(ligne.prix || ligne.montant) }}</span>
                                        <span class="text-right ligne-total">{{ formatPrice(ligne.total) }}</span>
                                    </div>
                                    <div class="doc-totals">
                                        <div class="total-row">
                                            <span>Montant total</span>
                                            <span class="total-chip total-green">{{ formatPrice(detailById[item.id].montantTotal) }}</span>
                                        </div>
                                        <div v-if="Number(detailById[item.id].restePatient) > 0" class="total-row">
                                            <span>Reste à payer</span>
                                            <span class="total-chip total-orange">{{ formatPrice(detailById[item.id].restePatient) }}</span>
                                        </div>
                                        <div v-if="Number(detailById[item.id].patientPaid) > 0" class="total-row">
                                            <span>Déjà payé</span>
                                            <span class="total-chip total-blue">{{ formatPrice(detailById[item.id].patientPaid) }}</span>
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

<style scoped>
.page-hero {
    background: var(--pp-gradient);
    border-radius: var(--pp-radius);
    padding: 1.1rem 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 16px rgba(30,58,138,0.2);
}

.hero-icon {
    font-size: 1.8rem;
    color: rgba(255,255,255,0.85);
    flex-shrink: 0;
}

.hero-title {
    margin: 0 0 0.15rem;
    font-size: 1.1rem;
    font-weight: 700;
    color: #fff;
}

.page-hero .muted {
    color: rgba(255,255,255,0.75) !important;
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

.doc-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    display: flex;
    align-items: flex-start;
    gap: 0.85rem;
    padding: 0.9rem 1rem;
    transition: box-shadow 0.2s ease, transform 0.18s ease;
}

.doc-card:hover {
    box-shadow: var(--pp-shadow-md);
    transform: translateY(-2px);
}

.doc-file-icon {
    width: 2.8rem;
    height: 2.8rem;
    border-radius: 10px;
    background: #fef2f2;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.doc-file-icon i {
    font-size: 1.25rem;
    color: #dc2626;
}

.doc-body {
    flex: 1;
    display: grid;
    gap: 0.55rem;
}

.doc-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.doc-amounts {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.amount-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    border-radius: 8px;
    padding: 0.25rem 0.6rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.amount-total {
    background: #f0fdf4;
    color: #15803d;
}

.amount-rest {
    background: #fff7ed;
    color: #c2410c;
}

.amount-chip i {
    font-size: 0.75rem;
}

/* Détail facture */
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
    margin-top: 0.2rem;
}
.detail-toggle-btn:hover { background: #dbeafe; }

.doc-detail {
    background: #f8fafc;
    border-radius: var(--pp-radius-sm);
    border: 1px solid var(--p-surface-200);
    padding: 0.75rem;
    font-size: 0.82rem;
    grid-column: 1 / -1;
}
.detail-loading, .detail-empty {
    color: var(--p-text-muted-color);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.82rem;
    font-style: italic;
}
.ligne-row {
    display: grid;
    grid-template-columns: 2fr 1fr 0.5fr 1fr 1fr;
    gap: 0.4rem;
    padding: 0.4rem 0;
    border-bottom: 1px solid var(--p-surface-100);
    align-items: center;
}
.ligne-header {
    font-weight: 700;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--p-text-muted-color);
    border-bottom: 2px solid var(--p-surface-200);
}
.ligne-type { font-weight: 600; color: var(--p-text-color); }
.ligne-total { font-weight: 700; color: #2563eb; }
.text-right { text-align: right; }
.doc-totals {
    display: grid;
    gap: 0.45rem;
    padding-top: 0.65rem;
    border-top: 1px solid var(--p-surface-200);
    margin-top: 0.4rem;
}
.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    font-weight: 600;
}
.total-chip {
    padding: 0.2rem 0.7rem;
    border-radius: 999px;
    font-weight: 700;
    font-size: 0.82rem;
}
.total-green { background: #f0fdf4; color: #16a34a; }
.total-orange { background: #fff7ed; color: #c2410c; }
.total-blue { background: #eff6ff; color: #2563eb; }

.mt-1 { margin-top: 0.25rem; }
</style>
