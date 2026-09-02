<script setup>
import AppChart from '@/components/common/AppChart.vue';
import Skeleton from 'primevue/skeleton';
import { computed } from 'vue';

const props = defineProps({
    referrals: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    }
});

const referralLabels = {
    'Reseaux sociaux': 'Réseaux sociaux',
    'Bouche a oreille': 'Bouche à oreille',
    'Bouche a bouche': 'Bouche à oreille',
    Recommandation: 'Recommandation',
    'Par un medecin': 'Par un médecin',
    Publicite: 'Publicité',
    Autres: 'Autres',
    'Non renseigné': 'Non renseigné'
};

const allReferralSources = ['Reseaux sociaux', 'Bouche a oreille', 'Recommandation', 'Par un medecin', 'Publicite', 'Autres', 'Non renseigné'];

const barColors = ['rgba(6, 182, 212, 0.85)', 'rgba(59, 130, 246, 0.85)', 'rgba(16, 185, 129, 0.85)', 'rgba(245, 158, 11, 0.85)', 'rgba(236, 72, 153, 0.85)', 'rgba(139, 92, 246, 0.85)', 'rgba(100, 116, 139, 0.85)'];

const formatLabel = (source) => referralLabels[source] || source || 'Non renseigné';

const normalizeReferralSource = (source) => (source === 'Bouche a bouche' ? 'Bouche a oreille' : source);

const items = computed(() => {
    const rows = Array.isArray(props.referrals) ? props.referrals : [];
    const countBySource = rows.reduce((acc, row) => {
        const rawSource = (row.source ?? '') !== '' ? String(row.source) : 'Non renseigné';
        const source = normalizeReferralSource(rawSource);
        acc[source] = (acc[source] || 0) + Number(row.count || 0);
        return acc;
    }, {});

    const extraSources = Object.keys(countBySource).filter((source) => !allReferralSources.includes(source));

    const mergedSources = [...allReferralSources, ...extraSources];
    const total = mergedSources.reduce((sum, source) => sum + (countBySource[source] || 0), 0);

    return mergedSources.map((source, index) => {
        const count = countBySource[source] || 0;
        const percent = total > 0 ? Math.round((count / total) * 100) : 0;

        return {
            key: source,
            label: formatLabel(source),
            count,
            percent,
            color: barColors[index % barColors.length]
        };
    });
});

const totalReferrals = computed(() => items.value.reduce((sum, item) => sum + item.count, 0));

const chartData = computed(() => ({
    labels: items.value.map((item) => item.label),
    datasets: [
        {
            label: 'Patients',
            data: items.value.map((item) => item.count),
            backgroundColor: items.value.map((item) => item.color),
            borderRadius: 8,
            borderSkipped: false,
            barThickness: 18
        }
    ]
}));

const chartOptions = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const textColor = documentStyle.getPropertyValue('--text-color');
    const textColorSecondary = documentStyle.getPropertyValue('--text-color-secondary');
    const surfaceBorder = documentStyle.getPropertyValue('--surface-border');

    return {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: (context) => {
                        const value = Number(context.raw || 0);
                        const percent = totalReferrals.value > 0 ? Math.round((value / totalReferrals.value) * 100) : 0;
                        return `${value} patient(s) (${percent} %)`;
                    }
                }
            }
        },
        scales: {
            x: {
                beginAtZero: true,
                ticks: {
                    color: textColorSecondary,
                    precision: 0
                },
                grid: {
                    color: surfaceBorder,
                    drawBorder: false
                }
            },
            y: {
                ticks: {
                    color: textColor,
                    font: { size: 12 }
                },
                grid: { display: false }
            }
        }
    };
});
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm" data-tour="patients-list.referrals">
        <div class="px-4 sm:px-6 py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <div class="flex items-center gap-3">
                <div class="p-2 rounded-lg bg-cyan-100 dark:bg-cyan-900/30">
                    <i class="fas fa-bullhorn text-cyan-600 dark:text-cyan-400"></i>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Référencement patients</h3>
                    <p class="text-sm text-surface-600 dark:text-surface-400">Comment les patients ont connu le cabinet</p>
                </div>
            </div>
        </div>

        <div class="p-4 sm:p-6">
            <div v-if="loading" class="grid gap-4 lg:grid-cols-2">
                <div class="space-y-3">
                    <Skeleton v-for="n in 5" :key="n" height="3.5rem" borderRadius="12px" />
                </div>
                <Skeleton height="280px" borderRadius="12px" />
            </div>

            <div v-else class="grid gap-6 lg:grid-cols-2 lg:items-stretch">
                <div class="space-y-3">
                    <div v-for="item in items" :key="item.key" class="rounded-xl border border-surface-200/60 bg-surface-50/80 px-4 py-3 dark:border-surface-700/60 dark:bg-surface-900/40">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: item.color }"></span>
                                <span class="truncate font-medium text-surface-800 dark:text-surface-100">
                                    {{ item.label }}
                                </span>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-surface-900 dark:text-surface-50">
                                {{ item.count }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700">
                                <div class="h-full rounded-full transition-all duration-500" :style="{ width: `${item.percent}%`, backgroundColor: item.color }"></div>
                            </div>
                            <span class="w-10 text-right text-xs font-medium text-surface-500 dark:text-surface-400"> {{ item.percent }}% </span>
                        </div>
                    </div>

                    <div class="rounded-xl border border-dashed border-surface-300/70 px-4 py-3 text-sm text-surface-600 dark:border-surface-600 dark:text-surface-300">
                        <span class="font-medium text-surface-800 dark:text-surface-100">{{ totalReferrals }}</span>
                        patient(s) au total
                    </div>
                </div>

                <div class="rounded-xl border border-surface-200/60 bg-gradient-to-br from-surface-0 to-surface-50/80 p-4 dark:border-surface-700/60 dark:from-surface-900 dark:to-surface-800/80">
                    <p class="mb-3 text-sm font-medium text-surface-600 dark:text-surface-300">Répartition visuelle</p>
                    <div class="h-[280px] sm:h-[320px]">
                        <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
