<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { fetchFinanceSummary } from '@/services/financeApi';

const toast = useToast();
const token = localStorage.getItem('token');

const summary = ref({ totalCredit: 0, totalDebit: 0, balance: 0, perCompte: [], perMonth: [] });
const filters = ref({ from: '', to: '' });
const loading = ref(false);

const loadSummary = async () => {
    try {
        loading.value = true;
        const params = { ...filters.value };
        if (!params.from) delete params.from;
        if (!params.to) delete params.to;
        summary.value = await fetchFinanceSummary(params, token);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Rapports', detail: 'Impossible de charger le rapport', life: 3000 });
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadSummary();
});
</script>

<template>
    <div class="page-shell">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="eyebrow">Finances</p>
                <h1 class="text-2xl font-semibold mb-1">Rapports financiers</h1>
                <p class="muted">Synthèses des mouvements et soldes.</p>
            </div>
            <Button label="Rafraîchir" icon="pi pi-refresh" text @click="loadSummary" />
        </div>

        <div class="card mb-4">
            <h3 class="text-lg font-semibold mb-3">Période</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div>
                    <label class="text-sm text-gray-600">Du</label>
                    <InputText v-model="filters.from" type="date" class="w-full" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Au</label>
                    <InputText v-model="filters.to" type="date" class="w-full" />
                </div>
                <div class="md:col-span-2 flex items-end gap-2">
                    <Button label="Appliquer" icon="pi pi-filter" @click="loadSummary" />
                    <Button label="Réinitialiser" icon="pi pi-times" severity="secondary" @click="() => { filters.value = { from: '', to: '' }; loadSummary(); }" />
                </div>
            </div>
        </div>

        <div class="grid md:grid-cols-3 gap-4 mb-4">
            <div class="stat-card">
                <p class="muted">Crédits</p>
                <h2 class="stat">{{ Number(summary.totalCredit).toLocaleString() }} F CFA</h2>
            </div>
            <div class="stat-card">
                <p class="muted">Débits</p>
                <h2 class="stat">{{ Number(summary.totalDebit).toLocaleString() }} F CFA</h2>
            </div>
            <div class="stat-card">
                <p class="muted">Balance</p>
                <h2 class="stat" :class="summary.balance >= 0 ? 'text-emerald-600' : 'text-rose-600'">
                    {{ Number(summary.balance).toLocaleString() }} F CFA
                </h2>
            </div>
        </div>

        <div class="card mb-4">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Par compte</h3>
            </div>
            <DataTable :value="summary.perCompte" :loading="loading" paginator :rows="10">
                <Column field="nom" header="Compte"></Column>
                <Column field="code" header="Code"></Column>
                <Column field="credit" header="Crédit">
                    <template #body="{ data }">{{ Number(data.credit).toLocaleString() }} F CFA</template>
                </Column>
                <Column field="debit" header="Débit">
                    <template #body="{ data }">{{ Number(data.debit).toLocaleString() }} F CFA</template>
                </Column>
                <Column header="Balance">
                    <template #body="{ data }">{{ Number(data.credit - data.debit).toLocaleString() }} F CFA</template>
                </Column>
            </DataTable>
        </div>

        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-lg font-semibold">Par mois</h3>
            </div>
            <DataTable :value="summary.perMonth" :loading="loading" paginator :rows="12">
                <Column field="mois" header="Mois"></Column>
                <Column field="credit" header="Crédit">
                    <template #body="{ data }">{{ Number(data.credit).toLocaleString() }} F CFA</template>
                </Column>
                <Column field="debit" header="Débit">
                    <template #body="{ data }">{{ Number(data.debit).toLocaleString() }} F CFA</template>
                </Column>
                <Column header="Balance">
                    <template #body="{ data }">{{ Number(data.credit - data.debit).toLocaleString() }} F CFA</template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>

<style scoped>
.page-shell {
    padding: 1.5rem;
    background: var(--surface-ground);
    min-height: 100vh;
}

.card {
    border-radius: 14px;
    padding: 1.25rem;
    background: var(--surface-card);
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.06);
    border: 1px solid var(--surface-border);
}

.stat-card {
    background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 100%);
    color: #fff;
    border-radius: 14px;
    padding: 1.25rem;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.08);
}

.stat {
    font-size: 1.6rem;
    margin: 0.35rem 0 0;
}

.eyebrow {
    letter-spacing: 0.08em;
    text-transform: uppercase;
    font-size: 0.8rem;
    color: #94a3b8;
    margin: 0;
}

.muted {
    color: #6b7280;
}
</style>
