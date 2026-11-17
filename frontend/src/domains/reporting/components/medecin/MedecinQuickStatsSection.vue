<script setup>
import { computed, ref } from 'vue';
import AppChart from '@/shared/ui/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import StatsCardsGrid from '@/domains/reporting/components/common/StatsCardsGrid.vue';
import { useReportChartTheme } from '@/domains/reporting/composables/useReportChartTheme.js';

const { chartStyle } = useReportChartTheme();

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const showChart = ref(false);

const buildItems = (stats) => [
    {
        key: 'pending',
        label: 'Consultations en attente',
        value: stats.consultationsEnAttente ?? 0,
        icon: 'pi pi-clock'
    },
    {
        key: 'today',
        label: 'Rendez-vous du jour',
        value: stats.rdvJour ?? 0,
        icon: 'pi pi-calendar'
    }
];

const chartData = computed(() => {
    const documentStyle = chartStyle;
    return {
        labels: ['Consultations en attente', 'Rendez-vous du jour'],
        datasets: [
            {
                label: 'Suivi rapide',
                backgroundColor: documentStyle.getPropertyValue('--p-orange-500'),
                data: [props.stats.consultationsEnAttente || 0, props.stats.rdvJour || 0]
            }
        ]
    };
});

const chartOptions = computed(() => {
    const documentStyle = chartStyle;
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
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Suivi rapide</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Alertes et rendez-vous du jour</p>
            </div>
            <ToggleButton size="small" v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
        </div>
        <StatsCardsGrid v-if="!showChart" :items="buildItems(stats)" :loading="loading" />
        <div v-else class="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:bg-surface-900 dark:border-surface-700">
            <div class="aspect-[16/9] min-h-[240px] w-full">
                <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </div>
</template>
