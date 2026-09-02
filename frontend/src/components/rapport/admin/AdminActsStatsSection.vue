<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import AppChart from '@/components/common/AppChart.vue';
import ToggleButton from 'primevue/togglebutton';
import ValueListCard from '@/components/rapport/common/ValueListCard.vue';
import { printReport } from '@/utils/reportPrint';

const props = defineProps({
    actsStats: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' }
});
const showChart = ref(false);

const chartData = computed(() => {
    const documentStyle = getComputedStyle(document.documentElement);
    return {
        labels: props.actsStats.map((row) => row.label),
        datasets: [
            {
                label: 'Actes',
                backgroundColor: documentStyle.getPropertyValue('--p-primary-500'),
                data: props.actsStats.map((row) => row.value || 0)
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

function printSection() {
    printReport({
        title: 'Statistiques des soins médicaux',
        periodLabel: props.periodLabel,
        sections: [
            {
                title: 'Répartition des soins',
                items: props.actsStats,
                emptyLabel: 'Aucun soin enregistré.'
            }
        ]
    });
}
</script>

<template>
    <section class="space-y-4" id="admin-acts-stats">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Statistiques des soins médicaux</h3>
                <p v-if="periodLabel" class="text-sm text-surface-500 dark:text-surface-400">Période : {{ periodLabel }}</p>
            </div>
            <Button label="Imprimer" icon="pi pi-print" outlined size="small" @click="printSection" />
        </div>
        <ValueListCard title="Répartition des soins" :items="actsStats" :loading="loading" :show-chart="showChart" empty-label="Aucun soin enregistré.">
            <template #actions>
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
            </template>
            <template #chart>
                <div class="aspect-[16/9] w-full">
                    <AppChart type="bar" :data="chartData" :options="chartOptions" class="h-full w-full" />
                </div>
            </template>
        </ValueListCard>
    </section>
</template>
