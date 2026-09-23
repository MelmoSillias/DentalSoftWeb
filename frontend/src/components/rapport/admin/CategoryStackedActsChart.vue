<script setup>
import { computed } from 'vue';
import AppChart from '@/components/common/AppChart.vue';

const props = defineProps({
    categories: { type: Array, default: () => [] }
});

const palette = [
    '--p-primary-500',
    '--p-emerald-500',
    '--p-amber-500',
    '--p-cyan-500',
    '--p-rose-500',
    '--p-indigo-500',
    '--p-teal-500',
    '--p-orange-500',
    '--p-sky-500',
    '--p-violet-500'
];

const chartPayload = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    const categories = Array.isArray(props.categories) ? props.categories : [];
    const labels = categories.map((category) => category.label || 'Autres');

    const soinLabels = [];
    const seen = new Set();
    categories.forEach((category) => {
        (category.items || []).forEach((item) => {
            const label = item.label || 'Acte';
            if (!seen.has(label)) {
                seen.add(label);
                soinLabels.push(label);
            }
        });
    });

    const datasets = soinLabels.map((soinLabel, index) => {
        const colorVar = palette[index % palette.length];
        return {
            label: soinLabel,
            backgroundColor: documentStyle.getPropertyValue(colorVar) || `hsl(${(index * 47) % 360} 65% 48%)`,
            data: categories.map((category) => {
                const match = (category.items || []).find((item) => (item.label || 'Acte') === soinLabel);
                return match ? Number(match.value) || 0 : 0;
            }),
            stack: 'soins'
        };
    });

    return {
        data: { labels, datasets },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { color: documentStyle.getPropertyValue('--text-color') }
                },
                tooltip: {
                    callbacks: {
                        footer(items) {
                            const total = items.reduce((sum, item) => sum + (Number(item.raw) || 0), 0);
                            return `Total: ${total}`;
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: { color: documentStyle.getPropertyValue('--text-color-secondary') },
                    grid: { display: false }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    ticks: { color: documentStyle.getPropertyValue('--text-color-secondary') },
                    grid: { color: documentStyle.getPropertyValue('--surface-border') }
                }
            }
        }
    };
});
</script>

<template>
    <div class="aspect-[16/9] w-full min-h-[260px]">
        <AppChart
            v-if="chartPayload.data.labels.length && chartPayload.data.datasets.length"
            type="bar"
            :data="chartPayload.data"
            :options="chartPayload.options"
            class="h-full w-full"
        />
        <div v-else class="flex h-full items-center justify-center text-sm text-surface-500 dark:text-surface-400">
            Aucun soin à afficher.
        </div>
    </div>
</template>
