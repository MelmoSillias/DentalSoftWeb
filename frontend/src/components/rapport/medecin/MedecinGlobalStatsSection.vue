<script setup>
import { computed, ref } from 'vue';
import Chart from 'primevue/chart';
import ToggleButton from 'primevue/togglebutton';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';

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
        label: 'Patients consultés',
        value: data.stats?.patientsTotal ?? 0,
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
    const documentStyle = getComputedStyle(document.documentElement);
    const data = props.data || {};
    return {
        labels: ['Patients consultés', 'Consultations totales', 'Valeur salaire'],
        datasets: [
            {
                label: 'Indicateurs',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: [data.stats?.patientsTotal || 0, data.stats?.totalConsultations || 0, data.valeurSalaire || 0]
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
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Statistiques globales</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Synthèse des indicateurs du praticien</p>
            </div>
            <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
        </div>
        <StatsCardsGrid v-if="!showChart" :items="buildItems(data)" :loading="loading" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 to-surface-50/70 p-4 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
            <div class="aspect-[16/9] w-full">
                <Chart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </div>
</template>
