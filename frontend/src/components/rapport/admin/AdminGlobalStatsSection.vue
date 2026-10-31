<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import AppChart from '@/components/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import StatsCardsGrid from '@/components/rapport/common/StatsCardsGrid.vue';
import { formatAsOfLabel, printReport } from '@/utils/reportPrint';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});
const showChart = ref(false);

function formatFcfa(amount) {
    const value = Number(amount || 0);
    return `${new Intl.NumberFormat('fr-FR').format(value)} Fcfa`;
}

const items = (stats) => [
    {
        key: 'patients',
        label: 'Nouveaux patients',
        value: stats.patientsNew ?? 0,
        subStats: [{ label: 'Patients au total', value: stats.patientsTotal ?? 0 }],
        icon: 'pi pi-users',
        tone: 'blue'
    },
    {
        key: 'treasury',
        label: 'Résultat (transactions)',
        value: formatFcfa(stats.resultTotal),
        subStats: [
            { label: 'Revenus', value: formatFcfa(stats.revenueTotal) },
            { label: 'Dépenses', value: formatFcfa(stats.expenseTotal) }
        ],
        icon: 'pi pi-wallet',
        tone: Number(stats.resultTotal || 0) < 0 ? 'rose' : 'emerald'
    },
    {
        key: 'billing',
        label: 'Total facturé',
        value: formatFcfa(stats.billedTotal),
        subStats: [
            { label: 'Factures normales', value: formatFcfa(stats.normalBilledTotal) },
            { label: 'Factures assurances', value: formatFcfa(stats.insuranceBilledTotal) },
            { label: 'Factures services cabinets', value: formatFcfa(stats.servicesCabinet?.facture) },
            { label: 'Total encaissé', value: formatFcfa(stats.collectedTotal) }
        ],
        icon: 'pi pi-chart-line',
        tone: 'indigo'
    },
    {
        key: 'employees',
        label: 'Employés (total)',
        value: stats.employeesTotal ?? 0,
        icon: 'pi pi-briefcase',
        tone: 'violet'
    },
    {
        key: 'payroll',
        label: 'Masse salariale',
        value: formatFcfa(stats.payrollTotal ?? (Number(stats.payrollFixed || 0) + Number(stats.payrollPercentage || 0))),
        subStats: [
            { label: 'Part fixe', value: formatFcfa(stats.payrollFixed) },
            { label: 'Part variable', value: formatFcfa(stats.payrollPercentage) }
        ],
        icon: 'pi pi-money-bill',
        tone: 'orange'
    },
    {
        key: 'rooms',
        label: 'Salles de consultation',
        value: stats.consultRoomsCount ?? 0,
        icon: 'pi pi-building',
        tone: 'cyan'
    },
    {
        key: 'consumables',
        label: 'Produits consommables',
        value: stats.consumablesCount ?? 0,
        icon: 'pi pi-box',
        tone: 'orange'
    },
    {
        key: 'users',
        label: 'Utilisateurs',
        value: stats.usersTotal ?? 0,
        subStats: [
            { label: 'Administrateurs', value: stats.usersAdmin ?? 0 },
            { label: 'Réception', value: stats.usersReceptionist ?? 0 },
            { label: 'Médecins', value: stats.usersDoctor ?? 0 }
        ],
        icon: 'pi pi-id-card',
        tone: 'teal'
    }
];

const chartData = computed(() => {
    const stats = props.stats || {};
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: ['Revenus', 'Dépenses', 'Facturé', 'Encaissé'],
        datasets: [
            {
                label: 'Montants (Fcfa)',
                backgroundColor: [
                    documentStyle.getPropertyValue('--p-emerald-500'),
                    documentStyle.getPropertyValue('--p-orange-500'),
                    documentStyle.getPropertyValue('--p-primary-500'),
                    documentStyle.getPropertyValue('--p-indigo-500')
                ],
                borderColor: [
                    documentStyle.getPropertyValue('--p-emerald-500'),
                    documentStyle.getPropertyValue('--p-orange-500'),
                    documentStyle.getPropertyValue('--p-primary-500'),
                    documentStyle.getPropertyValue('--p-indigo-500')
                ],
                data: [
                    Number(stats.revenueTotal || 0),
                    Number(stats.expenseTotal || 0),
                    Number(stats.billedTotal || 0),
                    Number(stats.collectedTotal || 0)
                ]
            }
        ]
    };
});

const chartOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false,
                labels: {
                    color: textColor
                }
            }
        },
        scales: {
            x: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    display: false
                }
            },
            y: {
                ticks: {
                    color: textColorSecondary
                },
                grid: {
                    color: surfaceBorder
                }
            }
        }
    };
});

function formatItemSubStats(item) {
    if (item.subStats?.length) {
        return item.subStats.map(({ label, value }) => `${label} : ${value}`).join('\n');
    }
    return Array.isArray(item.sub) ? item.sub.join('\n') : item.sub;
}

function printSection() {
    printReport({
        title: 'Statistiques globales',
        periodLabel: formatAsOfLabel(),
        sections: [
            {
                title: 'Indicateurs clés du cabinet',
                items: items(props.stats).map((item) => ({
                    label: item.label,
                    value: item.value,
                    sub: formatItemSubStats(item)
                }))
            }
        ]
    });
}
</script>

<template>
    <section class="space-y-4" id="admin-global-stats">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Statistiques globales</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Vue d'ensemble des indicateurs clés du cabinet</p>
            </div>
            <div class="flex items-center gap-2">
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                <Button label="Imprimer" icon="pi pi-print" outlined size="small" @click="printSection" />
            </div>
        </div>

        <StatsCardsGrid v-if="!showChart" :items="items(stats)" :loading="loading" />
        <div v-else class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 to-surface-50/70 p-4 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
            <div class="aspect-[16/9] w-full">
                <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </section>
</template>
