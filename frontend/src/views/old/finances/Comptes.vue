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
import ConfirmPopup from 'primevue/confirmpopup';
import Tag from 'primevue/tag';
import { fetchComptes, createCompte, updateCompte, deleteCompte } from '@/services/financeApi';

const toast = useToast();
const confirm = useConfirm();
const token = localStorage.getItem('token');

const comptes = ref([]);
const loading = ref(false);

const compteDialog = ref(false);
const editingId = ref(null);
const compteForm = ref({ nom: '', code: '', description: '', soldeInitial: 0, principal: false });

const loadComptes = async () => {
    try {
        loading.value = true;
        comptes.value = await fetchComptes(token);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Comptes', detail: 'Chargement impossible', life: 3000 });
    } finally {
        loading.value = false;
    }
};

const openCreate = () => {
    editingId.value = null;
    compteForm.value = { nom: '', code: '', description: '', soldeInitial: 0, principal: false };
    compteDialog.value = true;
};

const openEdit = (row) => {
    editingId.value = row.id;
    compteForm.value = {
        nom: row.nom,
        code: row.code,
        description: row.description,
        soldeInitial: row.soldeInitial,
        principal: row.principal
    };
    compteDialog.value = true;
};

const saveCompte = async () => {
    try {
        if (!compteForm.value.nom) {
            toast.add({ severity: 'warn', summary: 'Nom requis', detail: 'Renseignez un nom', life: 2500 });
            return;
        }
        const payload = { ...compteForm.value };
        if (editingId.value) {
            await updateCompte(editingId.value, payload, token);
            toast.add({ severity: 'success', summary: 'Compte', detail: 'Compte mis à jour', life: 2500 });
        } else {
            await createCompte(payload, token);
            toast.add({ severity: 'success', summary: 'Compte', detail: 'Compte créé', life: 2500 });
        }
        compteDialog.value = false;
        await loadComptes();
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Compte', detail: 'Enregistrement impossible', life: 3000 });
    }
};

const confirmDelete = (event, row) => {
    confirm.require({
        target: event.currentTarget,
        message: 'Supprimer ce compte ? Les transactions doivent être vides.',
        icon: 'pi pi-exclamation-triangle',
        acceptClass: 'p-button-danger',
        accept: async () => {
            try {
                await deleteCompte(row.id, token);
                toast.add({ severity: 'success', summary: 'Compte', detail: 'Compte supprimé', life: 2500 });
                await loadComptes();
            } catch (e) {
                console.error(e);
                toast.add({ severity: 'error', summary: 'Suppression', detail: 'Impossible de supprimer', life: 3000 });
            }
        }
    });
};

onMounted(() => {
    loadComptes();
});
</script>

<template>
    <div class="page-shell">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="eyebrow">Finances</p>
                <h1 class="text-2xl font-semibold mb-1">Comptes</h1>
                <p class="muted">Gérez les comptes financiers et leur statut principal.</p>
            </div>
            <div class="flex gap-2">
                <Button label="Nouveau compte" icon="pi pi-plus" severity="success" @click="openCreate" />
                <Button label="Rafraîchir" icon="pi pi-refresh" text @click="loadComptes" />
            </div>
        </div>

        <div class="card">
            <DataTable :value="comptes" dataKey="id" :loading="loading" paginator :rows="10" class="w-full">
                <Column field="nom" header="Nom" sortable></Column>
                <Column field="code" header="Code" sortable></Column>
                <Column field="solde" header="Solde">
                    <template #body="{ data }">{{ Number(data.solde).toLocaleString() }} F CFA</template>
                </Column>
                <Column field="credit" header="Crédits"></Column>
                <Column field="debit" header="Débits"></Column>
                <Column field="principal" header="Principal">
                    <template #body="{ data }">
                        <Tag :value="data.principal ? 'Oui' : 'Non'" :severity="data.principal ? 'success' : 'secondary'" />
                    </template>
                </Column>
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

        <Dialog v-model:visible="compteDialog" header="Compte" :modal="true" :style="{ width: '520px' }">
            <div class="grid grid-cols-1 gap-3">
                <div>
                    <label>Nom</label>
                    <InputText v-model="compteForm.nom" class="w-full" />
                </div>
                <div>
                    <label>Code</label>
                    <InputText v-model="compteForm.code" class="w-full" />
                </div>
                <div>
                    <label>Solde initial</label>
                    <InputNumber v-model="compteForm.soldeInitial" mode="decimal" locale="fr-FR" :minFractionDigits="0" class="w-full" />
                </div>
                <div>
                    <label>Description</label>
                    <InputText v-model="compteForm.description" class="w-full" />
                </div>
                <div class="flex items-center gap-2">
                    <input id="principal" v-model="compteForm.principal" type="checkbox" />
                    <label for="principal" class="cursor-pointer">Compte principal</label>
                </div>
            </div>
            <template #footer>
                <Button label="Annuler" text @click="compteDialog = false" />
                <Button label="Enregistrer" severity="success" @click="saveCompte" />
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
