<script setup>
import Button from 'primevue/button';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Tag from 'primevue/tag';
import { resolveAssuranceLogoUrl } from '@/utils/assuranceUtils';

const props = defineProps({
    cards: { type: Array, default: () => [] },
    unpaidClaims: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    actionLoadingId: { type: Number, default: null }
});

const emit = defineEmits([
    'refresh',
    'view-lots',
    'view-lot-dialog',
    'open-lot',
    'view-claim',
    'collect-patient-share',
    'validate-claim',
    'reject-claim'
]);

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const statusTag = (status) => {
    if (status === 'validated') return { label: 'Validée', severity: 'success' };
    if (status === 'rejected') return { label: 'Rejetée', severity: 'danger' };
    if (status === 'recouvre') return { label: 'Recouvrée', severity: 'info' };
    return { label: 'En attente', severity: 'warning' };
};

const canAct = (id) => props.actionLoadingId === null || props.actionLoadingId !== Number(id);
</script>

<template>
  <div class="flex flex-col gap-8">
    <div class="section-card p-5">
      <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div class="flex items-center gap-3">
          <i class="pi pi-shield text-primary text-xl"></i>
          <div>
            <p class="section-eyebrow">Assurances</p>
            <h2 class="section-title text-xl font-bold">Vue par assureur</h2>
          </div>
        </div>
        <Button icon="pi pi-refresh" label="Rafraîchir" outlined rounded @click="emit('refresh')" />
      </div>

      <div v-if="loading" class="py-12 text-center muted-text">
        <i class="pi pi-spin pi-spinner text-2xl"></i>
      </div>

      <div v-else-if="!cards.length" class="py-12 text-center muted-text">
        Aucune assurance active.
      </div>

      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        <article
          v-for="card in cards"
          :key="card.id || card.code"
          class="assurance-card group"
        >
          <div class="assurance-card-brand">
            <div class="assurance-logo">
              <img
                v-if="resolveAssuranceLogoUrl(card.logoPath)"
                :src="resolveAssuranceLogoUrl(card.logoPath)"
                :alt="card.nom"
                class="assurance-logo-img"
              />
              <i v-else class="pi pi-shield text-4xl text-primary"></i>
            </div>
            <div class="text-center min-w-0 px-2">
              <h3 class="card-title font-bold text-lg truncate">{{ card.nom }}</h3>
              <p class="card-subtitle text-xs uppercase tracking-wide mt-0.5">{{ card.code }}</p>
            </div>
          </div>

          <div class="assurance-card-body">
            <div class="flex justify-between items-baseline">
              <span class="muted-text text-sm">Reliquat total</span>
              <span class="text-lg font-bold text-primary">{{ formatFcfa(card.reliquatTotal) }}</span>
            </div>
            <div v-if="card.pendingClaimsCount" class="muted-text text-xs mt-1">
              {{ card.pendingClaimsCount }} créance(s) en attente
            </div>

            <div v-if="card.dernierLotOuvert" class="lot-highlight mt-4 p-3 rounded-xl">
              <p class="muted-text text-xs font-semibold uppercase mb-1">Dernier lot ouvert</p>
              <p class="card-title font-medium text-sm truncate">{{ card.dernierLotOuvert.description || `Lot #${card.dernierLotOuvert.id}` }}</p>
              <div class="flex flex-wrap gap-2 mt-2 text-xs muted-text">
                <span>{{ card.dernierLotOuvert.nbFactures || 0 }} facture(s)</span>
                <span>·</span>
                <span>{{ formatFcfa(card.dernierLotOuvert.montantTotal) }}</span>
              </div>
            </div>
            <div v-else class="mt-4 text-sm muted-text italic">Aucun lot ouvert</div>
          </div>

          <div class="assurance-card-actions">
            <Button label="Voir" icon="pi pi-arrow-right" size="small" @click="emit('view-lots', card)" />
            <Button
              v-if="card.dernierLotOuvert"
              label="Dernier lot"
              icon="pi pi-eye"
              size="small"
              outlined
              @click="emit('view-lot-dialog', card.dernierLotOuvert)"
            />
            <Button label="Ouvrir lot" icon="pi pi-plus" size="small" severity="secondary" outlined @click="emit('open-lot', card)" />
          </div>
        </article>
      </div>
    </div>

    <div class="section-card p-5">
      <div class="mb-4">
        <h3 class="section-title text-lg font-bold">Parts patient non réglées</h3>
        <p class="muted-text text-sm">Factures assurance avec reste patient à encaisser.</p>
      </div>

      <DataTable
        :value="unpaidClaims"
        :loading="loading"
        striped-rows
        size="small"
        paginator
        :rows="8"
        row-hover
        class="text-sm"
        @row-click="(e) => emit('view-claim', e.data)"
      >
        <Column field="patient" header="Patient" />
        <Column field="telephone" header="Téléphone" />
        <Column header="Assurance">
          <template #body="{ data }">{{ data?.assurance?.nom || '—' }}</template>
        </Column>
        <Column header="Date">
          <template #body="{ data }">{{ data?.dateFacture?.slice(0, 10) || '—' }}</template>
        </Column>
        <Column header="Reste patient">
          <template #body="{ data }">
            <span class="amount-warning font-semibold">{{ formatFcfa(data?.restePatient) }}</span>
          </template>
        </Column>
        <Column header="Statut">
          <template #body="{ data }">
            <Tag :value="statusTag(data?.insuranceStatus).label" :severity="statusTag(data?.insuranceStatus).severity" />
          </template>
        </Column>
        <Column header="Actions" style="min-width: 14rem">
          <template #body="{ data }">
            <div class="flex flex-wrap gap-1" @click.stop>
              <Button
                v-if="data?.availableActions?.canCollectPatient"
                icon="pi pi-wallet"
                label="Encaisser"
                size="small"
                :disabled="!canAct(data.id)"
                @click="emit('collect-patient-share', data)"
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
.section-card {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  border-radius: 1rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
}

.section-eyebrow {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--p-primary-color);
}

.section-title,
.card-title {
  color: var(--text-color);
}

.card-subtitle,
.muted-text {
  color: var(--text-color-secondary);
}

.amount-warning {
  color: #d97706;
}

.assurance-card {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--surface-border);
  border-radius: 1.25rem;
  background: var(--surface-card);
  transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
  overflow: hidden;
}

.assurance-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1);
  border-color: var(--p-primary-200);
}

.assurance-card-brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.875rem;
  padding: 1.5rem 1.25rem 1rem;
  background: linear-gradient(180deg, color-mix(in srgb, var(--p-primary-50) 70%, transparent) 0%, transparent 100%);
}

.assurance-logo {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 6.5rem;
  height: 6.5rem;
  border-radius: 1.25rem;
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
  box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
  flex-shrink: 0;
  padding: 0.75rem;
}

.assurance-logo-img {
  max-height: 4.5rem;
  max-width: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
}

.assurance-card-body {
  padding: 0.5rem 1.25rem 1rem;
  flex: 1;
}

.lot-highlight {
  background: var(--p-surface-50);
  border: 1px solid var(--surface-border);
}

.assurance-card-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
  padding: 0.875rem 1.25rem;
  border-top: 1px solid var(--surface-border);
  background: var(--p-surface-50);
}

.app-dark .assurance-card:hover {
  box-shadow: 0 16px 36px rgba(0, 0, 0, 0.35);
  border-color: var(--p-primary-700);
}

.app-dark .assurance-card-brand {
  background: linear-gradient(180deg, color-mix(in srgb, var(--p-primary-900) 35%, transparent) 0%, transparent 100%);
}

.app-dark .assurance-logo {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}

.app-dark .lot-highlight,
.app-dark .assurance-card-actions {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}

.app-dark .amount-warning {
  color: #fbbf24;
}
</style>
