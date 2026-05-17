<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import AppLayout from '../../layout/AppLayout.vue';
import { useAuthStore } from '../../stores/auth';
import { fetchPatientPayments } from '../../services/patientPortal';

const authStore = useAuthStore();
const router = useRouter();
const loading = ref(true);
const payments = ref([]);

const totalPaid = computed(() => payments.value.reduce((sum, item) => sum + Number(item?.montant || 0), 0));

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
        const res = await fetchPatientPayments(token);
        payments.value = Array.isArray(res?.items) ? res.items : [];
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <AppLayout>
        <section class="space-y-4">
            <!-- Bannière total -->
            <div class="total-banner">
                <div class="total-icon-wrap">
                    <i class="pi pi-wallet total-icon" />
                </div>
                <div>
                    <p class="total-label">Montant total réglé</p>
                    <p class="total-value">{{ Math.round(totalPaid).toLocaleString('fr-FR') }} FCFA</p>
                </div>
                <Button text icon="pi pi-folder-open" label="Mes documents" size="small" class="ml-auto" @click="router.push('/documents')" />
            </div>

            <div v-if="loading" class="grid gap-3">
                <Skeleton height="5.5rem" border-radius="16px" />
                <Skeleton height="5.5rem" border-radius="16px" />
            </div>

            <div v-else-if="payments.length === 0" class="empty-card">
                <i class="pi pi-credit-card empty-icon" />
                <p class="muted m-0">Aucun paiement enregistré.</p>
            </div>

            <div v-else class="grid gap-3">
                <div v-for="item in payments" :key="item.id" class="payment-card">
                    <div class="payment-accent" />
                    <div class="payment-body">
                        <div class="payment-top">
                            <div>
                                <p class="m-0 font-bold payment-amount">{{ Number(item?.montant || 0).toLocaleString('fr-FR') }} FCFA</p>
                                <p class="m-0 text-sm muted mt-1">{{ formatDate(item.date) }}</p>
                            </div>
                            <Tag :value="item.validationStatus || 'En attente'" :severity="item.validated ? 'success' : 'warn'" />
                        </div>
                        <div class="payment-meta">
                            <span class="meta-pill"><i class="pi pi-credit-card" /> {{ item.modePaiement || 'Mode non renseigné' }}</span>
                            <span v-if="item.description" class="muted text-xs">{{ item.description }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </AppLayout>
</template>

<style scoped>
.total-banner {
    background: var(--pp-gradient);
    border-radius: var(--pp-radius);
    padding: 1.1rem 1.2rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 16px rgba(30,58,138,0.2);
    flex-wrap: wrap;
}

.total-icon-wrap {
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.total-icon {
    font-size: 1.4rem;
    color: #fff;
}

.total-label {
    margin: 0;
    font-size: 0.8rem;
    color: rgba(255,255,255,0.8);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.total-value {
    margin: 0;
    font-size: 1.4rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
}

:deep(.total-banner .p-button) {
    color: rgba(255,255,255,0.9) !important;
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

.payment-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    display: flex;
    overflow: hidden;
    transition: box-shadow 0.2s ease, transform 0.18s ease;
}

.payment-card:hover {
    box-shadow: var(--pp-shadow-md);
    transform: translateY(-2px);
}

.payment-accent {
    width: 4px;
    flex-shrink: 0;
    background: linear-gradient(180deg, #22c55e, #16a34a);
}

.payment-body {
    flex: 1;
    padding: 0.9rem 1rem;
    display: grid;
    gap: 0.55rem;
}

.payment-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 0.5rem;
}

.payment-amount {
    font-size: 1.15rem;
    color: #15803d;
}

.payment-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.meta-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8rem;
    color: var(--p-text-muted-color);
    background: var(--p-surface-50);
    border-radius: 6px;
    padding: 0.2rem 0.5rem;
}

.meta-pill i {
    font-size: 0.75rem;
}

.mt-1 { margin-top: 0.25rem; }
</style>
