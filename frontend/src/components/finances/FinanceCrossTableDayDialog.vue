<script setup>
import { computed, watch } from 'vue';
import Dialog from 'primevue/dialog';
import FinanceCrossTablePeriodDetails from '@/components/finances/FinanceCrossTablePeriodDetails.vue';
import { useFinances } from '@/composables/useFinances';

const props = defineProps({
    visible: { type: Boolean, default: false },
    date: { type: String, default: '' }
});

const emit = defineEmits(['update:visible']);

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
    <Dialog
        v-model:visible="dialogVisible"
        modal
        :header="`Détail du ${periodLabel || 'jour'}`"
        :style="{ width: 'min(96vw, 1100px)' }"
        :breakpoints="{ '960px': '96vw' }"
        :draggable="false"
    >
        <FinanceCrossTablePeriodDetails
            :overview="overview"
            :loading="loading.dayOverview"
            :period-label="periodLabel"
            scope-label="journée"
        />
    </Dialog>
</template>
