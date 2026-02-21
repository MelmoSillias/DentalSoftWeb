<script setup>
import { onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { useConfirm } from 'primevue/useconfirm';
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import ConfirmPopup from 'primevue/confirmpopup';
import Tag from 'primevue/tag';
import { fetchComptes, fetchTransactions, createTransaction, updateTransaction, deleteTransaction } from '@/services/financeApi';

const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');

const transactions = ref([]);
const comptes = ref([]);
const loading = ref(false);
const comptesLoading = ref(false);

const filters = ref({ compte: null, type: null, search: '', from: '', to: '' });

const transactionDialog = ref(false);
const editingId = ref(null);
const transactionForm = ref({ compte: null, type: 'credit', montant: 0, libelle: '', categorie: '', reference: '', note: '', occurredAt: '' });

const typeOptions = [
    { label: 'Crédit', value: 'credit' },
    { label: 'Débit', value: 'debit' }
];

const loadComptes = async () => {
    try {
        comptesLoading.value = true;
        comptes.value = await fetchComptes(token);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Comptes', detail: 'Impossible de charger les comptes', life: 3000 });
    } finally {
        comptesLoading.value = false;
    }
};

const loadTransactions = async () => {
    try {
        loading.value = true;
        const params = { ...filters.value };
        if (params.from === '') delete params.from;
        if (params.to === '') delete params.to;
        transactions.value = await fetchTransactions(params, token);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Transactions', detail: 'Chargement impossible', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editingId.value = null;
    transactionForm.value = { compte: null, type: 'credit', montant: 0, libelle: '', categorie: '', reference: '', note: '', occurredAt: new Date().toISOString().slice(0, 10) };
    transactionDialog.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    transactionForm.value = {
        compte: row.compte,
        type: row.type,
        montant: row.montant,
        libelle: row.libelle,
        categorie: row.categorie,
        reference: row.reference,
        note: row.note,
        occurredAt: row.occurredAt?.slice(0, 10) ?? ''
    };
    transactionDialog.value = true;
};

const saveTransaction = async () => {
    try {
        const payload = { ...transactionForm.value };
        if (!payload.compte) {
            toast.add({ severity: 'warn', summary: 'Compte requis', detail: 'Sélectionnez un compte', life: 2500 });
            return;
        }
        if (!payload.occurredAt) {
            payload.occurredAt = new Date().toISOString();
        }
        if (editingId.value) {
            await updateTransaction(editingId.value, payload, token);
            toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction mise à jour', life: 2500 });
        } else {
            await createTransaction(payload, token);
            toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction créée', life: 2500 });
        }
        transactionDialog.value = false;
        await loadTransactions();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Transaction', detail: "Enregistrement impossible", life: 3000 });
    }
};

const confirmDelete = (event, row) => {
    confirm.require({
        target: event.currentTarget,
        message: 'Supprimer cette transaction ? Cette action est définitive.',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await deleteTransaction(row.id, token);
                toast.add({ severity: 'success', summary: 'Transaction', detail: 'Transaction supprimée', life: 2500 });
                await loadTransactions();
            } catch (e) {
                console.error(e);
                toast.add({ severity: 'error', summary: 'Suppression', detail: 'Impossible de supprimer', life: 3000 });
            }
        }
    });
};

onMounted(async () => {
    await loadComptes();
    await loadTransactions();
});
</script>

<template>
    <div class="page-shell">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="eyebrow">Finances</p>
                <h1 class="text-2xl font-semibold mb-1">Transactions</h1>
                <p class="muted">Suivi des entrées et sorties par compte.</p>
            </div>
            <div class="flex gap-2">
                <Button label="Nouvelle transaction" icon="pi pi-plus" severity="success" @click="openCreate" />
                <Button label="Rafraîchir" icon="pi pi-refresh" text @click="loadTransactions" />
            </div>
        </div>

        <div class="card mb-4">
            <h3 class="text-lg font-semibold mb-3">Filtres</h3>
            <div class="grid md:grid-cols-5 gap-3">
                <div>
                    <label class="text-sm text-gray-600">Compte</label>
                    <Select v-model="filters.compte" :options="comptes.map((c) => ({ label: c.nom, value: c.id }))" optionLabel="label" optionValue="value" placeholder="Tous" :loading="comptesLoading" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Type</label>
                    <Select v-model="filters.type" :options="typeOptions" optionLabel="label" optionValue="value" placeholder="Tous" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Recherche</label>
                    <InputText v-model="filters.search" placeholder="Libellé, réf..." class="w-full" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Du</label>
                    <InputText v-model="filters.from" type="date" class="w-full" />
                </div>
                <div>
                    <label class="text-sm text-gray-600">Au</label>
                    <InputText v-model="filters.to" type="date" class="w-full" />
                </div>
            </div>
            <div class="mt-3 flex gap-2">
                <Button label="Appliquer" icon="pi pi-filter" @click="loadTransactions" />
                <Button label="Réinitialiser" icon="pi pi-times" severity="secondary" @click="() => { filters.value = { compte: null, type: null, search: '', from: '', to: '' }; loadTransactions(); }" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="transactions" dataKey="id" :loading="loading" paginator :rows="10" class="w-full">
                <Column field="occurredAt" header="Date" sortable></Column>
                <Column field="compteNom" header="Compte" sortable></Column>
                <Column field="type" header="Type" sortable>
                    <template #body="{ data }">
                        <Tag :value="data.type === 'credit' ? 'Crédit' : 'Débit'" :severity="data.type === 'credit' ? 'success' : 'danger'" />
                    </template>
                </Column>
                <Column field="montant" header="Montant" sortable>
                    <template #body="{ data }">{{ Number(data.montant).toLocaleString() }} F CFA</template>
                </Column>
                <Column field="libelle" header="Libellé"></Column>
                <Column field="reference" header="Réf."></Column>
                <Column header="Actions">
                    <template #body="{ data }">
                        <div class="flex gap-2">
                            <Button icon="pi pi-pencil" text @click="openEdit(data)" />
                            <Button icon="pi pi-trash" text severity="danger" @click="(e) => confirmDelete(e, data)" />
                        </div>
                    </template>
                </Column>
            </DataTable>
        </div>

        <Dialog v-model:visible="transactionDialog" header="Transaction" :modal="true" :style="{ width: '520px' }">
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label>Compte</label>
                    <Select v-model="transactionForm.compte" :options="comptes.map((c) => ({ label: c.nom, value: c.id }))" optionLabel="label" optionValue="value" placeholder="Sélectionner" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label>Type</label>
                        <Select v-model="transactionForm.type" :options="typeOptions" optionLabel="label" optionValue="value" />
                    </div>
                    <div>
                        <label>Montant</label>
                        <InputNumber v-model="transactionForm.montant" mode="decimal" locale="fr-FR" :minFractionDigits="0" class="w-full" />
                    </div>
                </div>
                <div>
                    <label>Libellé</label>
                    <InputText v-model="transactionForm.libelle" class="w-full" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label>Catégorie</label>
                        <InputText v-model="transactionForm.categorie" class="w-full" />
                    </div>
                    <div>
                        <label>Référence</label>
                        <InputText v-model="transactionForm.reference" class="w-full" />
                    </div>
                </div>
                <div>
                    <label>Date</label>
                    <InputText v-model="transactionForm.occurredAt" type="date" class="w-full" />
                </div>
                <div>
                    <label>Note</label>
                    <InputText v-model="transactionForm.note" class="w-full" />
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text @click="transactionDialog = false" />
                <Button label="Enregistrer" severity="success" @click="saveTransaction" />
            </template>
        </Dialog>

        <ConfirmPopup />
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
