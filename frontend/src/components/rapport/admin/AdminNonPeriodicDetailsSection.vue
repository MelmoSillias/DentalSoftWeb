<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import AppChart from '@/components/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import ValueListCard from '@/components/rapport/common/ValueListCard.vue';
import { formatAsOfLabel, printReport } from '@/utils/reportPrint';

const props = defineProps({
    employeeDistribution: { type: Array, default: () => [] },
    lowStock: { type: Array, default: () => [] },
    patients: { type: Object, default: () => ({}) },
    patientReferrals: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});
const showEmployeesChart = ref(false);
const showLowStockChart = ref(false);
const showPatientsChart = ref(false);
const showPatientReferralsChart = ref(false);

const patientItems = (patients) => [
    { label: 'Total', value: patients.total ?? 0, severity: 'info' },
    { label: 'Femmes / Hommes', value: `${patients.female ?? 0} / ${patients.male ?? 0}`, severity: 'success' },
    {
        label: 'Mineurs / Adultes / Séniors',
        value: `${patients.minors ?? 0} / ${patients.adults ?? 0} / ${patients.seniors ?? 0}`,
        severity: 'secondary'
    },
    {
        label: 'Âge moyen',
        value: `${Math.round(patients.averageAge || 0)} ans`,
        severity: 'contrast'
    }
];

const employeeChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: props.employeeDistribution.map((row) => row.label),
        datasets: [
            {
                data: props.employeeDistribution.map((row) => row.value || 0),
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-primary-500'),
                    documentStyle.getPropertyValue('--p-emerald-500'),
                    documentStyle.getPropertyValue('--p-amber-500'),
                    documentStyle.getPropertyValue('--p-indigo-500'),
                    documentStyle.getPropertyValue('--p-teal-500')
                ]
            }
        ]
    };
});

const lowStockChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const values = props.lowStock.map((row) => Number(String(row.value).match(/\d+/)?.[0] || 0));
    return {
        labels: props.lowStock.map((row) => row.label),
        datasets: [
            {
                label: 'Restants',
                backgroundColor: documentStyle.getPropertyValue('--p-orange-500'),
                data: values
            }
        ]
    };
});

const patientsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const patients = props.patients || {};
    return {
        labels: ['Femmes', 'Hommes', 'Mineurs', 'Adultes', 'Séniors'],
        datasets: [
            {
                data: [patients.female || 0, patients.male || 0, patients.minors || 0, patients.adults || 0, patients.seniors || 0],
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-pink-500'),
                    documentStyle.getPropertyValue('--p-blue-500'),
                    documentStyle.getPropertyValue('--p-amber-500'),
                    documentStyle.getPropertyValue('--p-emerald-500'),
                    documentStyle.getPropertyValue('--p-purple-500')
                ]
            }
        ]
    };
});

const patientReferralsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: props.patientReferrals.map((row) => row.label),
        datasets: [
            {
                label: 'Patients',
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-cyan-500'),
                    documentStyle.getPropertyValue('--p-blue-500'),
                    documentStyle.getPropertyValue('--p-emerald-500'),
                    documentStyle.getPropertyValue('--p-orange-500'),
                    documentStyle.getPropertyValue('--p-pink-500'),
                    documentStyle.getPropertyValue('--p-purple-500')
                ],
                data: props.patientReferrals.map((row) => row.value || 0)
            }
        ]
    };
});

const pieOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: documentStyle.getPropertyValue('--text-color')
                }
            }
        }
    };
});

const barOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: documentStyle.getPropertyValue('--text-color') }
            }
        },
        scales: {
            x: {
                ticks: { color: textColorSecondary },
                grid: { display: false }
            },
            y: {
                ticks: { color: textColorSecondary },
                grid: { color: surfaceBorder }
            }
        }
    };
});

function printSection() {
    printReport({
        title: 'Détails globaux',
        periodLabel: formatAsOfLabel(),
        sections: [
            {
                title: 'Répartition des employés',
                items: props.employeeDistribution,
                emptyLabel: 'Aucun employé à afficher.'
            },
            {
                title: 'Consommables à stock bas',
                items: props.lowStock,
                emptyLabel: 'Aucun consommable critique.'
            },
            {
                title: 'Patients',
                items: patientItems(props.patients),
                emptyLabel: 'Aucune donnée patient.'
            },
            {
                title: 'Comment les patients ont connu le cabinet',
                items: props.patientReferrals,
                emptyLabel: 'Aucune provenance disponible.'
            }
        ]
    });
}
</script>

<template>
    <section class="space-y-4" id="admin-non-periodic">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Détails globaux</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Indicateurs non liés à la période sélectionnée</p>
            </div>
            <Button label="Imprimer la section" icon="pi pi-print" outlined size="small" @click="printSection" />
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <ValueListCard
                id="admin-employee-distribution"
                title="Répartition des employés"
                :items="employeeDistribution"
                :loading="loading"
                :show-chart="showEmployeesChart"
                empty-label="Aucun employé à afficher."
            >
                <template #actions>
                    <ToggleButton v-model="showEmployeesChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <AppChart type="pie" :data="employeeChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>

            <ValueListCard
                id="admin-low-stock"
                title="Consommables à stock bas"
                :items="lowStock"
                :loading="loading"
                :show-chart="showLowStockChart"
                empty-label="Aucun consommable critique."
            >
                <template #actions>
                    <ToggleButton v-model="showLowStockChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <AppChart type="bar" :data="lowStockChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
        <ValueListCard
            id="admin-global-patients"
            title="Patients"
            :items="patientItems(patients)"
            :loading="loading"
            :show-chart="showPatientsChart"
            empty-label="Aucune donnée patient."
        >
            <template #actions>
                <ToggleButton v-model="showPatientsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
            </template>
            <template #chart>
                <div class="aspect-square w-full">
                    <AppChart type="doughnut" :data="patientsChartData" :options="pieOptions" class="h-full w-full" />
                </div>
            </template>
        </ValueListCard>

        <ValueListCard
            id="admin-patient-referrals"
            title="Comment les patients ont connu le cabinet"
            :items="patientReferrals"
            :loading="loading"
            :show-chart="showPatientReferralsChart"
            empty-label="Aucune provenance disponible."
        >
            <template #actions>
                <ToggleButton v-model="showPatientReferralsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
            </template>
            <template #chart>
                <div class="aspect-[16/9] w-full">
                    <AppChart type="bar" :data="patientReferralsChartData" :options="barOptions" class="h-full w-full" />
                </div>
                </template>
            </ValueListCard>
        </div>
    </section>
</template>
