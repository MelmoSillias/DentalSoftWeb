<script setup>
import { computed, ref } from 'vue';
import AppChart from '@/components/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import ValueListCard from '@/components/rapport/common/ValueListCard.vue';

const props = defineProps({
    period: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const showConsultationsChart = ref(false);
const showAppointmentsChart = ref(false);
const showRevenueChart = ref(false);
const showReliquatsChart = ref(false);

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

const consultationsItems = (period) => [
    { label: 'Gratuites', value: period.freeConsultations ?? 0, severity: 'info' },
    { label: 'Payantes', value: period.paidConsultations ?? 0, severity: 'secondary' },
    { label: 'Total', value: period.paidConsultations ?? 0, severity: 'success' }
];

const appointmentsItems = (period) => [
    { label: 'Planifiés', value: period.rdvPlanifies ?? 0, severity: 'info' },
    { label: 'En attente', value: period.rdvEnAttente ?? 0, severity: 'secondary' },
    { label: 'Validés', value: period.rdvValides ?? 0, severity: 'success' },
    { label: 'Reportés', value: period.rdvReportes ?? 0, severity: 'warn' },
    { label: 'Annulés', value: period.rdvAnnules ?? 0, severity: 'danger' }
];

const apportItems = (period) => [
    { label: 'Montant total (apport)', value: formatFcfa(period.apportTotal || 0), severity: 'success' }
];

const revenueItems = (period) => [
    {
        label: 'Reliquats encaissés',
        value: formatFcfa(period.revenueReliquats || 0),
        severity: 'warn'
    },
    {
        label: 'Total encaissé',
        value: formatFcfa(period.revenueTotal || 0),
        severity: 'success'
    },
    {
        label: 'Réliquat patient',
        value: formatFcfa(period.reliquat || 0),
        severity: 'danger'
    }
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

const apportChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const period = props.period || {};
    return {
        labels: ['Apport total'],
        datasets: [
            {
                data: [period.apportTotal || 0],
                backgroundColor: [documentStyle.getPropertyValue('--p-sky-500')]
            }
        ]
    };
});

const revenueChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const period = props.period || {};
    return {
        labels: ['Total encaissé', 'Réliquat patient'],
        datasets: [
            {
                data: [period.revenueTotal || 0, period.reliquat || 0],
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-emerald-500'),
                    documentStyle.getPropertyValue('--p-amber-500')
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

        <div class="grid gap-4 lg:grid-cols-2 xl:grid-cols-4">
            <ValueListCard title="Consultations" :items="consultationsItems(period)" :loading="loading" :show-chart="showConsultationsChart">
                <template #actions>
                    <ToggleButton v-model="showConsultationsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <AppChart type="doughnut" :data="consultationsChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
            <ValueListCard title="Rendez-vous" :items="appointmentsItems(period)" :loading="loading" :show-chart="showAppointmentsChart">
                <template #actions>
                    <ToggleButton v-model="showAppointmentsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <AppChart type="bar" :data="appointmentsChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
            <ValueListCard title="Apport durant la période" :items="apportItems(period)" :loading="loading" :show-chart="showRevenueChart">
                <template #actions>
                    <ToggleButton v-model="showRevenueChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <AppChart type="doughnut" :data="apportChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
            <ValueListCard title="Encaissements et rémunération" :items="revenueItems(period)" :loading="loading" :show-chart="showReliquatsChart">
                <template #actions>
                    <ToggleButton v-model="showReliquatsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <AppChart type="doughnut" :data="revenueChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
        </div>
    </section>
</template>
