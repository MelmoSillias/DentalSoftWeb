<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import AppChart from '@/components/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import { overviewCards } from '@/utils/reportOverview';
import { formatAsOfLabel, printReport } from '@/utils/reportPrint';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' }
});
const showChart = ref(false);

const items = overviewCards;
const bentoSize = (item) => {
    const details = item.subStats?.length ?? 0;
    return details >= 6 ? 'bento-card--large' : details >= 3 ? 'bento-card--medium' : details ? 'bento-card--small' : 'bento-card--compact';
};

const chartData = computed(() => {
    const stats = props.stats || {};
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: ['Entrées validées', 'Sorties validées', 'Montant facturé', 'Paiements patients reçus'],
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

function printSection() {
    printReport({
        title: "Vue d’ensemble",
        periodLabel: props.periodLabel || formatAsOfLabel(),
        sections: items(props.stats).map((item) => ({
            title: item.label,
            items: [{ label: 'Total', value: item.value }, ...(item.subStats || [])],
            note: item.note
        }))
    });
}

</script>

<template>
    <section class="space-y-4" id="admin-global-stats">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Statistiques globales</h3>
                <p class="text-sm text-surface-500 dark:text-surface-400">Activité et flux de la période sélectionnée · effectifs et créances actuels signalés dans chaque case</p>
            </div>
            <div class="flex items-center gap-2">
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                <Button label="Imprimer" icon="pi pi-print" outlined size="small" @click="printSection" />
            </div>
        </div>

        <div v-if="!showChart" class="stats-bento" :aria-busy="loading">
            <article v-for="item in items(stats)" :key="item.key" :class="['bento-card', bentoSize(item), 'bento-tone--' + item.tone]">
                <div class="flex items-start justify-between gap-3">
                    <h3 class="text-xl font-medium text-surface-600 dark:text-surface-300">{{ item.label }}</h3>
                    <span class="bento-icon" aria-hidden="true"><i :class="item.icon"></i></span>
                </div>
                <div v-if="loading" class="mt-3 h-9 w-32 animate-pulse rounded bg-surface-200 dark:bg-surface-700" aria-label="Chargement"></div>
                <p v-else class="bento-value">{{ item.value }}</p>
                <dl v-if="item.subStats?.length" class="bento-details">
                    <div v-for="sub in item.subStats" :key="sub.label">
                        <dt>{{ sub.label }}</dt>
                        <dd><span v-if="loading">…</span><span v-else>{{ sub.value }}</span></dd>
                    </div>
                </dl>
                <p v-if="item.note" class="bento-note">{{ item.note }}</p>
            </article>
        </div>
        <div v-else class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 to-surface-50/70 p-4 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
            <div class="aspect-[16/9] w-full">
                <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
            </div>
        </div>
    </section>
</template>

<style scoped>
.stats-bento { display: flex; flex-wrap: wrap; gap: 1rem; align-items: stretch; }
.bento-card { --bento-accent: var(--p-primary-500); min-width: 0; flex: 1 0 100%; padding: 1.35rem; border: 1px solid var(--p-surface-200); border-radius: 1.35rem; background: linear-gradient(140deg, color-mix(in srgb, var(--bento-accent) 7%, var(--p-surface-0)), var(--p-surface-0) 60%); display: flex; flex-direction: column; box-shadow: 0 3px 18px rgb(15 23 42 / 3%); }
:global(.app-dark .bento-card) { border-color: var(--p-surface-700); background: linear-gradient(140deg, color-mix(in srgb, var(--bento-accent) 12%, var(--p-surface-900)), var(--p-surface-900) 60%); }
.bento-icon { color: var(--bento-accent); background: color-mix(in srgb, var(--bento-accent) 12%, transparent); border-radius: .8rem; padding: .65rem; flex-shrink: 0; }
.bento-value { font-size: clamp(1.5rem, 2vw, 2.2rem); font-weight: 700; letter-spacing: -.04em; margin: .7rem 0 1rem; overflow-wrap: anywhere; font-variant-numeric: tabular-nums; }
:global(.app-dark .bento-value) { color: var(--p-surface-0); }
:global(.app-dark .bento-details dd) { color: var(--p-surface-100); }
:global(.app-dark .bento-details dt), :global(.app-dark .bento-note) { color: var(--p-surface-400); }
.bento-details { display: grid; gap: .7rem; margin: 0; }
.bento-details > div { display: flex; justify-content: space-between; gap: .8rem; border-top: 1px solid color-mix(in srgb, var(--bento-accent) 12%, transparent); padding-top: .65rem; font-size: .8rem; }
.bento-details dt { color: var(--p-surface-500); line-height: 1.4; }
.bento-details dd { margin: 0; text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; flex-shrink: 0; max-width: 52%; overflow-wrap: anywhere; }
.bento-note { margin-top: auto; padding-top: 1rem; font-size: .72rem; line-height: 1.6; color: var(--p-surface-500); }
.bento-tone--emerald { --bento-accent: var(--p-emerald-500); }
.bento-tone--indigo { --bento-accent: var(--p-indigo-500); }
.bento-tone--violet { --bento-accent: var(--p-violet-500); }
.bento-tone--blue { --bento-accent: var(--p-blue-500); }
.bento-tone--cyan { --bento-accent: var(--p-cyan-500); }
.bento-tone--orange { --bento-accent: var(--p-orange-500); }
.bento-tone--rose { --bento-accent: var(--p-rose-500); }
.bento-tone--teal { --bento-accent: var(--p-teal-500); }
@media (min-width: 640px) { .bento-card { flex-basis: calc((100% - 1rem) / 2); } .bento-card--large { flex-basis: 100%; } .bento-card--compact { flex-basis: calc((100% - 2rem) / 3); } }
@media (min-width: 1280px) { .bento-card--large { flex-basis: calc((100% - 1rem) / 2); } .bento-card--medium { flex-basis: calc((100% - 2rem) / 3); } .bento-card--small { flex-basis: calc((100% - 3rem) / 4); } .bento-card--compact { flex-basis: calc((100% - 5rem) / 6); } }
@media print { .stats-bento { display: block; } .bento-card { break-inside: avoid; margin-bottom: 1rem; } }
</style>
