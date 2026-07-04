<script setup>
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { resolveAssuranceLogoUrl } from '@/utils/assuranceUtils';

const props = defineProps({
    assurance: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
    unassignedClaims: { type: Array, default: () => [] },
    openLot: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    actionLoadingId: { type: Number, default: null }
});

const emit = defineEmits([
    'back',
    'refresh',
    'open-lot',
    'view-lot',
    'send-lot',
    'recover-lot',
    'cancel-recovery',
    'view-claim',
    'add-claim-to-lot'
]);

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const lotStatutTag = (statut) => {
    if (statut === 'envoye') return { label: 'Envoyé', severity: 'info' };
    if (statut === 'recouvre') return { label: 'Recouvré', severity: 'success' };
    if (statut === 'ouvert') return { label: 'Ouvert', severity: 'warning' };
    return { label: statut || '—', severity: 'secondary' };
};

const canAct = (id) => props.actionLoadingId === null || props.actionLoadingId !== Number(id);
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
        <Button icon="pi pi-plus" label="Ouvrir un lot" @click="emit('open-lot')" />
      </div>
    </div>

    <div class="section-panel">
      <div class="mb-4">
        <h3 class="page-title text-lg font-bold">Lots</h3>
        <p class="muted-text text-sm">Historique des lots pour cet assureur.</p>
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
        <Column header="Montant">
          <template #body="{ data }">{{ formatFcfa(data.montantTotal) }}</template>
        </Column>
        <Column header="Envoi / Recouvrement">
          <template #body="{ data }">
            <div class="text-xs muted-text">
              <div v-if="data.dateEnvoi">Envoyé: {{ data.dateEnvoi?.slice(0, 10) }}</div>
              <div v-if="data.dateRecouvrement">Recouvré: {{ data.dateRecouvrement?.slice(0, 10) }}</div>
            </div>
          </template>
        </Column>
        <Column header="Actions" style="min-width: 16rem">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1">
              <Button icon="pi pi-eye" label="Voir" size="small" text @click="emit('view-lot', data)" />
              <Button
                v-if="data.availableActions?.canSend"
                icon="pi pi-send"
                label="Envoyer"
                size="small"
                :disabled="!canAct(data.id)"
                @click="emit('send-lot', data)"
              />
              <Button
                v-if="data.availableActions?.canRecover"
                icon="pi pi-wallet"
                label="Encaisser"
                size="small"
                severity="success"
                :disabled="!canAct(data.id)"
                @click="emit('recover-lot', data)"
              />
              <Button
                v-if="data.availableActions?.canCancelRecovery"
                icon="pi pi-times"
                label="Annuler"
                size="small"
                severity="danger"
                outlined
                :disabled="!canAct(data.id)"
                @click="emit('cancel-recovery', data)"
              />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>

    <div class="section-panel">
      <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
          <h3 class="page-title text-lg font-bold">Factures hors lot</h3>
          <p class="muted-text text-sm">
            Factures validées non rattachées à un lot.
            <span v-if="openLot" class="text-primary font-medium">
              Lot ouvert : {{ openLot.description || `Lot #${openLot.id}` }}
            </span>
            <span v-else class="alert-text font-medium">Aucun lot ouvert — ouvrez un lot pour y ajouter des factures.</span>
          </p>
        </div>
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
        @row-click="(e) => emit('view-claim', e.data)"
      >
        <Column field="patient" header="Patient" />
        <Column field="telephone" header="Téléphone" />
        <Column header="Date">
          <template #body="{ data }">{{ data?.dateFacture?.slice(0, 10) || '—' }}</template>
        </Column>
        <Column header="Taux">
          <template #body="{ data }">{{ Number(data?.tauxCouverture || 0) }} %</template>
        </Column>
        <Column header="Part assurance">
          <template #body="{ data }">
            <span class="font-semibold text-primary">{{ formatFcfa(data?.montantAssurance) }}</span>
          </template>
        </Column>
        <Column header="Statut">
          <template #body>
            <Tag value="Validée" severity="success" />
          </template>
        </Column>
        <Column header="Actions" style="min-width: 12rem">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1" @click.stop>
              <Button
                v-if="openLot"
                icon="pi pi-plus"
                label="Ajouter au lot"
                size="small"
                :disabled="!canAct(data.id)"
                @click="emit('add-claim-to-lot', data)"
              />
              <Button icon="pi pi-eye" size="small" text rounded @click="emit('view-claim', data)" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
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

.alert-text {
  color: #d97706;
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
  width: auto;
  height: auto;
  object-fit: contain;
}

.app-dark .section-panel {
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
}

.app-dark .assurance-logo-sm {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}

.app-dark .alert-text {
  color: #fbbf24;
}
</style>
