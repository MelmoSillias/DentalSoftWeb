<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';
import ToggleButton from 'primevue/togglebutton';
import CategoryStackedActsChart from '@/domains/reporting/components/admin/CategoryStackedActsChart.vue';
import { printReport } from '@/domains/reporting/model/reportPrint.js';

const props = defineProps({
    actsStats: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' }
});

const showChart = ref(false);

const categories = computed(() => (Array.isArray(props.actsStats) ? props.actsStats : []));

const hasData = computed(() => categories.value.some((category) => (category.items || []).some((item) => Number(item.value) > 0)));

function printSection() {
    printReport({
        title: 'Statistiques des soins médicaux',
        periodLabel: props.periodLabel,
        sections: categories.value.map((category) => ({
            title: `${category.label || 'Autres'} (${category.total || 0})`,
            items: category.items || [],
            emptyLabel: 'Aucun soin dans cette catégorie.'
        }))
    });
}
</script>

<template>
    <section class="space-y-4" id="admin-acts-stats">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-0">Statistiques des soins médicaux</h3>
                <p v-if="periodLabel" class="text-sm text-surface-500 dark:text-surface-400">Période : {{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <ToggleButton size="small" v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                <Button label="Imprimer" icon="pi pi-print" outlined size="small" @click="printSection" />
            </div>
        </div>

        <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="n in 3" :key="`acts-skel-${n}`" class="rounded-xl border border-surface-200 dark:border-surface-700">
                <template #content>
                    <Skeleton width="40%" height="1rem" class="mb-3" />
                    <Skeleton width="100%" height="4rem" />
                </template>
            </Card>
        </div>

        <div v-else-if="showChart" class="rounded-xl border border-surface-200 bg-surface-0 p-4 dark:border-surface-700 dark:bg-surface-900">
            <CategoryStackedActsChart :categories="categories" />
        </div>

        <div v-else-if="hasData || categories.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="category in categories" :key="category.id || category.label || 'autres'" class="min-w-0 overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900">
                <template #content>
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-medium text-surface-500 dark:text-surface-400">
                                {{ category.label || 'Autres' }}
                            </p>
                            <p class="mt-1 text-xl font-semibold tabular-nums text-surface-900 dark:text-surface-0">{{ category.total || 0 }}</p>
                        </div>
                        <span class="shrink-0 text-xs tabular-nums text-surface-500 dark:text-surface-400">{{ (category.items || []).length }} soin(s)</span>
                    </div>
                    <ul v-if="(category.items || []).length" class="divide-y divide-surface-200 dark:divide-surface-700">
                        <li v-for="item in category.items" :key="`${category.label}-${item.label}`" class="flex items-baseline justify-between gap-3 py-2.5">
                            <span class="text-sm font-medium text-surface-800 dark:text-surface-100">{{ item.label }}</span>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-surface-900 dark:text-surface-0">{{ item.value }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun soin enregistré.</p>
                </template>
            </Card>
        </div>

        <div v-else class="rounded-xl border border-surface-300 bg-surface-0 p-8 text-center text-sm text-surface-500 dark:border-surface-600 dark:bg-surface-900 dark:text-surface-400">Aucun soin enregistré.</div>
    </section>
</template>
