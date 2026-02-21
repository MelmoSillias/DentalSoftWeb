<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Chart from 'primevue/chart';
import ToggleButton from 'primevue/togglebutton';
import ValueListCard from '@/components/rapport/common/ValueListCard.vue';

const props = defineProps({
    patients: { type: Object, default: () => ({}) },
    consultations: { type: Object, default: () => ({}) },
    appointments: { type: Object, default: () => ({}) },
    roomUsage: { type: Object, default: () => ({ usage: [], topRoom: '' }) },
    paymentBalances: { type: Array, default: () => [] },
    paymentFrequency: { type: Object, default: () => ({ frequency: [], topMode: '' }) },
    loading: { type: Boolean, default: false }
});

const emit = defineEmits(['print']);
const showPatientsChart = ref(false);
const showConsultationsChart = ref(false);
const showAppointmentsChart = ref(false);
const showRoomUsageChart = ref(false);
const showPaymentBalancesChart = ref(false);
const showPaymentFrequencyChart = ref(false);

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

const patientsItems = (patients) => [
    { label: 'Nouveaux', value: patients.newPatients ?? 0, severity: 'info' },
    { label: 'Retours', value: patients.returningPatients ?? 0, severity: 'success' }
];

const consultationsItems = (consultations) => [
    { label: 'Total', value: consultations.total ?? 0, severity: 'info' },
    { label: 'Payantes / Gratuites', value: `${consultations.paid ?? 0} / ${consultations.free ?? 0}`, severity: 'warn' },
    { label: 'Montant total', value: formatFcfa(consultations.totalAmount), severity: 'success' },
    { label: 'Montant moyen', value: formatFcfa(consultations.averageAmount), severity: 'secondary' },
    {
        label: 'Soins fréquents',
        value: (consultations.topActs || []).length ? consultations.topActs.join(', ') : '—',
        severity: 'contrast'
    }
];

const appointmentsItems = (appointments) => [
    { label: 'Planifiés', value: appointments.scheduled ?? 0, severity: 'info' },
    { label: 'Validés / En attente', value: `${appointments.confirmed ?? 0} / ${appointments.pending ?? 0}`, severity: 'success' },
    { label: 'Reportés / Annulés', value: `${appointments.postponed ?? 0} / ${appointments.cancelled ?? 0}`, severity: 'warn' },
    { label: 'Taux de confirmation', value: `${appointments.confirmationRate ?? 0} %`, severity: 'secondary' },
    { label: 'Délai moyen', value: `${appointments.averageDelayDays ?? 0} jours`, severity: 'contrast' }
];

const roomUsageItems = (roomUsage) =>
    (roomUsage.usage || []).map((row) => ({
        label: row.room,
        value: `${row.count} (${row.percent}%)`,
        severity: 'info'
    }));

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

const patientsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: ['Nouveaux', 'Retours'],
        datasets: [
            {
                data: [props.patients.newPatients || 0, props.patients.returningPatients || 0],
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-primary-500'),
                    documentStyle.getPropertyValue('--p-emerald-500')
                ]
            }
        ]
    };
});

const consultationsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: ['Total', 'Payantes', 'Gratuites'],
        datasets: [
            {
                label: 'Consultations',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: [props.consultations.total || 0, props.consultations.paid || 0, props.consultations.free || 0]
            }
        ]
    };
});

const appointmentsChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: ['Planifiés', 'Validés', 'En attente', 'Reportés', 'Annulés'],
        datasets: [
            {
                label: 'Rendez-vous',
                backgroundColor: documentStyle.getPropertyValue('--p-orange-500'),
                data: [
                    props.appointments.scheduled || 0,
                    props.appointments.confirmed || 0,
                    props.appointments.pending || 0,
                    props.appointments.postponed || 0,
                    props.appointments.cancelled || 0
                ]
            }
        ]
    };
});

const roomUsageChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: (props.roomUsage.usage || []).map((row) => row.room),
        datasets: [
            {
                label: 'Utilisation',
                backgroundColor: documentStyle.getPropertyValue('--p-teal-500'),
                data: (props.roomUsage.usage || []).map((row) => row.count || 0)
            }
        ]
    };
});

const paymentBalancesChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: props.paymentBalances.map((row) => row.label),
        datasets: [
            {
                label: 'Solde',
                backgroundColor: documentStyle.getPropertyValue('--p-emerald-500'),
                data: props.paymentBalances.map((row) => Number(String(row.value).replace(/[^\d.-]/g, '')) || 0)
            }
        ]
    };
});

const paymentFrequencyChartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: (props.paymentFrequency.frequency || []).map((row) => row.label),
        datasets: [
            {
                label: 'Utilisation',
                backgroundColor: documentStyle.getPropertyValue('--p-purple-500'),
                data: (props.paymentFrequency.frequency || []).map((row) => Number(String(row.value).match(/\d+/)?.[0] || 0))
            }
        ]
    };
});
</script>

<template>
    <section class="space-y-4" id="admin-periodic">
        <div>
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Détails périodiques</h3>
            <p class="text-sm text-surface-500 dark:text-surface-400">Données filtrées par la période sélectionnée</p>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <ValueListCard
                id="admin-periodic-patients"
                title="Patients"
                :items="patientsItems(patients)"
                :loading="loading"
                :show-chart="showPatientsChart"
            >
                <template #actions>
                    <ToggleButton v-model="showPatientsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-pie" offIcon="pi pi-list" />
                    <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-periodic-patients')" />
                </template>
                <template #chart>
                    <div class="aspect-square w-full">
                        <Chart type="doughnut" :data="patientsChartData" :options="pieOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>

            <ValueListCard
                id="admin-periodic-consults"
                title="Consultations"
                :items="consultationsItems(consultations)"
                :loading="loading"
                :show-chart="showConsultationsChart"
            >
                <template #actions>
                    <ToggleButton v-model="showConsultationsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                    <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-periodic-consults')" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <Chart type="bar" :data="consultationsChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>

            <ValueListCard
                id="admin-periodic-appointments"
                title="Rendez-vous"
                :items="appointmentsItems(appointments)"
                :loading="loading"
                :show-chart="showAppointmentsChart"
            >
                <template #actions>
                    <ToggleButton v-model="showAppointmentsChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                    <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-periodic-appointments')" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <Chart type="bar" :data="appointmentsChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>

            <ValueListCard
                id="admin-room-usage"
                title="Utilisation des salles"
                :items="roomUsageItems(roomUsage)"
                :loading="loading"
                :show-chart="showRoomUsageChart"
                empty-label="Aucune salle utilisée."
            >
                <template #actions>
                    <ToggleButton v-model="showRoomUsageChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                    <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-room-usage')" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <Chart type="bar" :data="roomUsageChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
                <template #footer>
                    <p v-if="roomUsage.topRoom" class="mt-3 text-xs text-surface-500">
                        Salle la plus utilisée : <strong>{{ roomUsage.topRoom }}</strong>
                    </p>
                </template>
            </ValueListCard>

            <ValueListCard
                id="admin-payment-balances"
                title="Solde des comptes de paiement"
                :items="paymentBalances"
                :loading="loading"
                :show-chart="showPaymentBalancesChart"
                empty-label="Aucune donnée de solde."
            >
                <template #actions>
                    <ToggleButton v-model="showPaymentBalancesChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                    <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-payment-balances')" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <Chart type="bar" :data="paymentBalancesChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
            </ValueListCard>

            <ValueListCard
                id="admin-payment-frequency"
                title="Utilisation des modes de paiement"
                :items="paymentFrequency.frequency || []"
                :loading="loading"
                :show-chart="showPaymentFrequencyChart"
                empty-label="Aucune donnée de fréquence."
            >
                <template #actions>
                    <ToggleButton v-model="showPaymentFrequencyChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                    <Button icon="pi pi-print" text rounded @click="emit('print', 'admin-payment-frequency')" />
                </template>
                <template #chart>
                    <div class="aspect-[16/9] w-full">
                        <Chart type="bar" :data="paymentFrequencyChartData" :options="barOptions" class="h-full w-full" />
                    </div>
                </template>
                <template #footer>
                    <p v-if="paymentFrequency.topMode" class="mt-3 text-xs text-surface-500">
                        Mode le plus utilisé : <strong>{{ paymentFrequency.topMode }}</strong>
                    </p>
                </template>
            </ValueListCard>
        </div>
    </section>
</template>
