<script setup>
import { ref, watch } from 'vue';
import CaisseAssurancesDashboard from '@/components/caisse/assurances/CaisseAssurancesDashboard.vue';
import CaisseAssuranceLots from '@/components/caisse/assurances/CaisseAssuranceLots.vue';
import CaisseAssuranceLotPage from '@/components/caisse/assurances/CaisseAssuranceLotPage.vue';
import CaisseAssuranceClaimDetail from '@/components/caisse/assurances/CaisseAssuranceClaimDetail.vue';

const props = defineProps({
    dashboardCards: { type: Array, default: () => [] },
    lotsAssurance: { type: Object, default: null },
    lots: { type: Array, default: () => [] },
    openLots: { type: Array, default: () => [] },
    unassignedClaims: { type: Array, default: () => [] },
    selectedClaim: { type: Object, default: null },
    selectedLot: { type: Object, default: null },
    paymentMethods: { type: Array, default: () => [] },
    dashboardLoading: { type: Boolean, default: false },
    lotsLoading: { type: Boolean, default: false },
    claimLoading: { type: Boolean, default: false },
    lotLoading: { type: Boolean, default: false },
    actionLoadingId: { type: Number, default: null }
});

const emit = defineEmits([
    'refresh-dashboard',
    'refresh-lots',
    'refresh-lot',
    'view-lots',
    'back-to-dashboard',
    'back-to-lots',
    'create-lot',
    'update-lot',
    'view-lot',
    'send-lot',
    'reopen-lot',
    'confirm-lot',
    'unconfirm-lot',
    'refund-lot',
    'cancel-refund',
    'view-claim',
    'back-from-claim',
    'collect-patient-share',
    'modify-claim',
    'assign-claim',
    'change-claim-lot',
    'remove-claim',
    'print-receipt',
    'print-claim'
]);

const currentView = ref('dashboard');

watch(() => props.lotsAssurance, (value) => {
    if (value?.code && currentView.value === 'dashboard') {
        currentView.value = 'lots';
    }
}, { deep: true });

watch(() => props.selectedLot, (value) => {
    if (value?.id) {
        currentView.value = 'lot';
    }
});

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

const handleViewLot = (lot) => {
    emit('view-lot', lot);
    currentView.value = 'lot';
};

const handleBackFromLot = () => {
    currentView.value = 'lots';
    emit('back-to-lots');
};

const handleViewClaim = (claim) => {
    emit('view-claim', claim);
    currentView.value = 'claim';
};

const handleBackFromClaim = () => {
    if (props.selectedLot?.id) {
        currentView.value = 'lot';
    } else if (props.lotsAssurance?.code) {
        currentView.value = 'lots';
    } else {
        currentView.value = 'dashboard';
    }
    emit('back-from-claim');
};
</script>

<template>
  <div>
    <CaisseAssurancesDashboard
      v-if="currentView === 'dashboard'"
      :cards="dashboardCards"
      :loading="dashboardLoading"
      @refresh="emit('refresh-dashboard')"
      @view-lots="handleViewLots"
    />

    <CaisseAssuranceLots
      v-else-if="currentView === 'lots'"
      :assurance="lotsAssurance"
      :lots="lots"
      :open-lots="openLots"
      :unassigned-claims="unassignedClaims"
      :loading="lotsLoading"
      :action-loading-id="actionLoadingId"
      @back="handleBack"
      @refresh="emit('refresh-lots')"
      @create-lot="emit('create-lot', $event)"
      @update-lot="emit('update-lot', $event)"
      @view-lot="handleViewLot"
      @send-lot="emit('send-lot', $event)"
      @reopen-lot="emit('reopen-lot', $event)"
      @confirm-lot="emit('confirm-lot', $event)"
      @unconfirm-lot="emit('unconfirm-lot', $event)"
      @refund-lot="emit('refund-lot', $event)"
      @view-claim="handleViewClaim"
      @pay-claim="emit('collect-patient-share', $event)"
      @modify-claim="emit('modify-claim', $event)"
      @assign-claim="emit('assign-claim', $event)"
      @change-claim-lot="emit('change-claim-lot', $event)"
    />

    <CaisseAssuranceLotPage
      v-else-if="currentView === 'lot'"
      :lot="selectedLot"
      :loading="lotLoading"
      :action-loading="actionLoadingId !== null"
      :payment-methods="paymentMethods"
      @back="handleBackFromLot"
      @refresh="emit('refresh-lot')"
      @send-lot="emit('send-lot', $event)"
      @reopen-lot="emit('reopen-lot', $event)"
      @confirm-lot="emit('confirm-lot', $event)"
      @unconfirm-lot="emit('unconfirm-lot', $event)"
      @refund-lot="emit('refund-lot', $event)"
      @cancel-refund="emit('cancel-refund', $event)"
      @view-claim="handleViewClaim"
      @pay-claim="emit('collect-patient-share', $event)"
      @modify-claim="emit('modify-claim', $event)"
      @remove-claim="emit('remove-claim', $event)"
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
    />
  </div>
</template>
