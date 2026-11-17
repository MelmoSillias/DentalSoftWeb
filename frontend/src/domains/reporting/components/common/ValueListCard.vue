<script setup>
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';

const _props = defineProps({
    title: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyLabel: { type: String, default: 'Aucune donnée disponible.' },
    showChart: { type: Boolean, default: false }
});
</script>

<template>
    <Card class="min-w-0 overflow-hidden rounded-xl border border-surface-200 bg-surface-0 dark:border-surface-700 dark:bg-surface-900">
        <template #title>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <span class="min-w-0 flex-1 text-sm font-semibold leading-6 text-surface-900 dark:text-surface-0">{{ title }}</span>
                <slot name="actions" />
            </div>
        </template>
        <template #content>
            <div v-if="loading" class="space-y-2">
                <Skeleton v-for="n in 3" :key="n" height="1.5rem" borderRadius="8px" />
            </div>
            <template v-else>
                <div v-if="showChart" class="min-h-[220px]">
                    <slot name="chart" />
                </div>
                <ul v-else class="divide-y divide-surface-200 dark:divide-surface-700">
                    <li v-for="item in items" :key="item.key || item.label" class="grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)] items-baseline gap-3 py-2.5 text-sm text-surface-600 dark:text-surface-300">
                        <span class="break-words leading-5">{{ item.label }}</span>
                        <span class="break-words text-right font-semibold tabular-nums leading-5 text-surface-900 dark:text-surface-0">{{ item.value }}</span>
                    </li>
                    <li v-if="!items.length" class="py-6 text-center text-sm text-surface-500 dark:text-surface-400">{{ emptyLabel }}</li>
                </ul>
                <slot name="footer" />
            </template>
        </template>
    </Card>
</template>
