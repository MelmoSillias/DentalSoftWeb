<script setup>
import { computed, watch } from 'vue';
import AppDialog from '@/components/layout/AppDialog.vue';
import FinanceCrossTablePeriodDetails from '@/components/finances/FinanceCrossTablePeriodDetails.vue';
import { useFinances } from '@/composables/useFinances';

const props = defineProps({
    visible: { type: Boolean, default: false },
    date: { type: String, default: '' }
});

const emit = defineEmits(['update:visible', 'transaction-updated']);

const { crossTableDayOverview, loading, fetchCrossTableDayOverview } = useFinances();

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value)
});

const overview = computed(() => crossTableDayOverview.value || {});
const periodLabel = computed(() => overview.value.dateLabel || overview.value.date || '');

const loadOverview = async () => {
    if (!props.date) {
        return;
    }
    await fetchCrossTableDayOverview(props.date);
};

const handleTransactionUpdated = async () => {
    await loadOverview();
    emit('transaction-updated');
};

watch(
    () => [props.visible, props.date],
    ([visible, date]) => {
        if (visible && date) {
            loadOverview();
        }
    }
);
</script>

<template>
    <AppDialog
        v-model:visible="dialogVisible"
        :title="`Détail du ${periodLabel || 'jour'}`"
        icon="pi pi-calendar"
        icon-tone="info"
        size="full"
        :draggable="false"
        :show-footer="false"
    >
        <FinanceCrossTablePeriodDetails :overview="overview" :loading="loading.dayOverview" :period-label="periodLabel" scope-label="journée" @transaction-updated="handleTransactionUpdated" />
    </AppDialog>
</template>
