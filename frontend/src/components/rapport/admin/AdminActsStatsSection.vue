<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import ToggleButton from 'primevue/togglebutton';
import ValueListCard from '@/components/rapport/common/ValueListCard.vue';

const props = defineProps({
    actsStats: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['print']);
const showChart = ref(false);

const chartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: props.actsStats.map((row) => row.label),
        datasets: [
            {
                label: 'Actes',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: props.actsStats.map((row) => row.value || 0)
            }
        ]
    };
});

const chartOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } }
        },
        scales: {
            x: { ticks: { color: textColorSecondary }, grid: { display: false } },
            y: { ticks: { color: textColorSecondary }, grid: { color: surfaceBorder } }
        }
    };
});
</script>

<template>
    <section class="space-y-4" id="admin-acts-stats">
        <ValueListCard
            title="Statistiques des soins médicaux"
            :items="actsStats"
            :loading="loading"
            :show-chart="showChart"
            empty-label="Aucun soin enregistré."
        >
            <template #actions>
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-acts-stats')" />
            </template>
            <template #chart>
                <div class="aspect-[16/9] w-full">
                    <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
                </div>
            </template>
        </ValueListCard>
    </section>
</template>
