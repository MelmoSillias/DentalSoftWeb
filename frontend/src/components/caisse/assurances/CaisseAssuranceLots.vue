<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Menu from 'primevue/menu';
import Tag from 'primevue/tag';
import DatePicker from 'primevue/datepicker';
import { resolveAssuranceLogoUrl } from '@/utils/assuranceUtils';

const props = defineProps({
    assurance: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
    unassignedClaims: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    actionLoadingId: { type: Number, default: null }
});

const emit = defineEmits([
    'back',
    'refresh',
    'create-lot',
    'update-lot',
    'view-lot',
    'send-lot',
    'reopen-lot',
    'confirm-lot',
    'unconfirm-lot',
    'refund-lot',
    'view-claim',
    'pay-claim',
    'modify-claim',
    'assign-claim',
    'change-claim-lot'
]);

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const lotStatutTag = (statut) => {
    const map = {
        ouvert: { label: 'Ouvert', severity: 'warning' },
        envoye: { label: 'Envoyé', severity: 'info' },
        confirme: { label: 'Confirmé', severity: 'primary' },
        partiellement_rembourse: { label: 'Partiellement remboursé', severity: 'help' },
        rembourse: { label: 'Remboursé', severity: 'success' },
        recouvre: { label: 'Remboursé', severity: 'success' }
    };
    return map[statut] || { label: statut || '—', severity: 'secondary' };
};

const canAct = (id) => props.actionLoadingId === null || props.actionLoadingId !== Number(id);

const createDialogVisible = ref(false);
const editDialogVisible = ref(false);
const assignDialogVisible = ref(false);
const lotForm = ref({ description: '', dateDebut: null, dateFin: null });
const editingLot = ref(null);
const assignClaim = ref(null);
const selectedAssignLotId = ref(null);
const claimMenu = ref();
const claimMenuItems = ref([]);
const activeClaim = ref(null);

const openLotsOptions = computed(() => props.lots.filter((l) => l.statut === 'ouvert'));

const openCreateDialog = () => {
    lotForm.value = { description: '', dateDebut: new Date(), dateFin: new Date() };
    createDialogVisible.value = true;
};

const submitCreate = () => {
    emit('create-lot', {
        description: lotForm.value.description,
        dateDebut: formatDate(lotForm.value.dateDebut),
        dateFin: formatDate(lotForm.value.dateFin)
    });
    createDialogVisible.value = false;
};

const openEditDialog = (lot) => {
    editingLot.value = lot;
    lotForm.value = {
        description: lot.description || '',
        dateDebut: lot.dateDebut ? new Date(lot.dateDebut) : null,
        dateFin: lot.dateFin ? new Date(lot.dateFin) : null
    };
    editDialogVisible.value = true;
};

const submitEdit = () => {
    if (!editingLot.value) return;
    emit('update-lot', {
        lot: editingLot.value,
        payload: {
            description: lotForm.value.description,
            dateDebut: formatDate(lotForm.value.dateDebut),
            dateFin: formatDate(lotForm.value.dateFin)
        }
    });
    editDialogVisible.value = false;
};

const formatDate = (value) => {
    if (!value) return null;
    const d = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(d.getTime())) return null;
    return d.toISOString().slice(0, 10);
};

const openClaimMenu = (event, claim) => {
    activeClaim.value = claim;
    claimMenuItems.value = [
        {
            label: 'Gestion du lot',
            items: [
                {
                    label: 'Affecter à un lot',
                    icon: 'pi pi-folder-plus',
                    disabled: !openLotsOptions.value.length,
                    command: () => openAssignDialog(claim, false)
                },
                {
                    label: 'Changer de lot',
                    icon: 'pi pi-sync',
                    disabled: true,
                    command: () => openAssignDialog(claim, true)
                }
            ]
        },
        { separator: true },
        {
            label: 'Gestion de la facture',
            items: [
                {
                    label: 'Payer',
                    icon: 'pi pi-wallet',
                    disabled: !(claim?.canPay || Number(claim?.restePatient) > 0),
                    command: () => emit('pay-claim', claim)
                },
                {
                    label: 'Voir',
                    icon: 'pi pi-eye',
                    command: () => emit('view-claim', claim)
                },
                {
                    label: 'Modifier',
                    icon: 'pi pi-pencil',
                    disabled: claim?.canModify === false,
                    command: () => emit('modify-claim', claim)
                }
            ]
        }
    ];
    claimMenu.value?.toggle(event);
};

const openAssignDialog = (claim, isChange) => {
    assignClaim.value = { ...claim, isChange };
    selectedAssignLotId.value = openLotsOptions.value[0]?.id ?? null;
    assignDialogVisible.value = true;
};

const submitAssign = () => {
    if (!assignClaim.value || !selectedAssignLotId.value) return;
    if (assignClaim.value.isChange) {
        emit('change-claim-lot', { claim: assignClaim.value, lotId: selectedAssignLotId.value });
    } else {
        emit('assign-claim', { claim: assignClaim.value, lotId: selectedAssignLotId.value });
    }
    assignDialogVisible.value = false;
};
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-4">
        <Button icon="pi pi-arrow-left" text rounded @click="emit('back')" />
        <div class="assurance-logo-sm">
          <img
            v-if="resolveAssuranceLogoUrl(assurance?.logoPath)"
            :src="resolveAssuranceLogoUrl(assurance?.logoPath)"
            :alt="assurance?.nom"
            class="assurance-logo-sm-img"
          />
          <i v-else class="pi pi-shield text-primary text-2xl"></i>
        </div>
        <div>
          <h2 class="page-title text-xl font-bold">{{ assurance?.nom || 'Assurance' }}</h2>
          <p class="muted-text text-sm">{{ assurance?.code }}</p>
        </div>
      </div>
      <div class="flex gap-2">
        <Button icon="pi pi-refresh" label="Rafraîchir" outlined rounded @click="emit('refresh')" />
        <Button icon="pi pi-plus" label="Créer un lot" @click="openCreateDialog" />
      </div>
    </div>

    <div class="section-panel">
      <div class="mb-4">
        <h3 class="page-title text-lg font-bold">Gestion des lots</h3>
        <p class="muted-text text-sm">Création, modification et transitions du cycle de vie.</p>
      </div>

      <DataTable :value="lots" :loading="loading" striped-rows paginator :rows="10" size="small" row-hover>
        <Column header="Lot">
          <template #body="{ data }">
            <div>
              <p class="page-title font-medium">{{ data.description || `Lot #${data.id}` }}</p>
              <p class="muted-text text-xs">#{{ data.id }}</p>
            </div>
          </template>
        </Column>
        <Column header="Période">
          <template #body="{ data }">
            {{ data.dateDebut || '—' }} → {{ data.dateFin || '—' }}
          </template>
        </Column>
        <Column header="Statut">
          <template #body="{ data }">
            <Tag :value="lotStatutTag(data.statut).label" :severity="lotStatutTag(data.statut).severity" />
          </template>
        </Column>
        <Column field="nbFactures" header="Factures" />
        <Column header="Montant assurance">
          <template #body="{ data }">{{ formatFcfa(data.montantTotal) }}</template>
        </Column>
        <Column header="Reste à rembourser">
          <template #body="{ data }">{{ formatFcfa(data.resteARembourser) }}</template>
        </Column>
        <Column header="Actions" style="min-width: 18rem">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Button icon="pi pi-eye" label="Voir" size="small" text @click="emit('view-lot', data)" />
              <Button
                v-if="data.availableActions?.canEdit"
                icon="pi pi-pencil"
                size="small"
                text
                rounded
                :disabled="!canAct(data.id)"
                @click="openEditDialog(data)"
              />
              <Button
                v-if="data.availableActions?.canSend"
                icon="pi pi-send"
                label="Envoyer"
                size="small"
                :disabled="!canAct(data.id)"
                @click="emit('send-lot', data)"
              />
              <Button
                v-if="data.availableActions?.canReopen"
                icon="pi pi-replay"
                label="Rouvrir"
                size="small"
                outlined
                :disabled="!canAct(data.id)"
                @click="emit('reopen-lot', data)"
              />
              <Button
                v-if="data.availableActions?.canConfirm"
                icon="pi pi-check"
                label="Confirmer"
                size="small"
                :disabled="!canAct(data.id)"
                @click="emit('confirm-lot', data)"
              />
              <Button
                v-if="data.availableActions?.canUnconfirm"
                icon="pi pi-undo"
                label="Retour"
                size="small"
                outlined
                :disabled="!canAct(data.id)"
                @click="emit('unconfirm-lot', data)"
              />
              <Button
                v-if="data.availableActions?.canRefund"
                icon="pi pi-wallet"
                label="Rembourser"
                size="small"
                severity="success"
                :disabled="!canAct(data.id)"
                @click="emit('refund-lot', data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <div class="section-panel">
      <div class="mb-4">
        <h3 class="page-title text-lg font-bold">Factures non affectées</h3>
        <p class="muted-text text-sm">FacturesAssurance clôturées non rattachées à un lot.</p>
      </div>

      <DataTable
        :value="unassignedClaims"
        :loading="loading"
        striped-rows
        paginator
        :rows="8"
        size="small"
        row-hover
        class="text-sm"
      >
        <Column header="Date">
          <template #body="{ data }">{{ data?.dateFacture?.slice(0, 10) || '—' }}</template>
        </Column>
        <Column field="patient" header="Patient" />
        <Column header="Total">
          <template #body="{ data }">{{ formatFcfa(data?.montantTotal) }}</template>
        </Column>
        <Column header="Taux">
          <template #body="{ data }">{{ Number(data?.tauxCouverture || 0) }} %</template>
        </Column>
        <Column header="Reste patient">
          <template #body="{ data }">
            <span class="font-semibold">{{ formatFcfa(data?.restePatient) }}</span>
          </template>
        </Column>
        <Column header="Actions" style="min-width: 6rem">
          <template #body="{ data }">
            <Button icon="pi pi-ellipsis-v" text rounded @click="openClaimMenu($event, data)" />
          </template>
        </Column>
      </DataTable>
      <Menu ref="claimMenu" :model="claimMenuItems" popup />
    </div>

    <Dialog v-model:visible="createDialogVisible" modal header="Créer un lot" class="w-full max-w-lg">
      <div class="flex flex-col gap-4 py-2">
        <div>
          <label class="block text-sm mb-1">Nom</label>
          <InputText v-model="lotForm.description" class="w-full" placeholder="Nom du lot" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm mb-1">Début</label>
            <DatePicker v-model="lotForm.dateDebut" date-format="yy-mm-dd" class="w-full" show-icon />
          </div>
          <div>
            <label class="block text-sm mb-1">Fin</label>
            <DatePicker v-model="lotForm.dateFin" date-format="yy-mm-dd" class="w-full" show-icon />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="createDialogVisible = false" />
        <Button label="Créer" icon="pi pi-check" @click="submitCreate" />
      </template>
    </Dialog>

    <Dialog v-model:visible="editDialogVisible" modal header="Modifier le lot" class="w-full max-w-lg">
      <div class="flex flex-col gap-4 py-2">
        <div>
          <label class="block text-sm mb-1">Nom</label>
          <InputText v-model="lotForm.description" class="w-full" />
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-sm mb-1">Début</label>
            <DatePicker v-model="lotForm.dateDebut" date-format="yy-mm-dd" class="w-full" show-icon />
          </div>
          <div>
            <label class="block text-sm mb-1">Fin</label>
            <DatePicker v-model="lotForm.dateFin" date-format="yy-mm-dd" class="w-full" show-icon />
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="editDialogVisible = false" />
        <Button label="Enregistrer" icon="pi pi-check" @click="submitEdit" />
      </template>
    </Dialog>

    <Dialog v-model:visible="assignDialogVisible" modal header="Affecter à un lot" class="w-full max-w-md">
      <div class="flex flex-col gap-3 py-2">
        <p class="muted-text text-sm">{{ assignClaim?.patient }}</p>
        <label class="block text-sm mb-1">Lot ouvert</label>
        <select v-model="selectedAssignLotId" class="lot-select">
          <option v-for="lot in openLotsOptions" :key="lot.id" :value="lot.id">
            {{ lot.description || `Lot #${lot.id}` }}
          </option>
        </select>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="assignDialogVisible = false" />
        <Button label="Affecter" icon="pi pi-check" :disabled="!selectedAssignLotId" @click="submitAssign" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.section-panel {
  padding: 1.25rem;
  border-radius: 1rem;
  border: 1px solid var(--surface-border);
  background: var(--surface-card);
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
}

.page-title {
  color: var(--text-color);
}

.muted-text {
  color: var(--text-color-secondary);
}

.assurance-logo-sm {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 4.5rem;
  height: 4.5rem;
  border-radius: 1rem;
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  box-shadow: 0 6px 16px rgba(15, 23, 42, 0.06);
  padding: 0.5rem;
}

.assurance-logo-sm-img {
  max-height: 3.25rem;
  max-width: 100%;
  object-fit: contain;
}

.lot-select {
  width: 100%;
  padding: 0.6rem 0.75rem;
  border-radius: 0.5rem;
  border: 1px solid var(--surface-border);
  background: var(--surface-card);
  color: var(--text-color);
}

.app-dark .section-panel {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}
</style>
