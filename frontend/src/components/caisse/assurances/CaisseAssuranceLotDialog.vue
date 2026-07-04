<script setup>
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import { computed } from 'vue';

const props = defineProps({
    visible: { type: Boolean, default: false },
    lot: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    actionLoading: { type: Boolean, default: false }
});

const emit = defineEmits(['update:visible', 'send-lot', 'recover-lot', 'cancel-recovery', 'view-claim', 'refresh']);

const modelVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const lotStatutTag = (statut) => {
    if (statut === 'envoye') return { label: 'Envoyé', severity: 'info' };
    if (statut === 'recouvre') return { label: 'Recouvré', severity: 'success' };
    if (statut === 'ouvert') return { label: 'Ouvert', severity: 'warning' };
    return { label: statut || '—', severity: 'secondary' };
};
</script>

<template>
  <Dialog
    v-model:visible="modelVisible"
    modal
    :header="lot?.description || `Lot #${lot?.id || ''}`"
    :style="{ width: 'min(920px, 96vw)' }"
    :draggable="false"
  >
    <div v-if="loading" class="py-8 text-center"><i class="pi pi-spin pi-spinner"></i></div>
    <div v-else-if="lot" class="flex flex-col gap-5">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <Tag :value="lotStatutTag(lot.statut).label" :severity="lotStatutTag(lot.statut).severity" />
        <div class="text-sm text-surface-500">
          {{ lot.dateDebut }} → {{ lot.dateFin }}
        </div>
      </div>

      <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="stat-box">
          <span class="stat-label">Factures</span>
          <span class="stat-value">{{ lot.nbFactures || lot.factures?.length || 0 }}</span>
        </div>
        <div class="stat-box">
          <span class="stat-label">Montant assurance</span>
          <span class="stat-value">{{ formatFcfa(lot.montantTotal) }}</span>
        </div>
        <div v-if="lot.dateEnvoi" class="stat-box">
          <span class="stat-label">Date envoi</span>
          <span class="stat-value text-sm">{{ lot.dateEnvoi?.slice(0, 16) }}</span>
        </div>
        <div v-if="lot.dateRecouvrement" class="stat-box">
          <span class="stat-label">Date recouvrement</span>
          <span class="stat-value text-sm">{{ lot.dateRecouvrement?.slice(0, 16) }}</span>
        </div>
      </div>

      <div v-if="lot.transaction" class="transaction-box p-4 rounded-xl">
        <p class="muted-text text-xs font-semibold uppercase mb-2">Transaction liée</p>
        <p class="page-title font-medium">{{ lot.transaction.description }}</p>
        <p class="muted-text text-sm mt-1">
          {{ formatFcfa(lot.transaction.montant) }} · {{ lot.transaction.modeDePaiement?.libelle }}
        </p>
      </div>

      <DataTable :value="lot.factures || []" size="small" striped-rows>
        <Column field="patient" header="Patient" />
        <Column field="telephone" header="Téléphone" />
        <Column header="Date">
          <template #body="{ data }">{{ data.dateFacture?.slice(0, 10) }}</template>
        </Column>
        <Column header="Part assurance">
          <template #body="{ data }">{{ formatFcfa(data.montantAssurance) }}</template>
        </Column>
        <Column header="Actions">
          <template #body="{ data }">
            <Button icon="pi pi-eye" size="small" text rounded @click="emit('view-claim', data)" />
          </template>
        </Column>
      </DataTable>

      <div class="flex flex-wrap gap-2 justify-end">
        <Button
          v-if="lot.availableActions?.canSend"
          icon="pi pi-send"
          label="Envoyer le lot"
          :loading="actionLoading"
          @click="emit('send-lot', lot)"
        />
        <Button
          v-if="lot.availableActions?.canRecover"
          icon="pi pi-wallet"
          label="Encaisser le lot"
          severity="success"
          :loading="actionLoading"
          @click="emit('recover-lot', lot)"
        />
        <Button
          v-if="lot.availableActions?.canCancelRecovery"
          icon="pi pi-times"
          label="Annuler encaissement"
          severity="danger"
          outlined
          :loading="actionLoading"
          @click="emit('cancel-recovery', lot)"
        />
      </div>
    </div>
  </Dialog>
</template>

<style scoped>
.page-title {
  color: var(--text-color);
}

.muted-text {
  color: var(--text-color-secondary);
}

.stat-box {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 0.875rem;
  border-radius: 0.75rem;
  background: var(--p-surface-50);
  border: 1px solid var(--surface-border);
}

.transaction-box {
  background: var(--p-surface-50);
  border: 1px solid var(--surface-border);
}

.stat-label {
  font-size: 0.75rem;
  color: var(--text-color-secondary);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.stat-value {
  font-weight: 700;
  font-size: 1.05rem;
  color: var(--text-color);
}

.app-dark .stat-box,
.app-dark .transaction-box {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}
</style>
