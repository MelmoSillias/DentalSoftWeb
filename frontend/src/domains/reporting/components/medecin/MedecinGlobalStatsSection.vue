<script setup>
import { computed, ref } from 'vue';
import AppChart from '@/shared/ui/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import StatsCardsGrid from '@/domains/reporting/components/common/StatsCardsGrid.vue';
import { useReportChartTheme } from '@/domains/reporting/composables/useReportChartTheme.js';

const { chartStyle } = useReportChartTheme();

const props = defineProps({
    data: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const showChart = ref(false);

function formatDateDisplay(dateStr) {
    if (!dateStr) return '--';
    const date = new Date(dateStr);
    if (Number.isNaN(date.getTime())) return '--';
    return date.toLocaleDateString('fr-FR', { year: 'numeric', month: 'long' });
}

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

function formatSalaire(type, valeur) {
    if (type === 'pourcentage') {
        return `Pourcentage (${valeur || 0}%)`;
    }
    return `Fixe (${formatFcfa(valeur)})`;
}

const buildItems = (data) => [
    {
        key: 'patients',
        label: 'Patients distincts reçus',
        value: data.stats?.patientsTotal ?? 0,
        subStats: [
            { label: 'Nouvellement inscrits et reçus', value: data.stats?.newPatientsSeen ?? 0 },
            { label: 'Déjà inscrits et reçus', value: data.stats?.returningPatientsSeen ?? 0 },
            { label: 'Consultations supplémentaires', value: data.stats?.repeatConsultations ?? 0 }
        ],
        icon: 'pi pi-users'
    },
    {
        key: 'employed',
        label: 'Employé depuis',
        value: formatDateDisplay(data.dateEmbauche),
        icon: 'pi pi-calendar'
    },
    {
        key: 'salaryType',
        label: 'Type de salaire',
        value: formatSalaire(data.typeSalaire, data.valeurSalaire),
        icon: 'pi pi-wallet'
    },
    {
        key: 'consultations',
        label: 'Consultations totales',
        value: data.stats?.totalConsultations ?? 0,
        icon: 'pi pi-briefcase'
    }
];

const chartData = computed(() => {
    const documentStyle = chartStyle;
    const data = props.data || {};
    return {
        labels: ['Patients distincts reçus', 'Consultations de la période'],
        datasets: [
            {
                label: 'Indicateurs',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: [data.stats?.patientsTotal || 0, data.stats?.totalConsultations || 0]
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
                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Statistiques globales</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Synthèse des indicateurs du praticien</p>
            </div>
            <ToggleButton size="small" v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
        </div>
        <StatsCardsGrid v-if="!showChart" :items="buildItems(data)" :loading="loading" />
        <div v-else class="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:bg-surface-900 dark:border-surface-700">
            <div class="aspect-[16/9] min-h-[240px] w-full">
                <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </div>
</template>
