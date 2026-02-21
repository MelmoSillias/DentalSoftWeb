<script setup>
import { computed, ref } from 'vue';
import Chart from 'primevue/chart';
import ToggleButton from 'primevue/togglebutton';
import ValueListCard from '@/components/rapport/common/ValueListCard.vue';

const props = defineProps({
    period: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const showConsultationsChart = ref(false);
const showAppointmentsChart = ref(false);
const showRevenueChart = ref(false);

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

const consultationsItems = (period) => [
    { label: 'Gratuites', value: period.freeConsultations ?? 0, severity: 'info' },
    { label: 'Payantes', value: period.paidConsultations ?? 0, severity: 'secondary' },
    { label: 'Total', value: formatFcfa((period.paidConsultations || 0) * 5000), severity: 'success' }
];

const appointmentsItems = (period) => [
    { label: 'Planifiés', value: period.rdvPlanifies ?? 0, severity: 'info' },
    { label: 'En attente', value: period.rdvEnAttente ?? 0, severity: 'secondary' },
    { label: 'Validés', value: period.rdvValides ?? 0, severity: 'success' },
    { label: 'Reportés', value: period.rdvReportes ?? 0, severity: 'warn' },
    { label: 'Annulés', value: period.rdvAnnules ?? 0, severity: 'danger' }
];

const revenueItems = (period) => [
    {
        label: 'Total sur consultations',
        value: formatFcfa((period.paidConsultations || 0) * 5000),
        severity: 'info'
    },
    {
        label: 'Total sur soins effectués',
        value: formatFcfa((period.apportTotal || 0) - (period.paidConsultations || 0) * 5000),
        severity: 'secondary'
    },
    { label: 'Montant total', value: formatFcfa(period.apportTotal || 0), severity: 'success' }
];

const barOptions = computed(() => {
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

const pieOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: documentStyle.getPropertyValue('--text-color') } }
        }
    };
});

const consultationsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const period = props.period || {};
    return {
        labels: ['Gratuites', 'Payantes'],
        datasets: [
            {
                label: 'Consultations',
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-primary-500'),
                    documentStyle.getPropertyValue('--p-emerald-500')
                ],
                data: [period.freeConsultations || 0, period.paidConsultations || 0]
            }
        ]
    };
});

const appointmentsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const period = props.period || {};
    return {
        labels: ['Planifiés', 'En attente', 'Validés', 'Reportés', 'Annulés'],
        datasets: [
            {
                label: 'Rendez-vous',
                backgroundColor: documentStyle.getPropertyValue('--p-orange-500'),
                data: [
                    period.rdvPlanifies || 0,
                    period.rdvEnAttente || 0,
                    period.rdvValides || 0,
                    period.rdvReportes || 0,
                    period.rdvAnnules || 0
                ]
            }
        ]
    };
});

const revenueChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const period = props.period || {};
    const consultTotal = (period.paidConsultations || 0) * 5000;
    const actsTotal = (period.apportTotal || 0) - consultTotal;
    return {
        labels: ['Consultations', 'Soins effectués'],
        datasets: [
            {
                data: [consultTotal, actsTotal],
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-sky-500'),
                    documentStyle.getPropertyValue('--p-indigo-500')
                ]
            }
        ]
    };
});
</script>

<template>
    <section class="space-y-4">
        <div>
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Détails périodiques</h3>
            <p class="text-sm text-surface-500 dark:text-surface-400">Synthèse de la période sélectionnée</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <ValueListCard title="Consultations" :items="consultationsItems(period)" :loading="loading" :show-chart="showConsultationsChart">
                <template #actions>
                    <ToggleButton v-model="showConsultationsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <Chart type="doughnut" :data="consultationsChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
            <ValueListCard title="Rendez-vous" :items="appointmentsItems(period)" :loading="loading" :show-chart="showAppointmentsChart">
                <template #actions>
                    <ToggleButton v-model="showAppointmentsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <Chart type="bar" :data="appointmentsChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
            <ValueListCard title="Apport durant la période" :items="revenueItems(period)" :loading="loading" :show-chart="showRevenueChart">
                <template #actions>
                    <ToggleButton v-model="showRevenueChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <Chart type="doughnut" :data="revenueChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
        </div>
    </section>
</template>
