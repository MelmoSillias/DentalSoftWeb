<script setup>
import { computed, ref, watch } from 'vue';
import Button from 'primevue/button';
import Column from 'primevue/column';
import DataTable from 'primevue/datatable';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import Menu from 'primevue/menu';

const props = defineProps({
    lot: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    actionLoading: { type: Boolean, default: false },
    paymentMethods: { type: Array, default: () => [] }
});

const emit = defineEmits([
    'back',
    'refresh',
    'send-lot',
    'reopen-lot',
    'confirm-lot',
    'unconfirm-lot',
    'refund-lot',
    'cancel-refund',
    'view-claim',
    'pay-claim',
    'modify-claim',
    'remove-claim'
]);

const formatFcfa = (value) => `${Number(value || 0).toLocaleString('fr-FR')} FCFA`;

const lotStatutTag = (statut) => {
    const map = {
        ouvert: { label: 'Ouvert', severity: 'warning' },
        envoye: { label: 'Envoyé', severity: 'info' },
        confirme: { label: 'Confirmé', severity: 'primary' },
        partiellement_rembourse: { label: 'Partiellement remboursé', severity: 'help' },
        rembourse: { label: 'Remboursé', severity: 'success' }
    };
    return map[statut] || { label: statut || '—', severity: 'secondary' };
};

const refundDialogVisible = ref(false);
const refundForm = ref({ modeId: null, amount: 0 });
const claimMenu = ref();
const claimMenuItems = ref([]);

const resteARembourser = computed(() => Number(props.lot?.resteARembourser ?? 0));
const montantSaisi = computed(() => Number(refundForm.value.amount || 0));
const resteApresSaisie = computed(() => Math.max(0, resteARembourser.value - montantSaisi.value));

watch(
    () => props.lot?.id,
    () => {
        refundForm.value = {
            modeId: props.paymentMethods[0]?.id ?? null,
            amount: resteARembourser.value
        };
    },
    { immediate: true }
);

const openRefundDialog = () => {
    refundForm.value = {
        modeId: props.paymentMethods[0]?.id ?? null,
        amount: resteARembourser.value
    };
    refundDialogVisible.value = true;
};

const submitRefund = () => {
    emit('refund-lot', {
        lot: props.lot,
        modeId: refundForm.value.modeId,
        amount: refundForm.value.amount
    });
    refundDialogVisible.value = false;
};

const openClaimMenu = (event, claim) => {
    claimMenuItems.value = [
        {
            label: 'Gestion de la facture',
            items: [
                {
                    label: 'Payer',
                    icon: 'pi pi-wallet',
                    disabled: !claim?.canPay,
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
                    disabled: !claim?.canModify,
                    command: () => emit('modify-claim', claim)
                },
                {
                    label: 'Retirer du lot',
                    icon: 'pi pi-times',
                    disabled: !props.lot?.availableActions?.canRemoveClaims,
                    command: () => emit('remove-claim', claim)
                }
            ]
        }
    ];
    claimMenu.value?.toggle(event);
};
</script>

<template>
  <div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-3">
        <Button icon="pi pi-arrow-left" text rounded @click="emit('back')" />
        <div>
          <h2 class="page-title text-xl font-bold">{{ lot?.description || `Lot #${lot?.id}` }}</h2>
          <p class="muted-text text-sm">
            {{ lot?.assurance?.nom }} · {{ lot?.dateDebut || '—' }} → {{ lot?.dateFin || '—' }}
          </p>
        </div>
        <Tag
          v-if="lot"
          :value="lotStatutTag(lot.statut).label"
          :severity="lotStatutTag(lot.statut).severity"
        />
      </div>
      <div class="flex flex-wrap gap-2">
        <Button icon="pi pi-refresh" outlined rounded @click="emit('refresh')" />
        <Button
          v-if="lot?.availableActions?.canSend"
          icon="pi pi-send"
          label="Envoyer"
          :loading="actionLoading"
          @click="emit('send-lot', lot)"
        />
        <Button
          v-if="lot?.availableActions?.canReopen"
          icon="pi pi-replay"
          label="Rouvrir"
          outlined
          :loading="actionLoading"
          @click="emit('reopen-lot', lot)"
        />
        <Button
          v-if="lot?.availableActions?.canConfirm"
          icon="pi pi-check"
          label="Confirmer"
          :loading="actionLoading"
          @click="emit('confirm-lot', lot)"
        />
        <Button
          v-if="lot?.availableActions?.canUnconfirm"
          icon="pi pi-undo"
          label="Retour envoyé"
          outlined
          :loading="actionLoading"
          @click="emit('unconfirm-lot', lot)"
        />
        <Button
          v-if="lot?.availableActions?.canRefund"
          icon="pi pi-wallet"
          label="Rembourser"
          severity="success"
          :loading="actionLoading"
          @click="openRefundDialog"
        />
      </div>
    </div>

    <div v-if="loading" class="py-10 text-center muted-text">
      <i class="pi pi-spin pi-spinner text-2xl"></i>
    </div>

    <template v-else-if="lot">
      <div class="info-grid">
        <div class="info-card">
          <span class="muted-text text-xs uppercase">Factures</span>
          <strong>{{ lot.nbFactures || 0 }}</strong>
        </div>
        <div class="info-card">
          <span class="muted-text text-xs uppercase">Montant assurance</span>
          <strong>{{ formatFcfa(lot.montantTotal) }}</strong>
        </div>
        <div class="info-card">
          <span class="muted-text text-xs uppercase">Déjà remboursé</span>
          <strong>{{ formatFcfa(lot.montantRembourse) }}</strong>
        </div>
        <div class="info-card">
          <span class="muted-text text-xs uppercase">Reste à rembourser</span>
          <strong>{{ formatFcfa(lot.resteARembourser) }}</strong>
        </div>
      </div>

      <div class="section-panel">
        <h3 class="page-title text-lg font-bold mb-3">Factures du lot</h3>
        <DataTable :value="lot.factures || []" striped-rows size="small" paginator :rows="10">
          <Column header="Date">
            <template #body="{ data }">{{ data?.dateFacture?.slice(0, 10) || '—' }}</template>
          </Column>
          <Column field="patient" header="Patient" />
          <Column header="Total">
            <template #body="{ data }">{{ formatFcfa(data.montantTotal) }}</template>
          </Column>
          <Column header="Taux">
            <template #body="{ data }">{{ Number(data.tauxCouverture || 0) }} %</template>
          </Column>
          <Column header="Reste patient">
            <template #body="{ data }">
              <span class="font-semibold">{{ formatFcfa(data.restePatient) }}</span>
            </template>
          </Column>
          <Column header="Actions" style="min-width: 5rem">
            <template #body="{ data }">
              <Button icon="pi pi-ellipsis-v" text rounded @click="openClaimMenu($event, data)" />
            </template>
          </Column>
        </DataTable>
        <Menu ref="claimMenu" :model="claimMenuItems" popup />
      </div>

      <div class="section-panel">
        <h3 class="page-title text-lg font-bold mb-3">Remboursements</h3>
        <DataTable :value="lot.remboursements || []" striped-rows size="small" empty-message="Aucun remboursement">
          <Column header="Date">
            <template #body="{ data }">{{ data?.dateTransaction?.slice(0, 16) || '—' }}</template>
          </Column>
          <Column header="Montant">
            <template #body="{ data }">{{ formatFcfa(data.montant) }}</template>
          </Column>
          <Column header="Mode">
            <template #body="{ data }">{{ data?.modeDePaiement?.libelle || '—' }}</template>
          </Column>
          <Column field="description" header="Description" />
          <Column header="Actions">
            <template #body="{ data }">
              <Button
                v-if="data.canCancel"
                icon="pi pi-times"
                label="Annuler"
                size="small"
                severity="danger"
                outlined
                :disabled="actionLoading"
                @click="emit('cancel-refund', { lot, transaction: data })"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </template>

    <Dialog v-model:visible="refundDialogVisible" modal header="Remboursement du lot" class="w-full max-w-md">
      <div class="flex flex-col gap-4 py-2">
        <div>
          <label class="block text-sm mb-1">Mode de paiement</label>
          <Select
            v-model="refundForm.modeId"
            :options="paymentMethods"
            option-label="libelle"
            option-value="id"
            class="w-full"
            placeholder="Choisir un mode"
          />
        </div>
        <div>
          <label class="block text-sm mb-1">Montant</label>
          <InputNumber v-model="refundForm.amount" class="w-full" :min="0" :max="resteARembourser" />
        </div>
        <div class="reste-box">
          <span>Reste à rembourser après validation</span>
          <strong>{{ formatFcfa(resteApresSaisie) }}</strong>
        </div>
      </div>
      <template #footer>
        <Button label="Annuler" text @click="refundDialogVisible = false" />
        <Button
          label="Valider"
          icon="pi pi-check"
          :disabled="!refundForm.modeId || montantSaisi <= 0 || montantSaisi > resteARembourser"
          @click="submitRefund"
        />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.page-title {
  color: var(--text-color);
}

.muted-text {
  color: var(--text-color-secondary);
}

.section-panel {
  padding: 1.25rem;
  border-radius: 1rem;
  border: 1px solid var(--surface-border);
  background: var(--surface-card);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
  gap: 0.75rem;
}

.info-card {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1rem;
  border-radius: 0.85rem;
  border: 1px solid var(--surface-border);
  background: var(--surface-card);
}

.reste-box {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  background: var(--p-surface-50);
  border: 1px solid var(--surface-border);
}
</style>
