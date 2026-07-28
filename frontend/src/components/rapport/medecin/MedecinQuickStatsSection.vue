<script setup>
import { computed, ref } from 'vue';
import AppChart from '@/components/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';

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
        icon: 'pi pi-calendar-check'
    }
];

const chartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
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
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Suivi rapide</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Alertes et rendez-vous du jour</p>
            </div>
            <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
        </div>
        <StatsCardsGrid v-if="!showChart" :items="buildItems(stats)" :loading="loading" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 to-surface-50/70 p-4 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
            <div class="aspect-[16/9] w-full">
                <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </div>
</template>
