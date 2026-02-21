<script setup>
import { computed, ref } from 'vue';
import Chart from 'primevue/chart';
import ToggleButton from 'primevue/togglebutton';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const showChart = ref(false);

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

const buildItems = (stats) => [
    { key: 'new', label: 'Nouveaux patients', value: stats.newPatients ?? 0, icon: 'pi pi-user-plus' },
    { key: 'consults', label: 'Consultations', value: stats.totalConsultations ?? 0, icon: 'pi pi-briefcase' },
    { key: 'pending', label: 'Consultations en attente', value: stats.pendingConsultations ?? 0, icon: 'pi pi-clock' },
    { key: 'appointments', label: 'Rendez-vous planifiés', value: stats.totalAppointments ?? 0, icon: 'pi pi-calendar' },
    { key: 'absent', label: 'Absences patients', value: stats.absentAppointments ?? 0, icon: 'pi pi-user-minus' },
    { key: 'paid', label: 'Factures payées', value: stats.paidInvoices ?? 0, icon: 'pi pi-receipt' },
    { key: 'cash', label: 'Revenus encaissés', value: formatFcfa(stats.cashRevenue), icon: 'pi pi-money-bill' },
    { key: 'total', label: 'Total revenus', value: formatFcfa(stats.totalRevenue), icon: 'pi pi-chart-bar' }
];

const chartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: [
            'Nouveaux patients',
            'Consultations',
            'Consultations en attente',
            'Rendez-vous planifiés',
            'Absences',
            'Factures payées',
            'Revenus encaissés',
            'Total revenus'
        ],
        datasets: [
            {
                label: 'Statistiques',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: [
                    props.stats.newPatients || 0,
                    props.stats.totalConsultations || 0,
                    props.stats.pendingConsultations || 0,
                    props.stats.totalAppointments || 0,
                    props.stats.absentAppointments || 0,
                    props.stats.paidInvoices || 0,
                    props.stats.cashRevenue || 0,
                    props.stats.totalRevenue || 0
                ]
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
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Statistiques du jour</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Vue instantanée des indicateurs de la réception</p>
            </div>
            <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
        </div>
        <StatsCardsGrid v-if="!showChart" :items="buildItems(stats)" :loading="loading" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 to-surface-50/70 p-4 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
            <div class="aspect-[16/9] w-full">
                <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </div>
</template>
