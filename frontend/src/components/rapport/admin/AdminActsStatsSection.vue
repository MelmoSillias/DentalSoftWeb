<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';
import ToggleButton from 'primevue/togglebutton';
import CategoryStackedActsChart from '@/components/rapport/admin/CategoryStackedActsChart.vue';
import { printReport } from '@/utils/reportPrint';

const props = defineProps({
    actsStats: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    periodLabel: { type: String, default: '' }
});

const showChart = ref(false);

const categories = computed(() => (Array.isArray(props.actsStats) ? props.actsStats : []));

const hasData = computed(() =>
    categories.value.some((category) => (category.items || []).some((item) => Number(item.value) > 0))
);

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
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-0">Statistiques des soins médicaux</h3>
                <p v-if="periodLabel" class="text-sm text-surface-500 dark:text-surface-400">Période : {{ periodLabel }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <ToggleButton v-model="showChart" onLabel="Graphique" offLabel="Données" onIcon="pi pi-chart-bar" offIcon="pi pi-list" />
                <Button label="Imprimer" icon="pi pi-print" outlined size="small" @click="printSection" />
            </div>
        </div>

        <div v-if="loading" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card v-for="n in 3" :key="`acts-skel-${n}`" class="rounded-2xl border border-surface-200/60 dark:border-surface-700">
                <template #content>
                    <Skeleton width="40%" height="1rem" class="mb-3" />
                    <Skeleton width="100%" height="4rem" />
                </template>
            </Card>
        </div>

        <div v-else-if="showChart" class="rounded-2xl border border-surface-200/60 bg-surface-0 p-4 dark:border-surface-700 dark:bg-surface-900">
            <CategoryStackedActsChart :categories="categories" />
        </div>

        <div v-else-if="hasData || categories.length" class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <Card
                v-for="category in categories"
                :key="category.id || category.label || 'autres'"
                class="overflow-hidden rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 via-surface-0 to-surface-50/80 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800"
            >
                <template #content>
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                {{ category.label || 'Autres' }}
                            </p>
                            <p class="text-2xl font-semibold text-surface-900 dark:text-surface-0">{{ category.total || 0 }}</p>
                        </div>
                        <Tag :value="`${(category.items || []).length} soin(s)`" severity="secondary" />
                    </div>
                    <ul v-if="(category.items || []).length" class="space-y-2">
                        <li
                            v-for="item in category.items"
                            :key="`${category.label}-${item.label}`"
                            class="flex items-center justify-between gap-2 rounded-lg border border-surface-100 px-2 py-1.5 dark:border-surface-700/80"
                        >
                            <span class="text-sm font-medium text-surface-800 dark:text-surface-100">{{ item.label }}</span>
                            <Tag :value="item.value" />
                        </li>
                    </ul>
                    <p v-else class="text-sm text-surface-500 dark:text-surface-400">Aucun soin enregistré.</p>
                </template>
            </Card>
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed border-surface-300 bg-surface-0 p-8 text-center text-sm text-surface-500 dark:border-surface-600 dark:bg-surface-900 dark:text-surface-400"
        >
            Aucun soin enregistré.
        </div>
    </section>
</template>
