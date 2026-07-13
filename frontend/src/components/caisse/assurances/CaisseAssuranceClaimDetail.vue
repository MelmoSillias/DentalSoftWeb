<script setup>
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';

defineProps({
    claim: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    actionLoading: { type: Boolean, default: false }
});

const emit = defineEmits([
    'back',
    'collect-patient-share',
    'print-receipt',
    'print-claim'
]);

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const statusTag = (status) => {
    const map = {
        open: { label: 'Consultation ouverte', severity: 'warning' },
        ready: { label: 'Prête', severity: 'success' },
        in_lot: { label: 'Dans un lot', severity: 'info' },
        rembourse: { label: 'Remboursée', severity: 'secondary' }
    };
    return map[status] || { label: status || '—', severity: 'secondary' };
};
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center gap-3">
      <Button icon="pi pi-arrow-left" text rounded @click="emit('back')" />
      <div>
        <h2 class="page-title text-xl font-bold">Facture assurance #{{ claim?.id || '—' }}</h2>
        <p class="muted-text text-sm">{{ claim?.patient }} · {{ claim?.assurance?.nom }}</p>
      </div>
      <Tag v-if="claim" class="ml-auto" :value="statusTag(claim.insuranceStatus).label" :severity="statusTag(claim.insuranceStatus).severity" />
    </div>

    <div v-if="loading" class="py-12 text-center"><i class="pi pi-spin pi-spinner text-2xl"></i></div>

    <template v-else-if="claim">
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div class="info-card">
          <span class="info-label">Assurance</span>
          <span class="info-value">{{ claim.assurance?.nom || '—' }}</span>
        </div>
        <div class="info-card">
          <span class="info-label">Taux de prise en charge</span>
          <span class="info-value">{{ claim.tauxCouverture ?? '—' }}%</span>
        </div>
        <div class="info-card">
          <span class="info-label">Montant total</span>
          <span class="info-value">{{ formatFcfa(claim.montantTotal) }}</span>
        </div>
        <div class="info-card">
          <span class="info-label">Part assurance</span>
          <span class="info-value text-primary">{{ formatFcfa(claim.montantAssurance) }}</span>
        </div>
        <div class="info-card">
          <span class="info-label">Part patient</span>
          <span class="info-value">{{ formatFcfa(claim.montantPatient) }}</span>
        </div>
        <div class="info-card">
          <span class="info-label">Reste patient</span>
          <span class="info-value">{{ formatFcfa(claim.restePatient) }}</span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
        <div><span class="muted-text">Date facture:</span> <span class="page-title">{{ claim.dateFacture?.slice(0, 16) || '—' }}</span></div>
        <div><span class="muted-text">Téléphone:</span> <span class="page-title">{{ claim.telephone || '—' }}</span></div>
        <div v-if="claim.lotId"><span class="muted-text">Lot:</span> <span class="page-title">#{{ claim.lotId }} ({{ claim.lotStatut }})</span></div>
      </div>

      <div class="flex flex-wrap gap-2">
        <Button
          v-if="claim.availableActions?.canCollectPatient"
          icon="pi pi-wallet"
          label="Payer"
          size="small"
          severity="success"
          :loading="actionLoading"
          @click="emit('collect-patient-share', claim)"
        />
        <Button icon="pi pi-print" label="Imprimer facture" size="small" outlined @click="emit('print-claim', claim)" />
      </div>

      <div class="section-card p-4 rounded-xl">
        <h3 class="page-title font-semibold mb-3">Lignes de facturation</h3>
        <DataTable :value="claim.lignes || []" size="small">
          <Column header="Désignation">
            <template #body="{ data }">
              <span>{{ data.designation }}</span>
              <Tag v-if="data.virtual" value="Consultation" severity="secondary" class="ml-2" />
            </template>
          </Column>
          <Column field="quantite" header="Qté" />
          <Column header="Prix">
            <template #body="{ data }">{{ formatFcfa(data.montant || data.prix) }}</template>
          </Column>
          <Column header="Total">
            <template #body="{ data }">{{ formatFcfa(data.total) }}</template>
          </Column>
        </DataTable>
      </div>

      <div class="section-card p-4 rounded-xl">
        <h3 class="page-title font-semibold mb-3">Paiements patient</h3>
        <div v-if="!(claim.paiements || []).length" class="text-sm muted-text italic">Aucun paiement enregistré.</div>
        <DataTable v-else :value="claim.paiements" size="small">
          <Column header="Date">
            <template #body="{ data }">{{ data.date?.slice(0, 16) }}</template>
          </Column>
          <Column field="mode" header="Mode" />
          <Column header="Montant">
            <template #body="{ data }">{{ formatFcfa(data.montant) }}</template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Button
                v-if="data.paiementId"
                icon="pi pi-print"
                label="Reçu"
                size="small"
                text
                @click="emit('print-receipt', data)"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </template>
  </div>
</template>

<style scoped>
.page-title {
  color: var(--text-color);
}

.muted-text {
  color: var(--text-color-secondary);
}

.section-card {
  background: var(--surface-card);
  border: 1px solid var(--surface-border);
}

.info-card {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  padding: 1rem;
  border-radius: 0.875rem;
  background: var(--p-surface-50);
  border: 1px solid var(--surface-border);
}

.info-label {
  font-size: 0.75rem;
  color: var(--text-color-secondary);
  text-transform: uppercase;
}

.info-value {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--text-color);
}

.app-dark .info-card {
  background: var(--p-surface-800);
  border-color: var(--p-surface-700);
}
</style>
