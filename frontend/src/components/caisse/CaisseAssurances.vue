<script setup>
import { ref, watch } from 'vue';
import CaisseAssurancesDashboard from '@/components/caisse/assurances/CaisseAssurancesDashboard.vue';
import CaisseAssuranceLots from '@/components/caisse/assurances/CaisseAssuranceLots.vue';
import CaisseAssuranceLotDialog from '@/components/caisse/assurances/CaisseAssuranceLotDialog.vue';
import CaisseAssuranceClaimDetail from '@/components/caisse/assurances/CaisseAssuranceClaimDetail.vue';

const props = defineProps({
    dashboardCards: { type: Array, default: () => [] },
    unpaidClaims: { type: Array, default: () => [] },
    lotsAssurance: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
    unassignedClaims: { type: Array, default: () => [] },
    openLot: { type: Object, default: null },
    selectedClaim: { type: Object, default: null },
    selectedLot: { type: Object, default: null },
    dashboardLoading: { type: Boolean, default: false },
    lotsLoading: { type: Boolean, default: false },
    claimLoading: { type: Boolean, default: false },
    lotDialogLoading: { type: Boolean, default: false },
    actionLoadingId: { type: Number, default: null },
    lotDialogVisible: { type: Boolean, default: false }
});

const emit = defineEmits([
    'refresh-dashboard',
    'refresh-lots',
    'load-lot-detail',
    'view-lots',
    'back-to-dashboard',
    'open-lot',
    'view-lot-dialog',
    'close-lot-dialog',
    'view-claim',
    'back-from-claim',
    'send-lot',
    'recover-lot',
    'cancel-recovery',
    'validate-claim',
    'reject-claim',
    'collect-patient-share',
    'print-receipt',
    'print-claim',
    'add-claim-to-lot'
]);

const currentView = ref('dashboard');

watch(() => props.lotsAssurance, (value) => {
    if (value?.code) {
        currentView.value = 'lots';
    }
}, { deep: true });

watch(() => props.selectedClaim, (value) => {
    if (value?.id) {
        currentView.value = 'claim';
    }
});

const handleViewLots = (card) => {
    emit('view-lots', card);
    currentView.value = 'lots';
};

const handleBack = () => {
    currentView.value = 'dashboard';
    emit('back-to-dashboard');
};

const handleViewClaim = (claim) => {
    emit('view-claim', claim);
    currentView.value = 'claim';
};

const handleBackFromClaim = () => {
    currentView.value = props.lotsAssurance?.code ? 'lots' : 'dashboard';
    emit('back-from-claim');
};
</script>

<template>
  <div>
    <CaisseAssurancesDashboard
      v-if="currentView === 'dashboard'"
      :cards="dashboardCards"
      :unpaid-claims="unpaidClaims"
      :loading="dashboardLoading"
      :action-loading-id="actionLoadingId"
      @refresh="emit('refresh-dashboard')"
      @view-lots="handleViewLots"
      @view-lot-dialog="emit('view-lot-dialog', $event)"
      @open-lot="emit('open-lot', $event)"
      @view-claim="handleViewClaim"
      @collect-patient-share="emit('collect-patient-share', $event)"
      @validate-claim="emit('validate-claim', $event)"
      @reject-claim="emit('reject-claim', $event)"
    />

    <CaisseAssuranceLots
      v-else-if="currentView === 'lots'"
      :assurance="lotsAssurance"
      :lots="lots"
      :unassigned-claims="unassignedClaims"
      :open-lot="openLot"
      :loading="lotsLoading"
      :action-loading-id="actionLoadingId"
      @back="handleBack"
      @refresh="emit('refresh-lots')"
      @open-lot="emit('open-lot', lotsAssurance)"
      @view-lot="emit('load-lot-detail', $event)"
      @send-lot="emit('send-lot', $event)"
      @recover-lot="emit('recover-lot', $event)"
      @cancel-recovery="emit('cancel-recovery', $event)"
      @view-claim="handleViewClaim"
      @add-claim-to-lot="emit('add-claim-to-lot', $event)"
    />

    <CaisseAssuranceClaimDetail
      v-else-if="currentView === 'claim'"
      :claim="selectedClaim"
      :loading="claimLoading"
      :action-loading="actionLoadingId === Number(selectedClaim?.id)"
      @back="handleBackFromClaim"
      @collect-patient-share="emit('collect-patient-share', $event)"
      @print-receipt="emit('print-receipt', $event)"
      @print-claim="emit('print-claim', $event)"
      @validate-claim="emit('validate-claim', $event)"
      @reject-claim="emit('reject-claim', $event)"
    />

    <CaisseAssuranceLotDialog
      :visible="lotDialogVisible"
      :lot="selectedLot"
      :loading="lotDialogLoading"
      :action-loading="actionLoadingId !== null"
      @update:visible="emit('close-lot-dialog', $event)"
      @send-lot="emit('send-lot', $event)"
      @recover-lot="emit('recover-lot', $event)"
      @cancel-recovery="emit('cancel-recovery', $event)"
      @view-claim="handleViewClaim"
    />
  </div>
</template>
