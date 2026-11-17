<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import AppChart from '@/shared/ui/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import { overviewCards } from '@/domains/reporting/model/reportOverview.js';
import { useReportChartTheme } from '@/domains/reporting/composables/useReportChartTheme.js';
import { formatAsOfLabel, printReport } from '@/domains/reporting/model/reportPrint.js';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' }
});
const showChart = ref(false);
const { chartStyle } = useReportChartTheme();
const items = overviewCards;
const groups = computed(() => {
    const cards = items(props.stats);
    return [
        { key: 'financials', title: 'Situation financière', items: cards.slice(0, 4) },
        { key: 'activity', title: 'Patients et ressources', items: cards.slice(4) }
    ];
});

const chartData = computed(() => {
    const stats = props.stats || {};
    const documentStyle = chartStyle;
    return {
        labels: ['Entrées validées', 'Sorties validées', 'Montant facturé', 'Paiements patients reçus'],
        datasets: [
            {
                label: 'Montants (Fcfa)',
                backgroundColor: [documentStyle.getPropertyValue('--p-emerald-500'), documentStyle.getPropertyValue('--p-orange-500'), documentStyle.getPropertyValue('--p-primary-500'), documentStyle.getPropertyValue('--p-indigo-500')],
                borderColor: [documentStyle.getPropertyValue('--p-emerald-500'), documentStyle.getPropertyValue('--p-orange-500'), documentStyle.getPropertyValue('--p-primary-500'), documentStyle.getPropertyValue('--p-indigo-500')],
                data: [Number(stats.revenueTotal || 0), Number(stats.expenseTotal || 0), Number(stats.billedTotal || 0), Number(stats.collectedTotal || 0)]
            }
        ]
    };
});

const chartOptions = computed(() => {
    const documentStyle = chartStyle;
    const textColor = documentStyle.getPropertyValue('--text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false, labels: { color: textColor } }
        },
        scales: {
            x: { ticks: { color: textColorSecondary }, grid: { display: false } },
            y: { ticks: { color: textColorSecondary }, grid: { color: surfaceBorder } }
        }
    };
});

function printSection() {
    printReport({
        title: 'Vue d’ensemble',
        periodLabel: props.periodLabel || formatAsOfLabel(),
        sections: items(props.stats).map((item) => ({
            title: item.label,
            items: [{ label: 'Total', value: item.value }, ...(item.subStats || [])],
            note: item.note
        }))
    });
}

function printBento(item) {
    printReport({
        title: item.label,
        periodLabel: props.periodLabel || formatAsOfLabel(),
        landscape: false,
        sections: [{ title: item.label, items: [{ label: 'Total', value: item.value }, ...(item.subStats || [])], note: item.note }]
    });
}
</script>

<template>
    <section id="admin-global-stats" class="report-overview" :aria-busy="loading">
        <div class="report-overview__header">
            <div>
                <h3>Vue d'ensemble</h3>
                <p>Montants de la période sélectionnée. Les effectifs et créances actuels sont indiqués séparément.</p>
            </div>
            <div class="report-overview__actions">
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Graphique" onIcon="pi pi-chart-bar" offIcon="pi pi-chart-bar" size="small" aria-label="Afficher le graphique financier" />
                <Button label="Imprimer" icon="pi pi-print" severity="secondary" outlined size="small" :disabled="loading" @click="printSection" />
            </div>
        </div>

        <template v-if="!showChart">
            <section v-for="group in groups" :key="group.key" class="overview-group" :aria-labelledby="`overview-${group.key}`">
                <h4 :id="`overview-${group.key}`" class="overview-group__title">{{ group.title }}</h4>
                <div class="overview-grid" :class="{ 'overview-grid--resources': group.key === 'activity' }">
                    <article v-for="item in group.items" :key="item.key" class="overview-stat">
                        <div class="overview-stat__header">
                            <h5>{{ item.label }}</h5>
                            <Button icon="pi pi-print" text severity="secondary" size="small" class="overview-stat__print" :aria-label="'Imprimer : ' + item.label" :title="'Imprimer : ' + item.label" :disabled="loading" @click="printBento(item)" />
                        </div>
                        <div v-if="loading" class="my-3 h-8 w-32 animate-pulse rounded bg-surface-200 dark:bg-surface-700" aria-label="Chargement"></div>
                        <p v-else class="overview-stat__value" :class="{ 'overview-stat__value--negative': item.key === 'treasury' && Number(stats.resultTotal) < 0 }">{{ item.value }}</p>
                        <dl v-if="item.subStats?.length" class="overview-stat__details">
                            <div v-for="sub in item.subStats" :key="sub.label">
                                <dt>{{ sub.label }}</dt>
                                <dd>{{ loading ? '…' : sub.value }}</dd>
                            </div>
                        </dl>
                        <p v-if="item.note" class="overview-stat__note">{{ item.note }}</p>
                    </article>
                </div>
            </section>
        </template>
        <div v-else class="overview-chart">
            <div v-if="loading" class="flex h-full items-center justify-center text-sm text-surface-500" role="status">Chargement du graphique…</div>
            <AppChart v-else type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
        </div>
    </section>
</template>

<style scoped>
.report-overview {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.report-overview__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.report-overview__header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-color);
}
.report-overview__header p {
    margin: 0.375rem 0 0;
    max-width: 42rem;
    font-size: 0.8125rem;
    line-height: 1.6;
    color: var(--text-color-secondary);
}
.report-overview__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.overview-group__title {
    margin: 0 0 0.75rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-color-secondary);
}
.overview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 1rem;
}
.overview-grid--resources {
    grid-template-columns: repeat(3, minmax(0, 1fr));
}
.overview-stat {
    display: flex;
    flex-direction: column;
    min-width: 0;
    padding: 1.25rem;
    border: 1px solid var(--surface-border);
    border-radius: 0.75rem;
    background: var(--surface-card);
}
.overview-stat__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}
.overview-stat__header h5 {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.5;
    color: var(--text-color-secondary);
}
.overview-stat__print {
    flex-shrink: 0;
    width: 1.75rem;
    height: 1.75rem;
}
.overview-stat__value {
    margin: 0.5rem 0 0;
    font-size: clamp(1.375rem, 2vw, 1.875rem);
    font-weight: 600;
    letter-spacing: -0.025em;
    line-height: 1.4;
    color: var(--text-color);
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.overview-stat__value--negative {
    color: var(--p-red-500);
}
.overview-stat__details {
    display: grid;
    margin: 1rem 0 0;
}
.overview-stat__details > div {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 0.8fr);
    align-items: baseline;
    gap: 0.75rem;
    padding: 0.625rem 0;
    border-top: 1px solid var(--surface-border);
    font-size: 0.8125rem;
    line-height: 1.5;
}
.overview-stat__details dt {
    color: var(--text-color-secondary);
}
.overview-stat__details dd {
    margin: 0;
    font-weight: 500;
    text-align: right;
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
}
.overview-stat__note {
    margin: auto 0 0;
    padding-top: 0.75rem;
    font-size: 0.75rem;
    line-height: 1.6;
    color: var(--text-color-secondary);
}
.overview-chart {
    height: clamp(20rem, 45vw, 30rem);
    padding: 1.25rem;
    border: 1px solid var(--surface-border);
    border-radius: 0.75rem;
    background: var(--surface-card);
}
@media (max-width: 1023px) {
    .overview-grid--resources {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 639px) {
    .overview-grid,
    .overview-grid--resources {
        grid-template-columns: minmax(0, 1fr);
    }
    .overview-stat {
        padding: 1rem;
    }
}
@media print {
    .overview-grid {
        display: block;
    }
    .overview-stat {
        break-inside: avoid;
        margin-bottom: 1rem;
    }
    .overview-stat__print,
    .report-overview__actions {
        display: none;
    }
}
</style>
