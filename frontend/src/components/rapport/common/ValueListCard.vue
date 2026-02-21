<script setup>
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';
import Tag from 'primevue/tag';

const props = defineProps({
    title: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    emptyLabel: { type: String, default: 'Aucune donnée disponible.' },
    showChart: { type: Boolean, default: false }
});
</script>

<template>
    <Card class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 via-surface-0 to-surface-50/70 shadow-sm dark:border-surface-700 dark:from-surface-900 dark:to-surface-800">
        <template #title>
            <div class="flex items-center justify-between gap-3 border-b border-surface-200/60 pb-3 dark:border-surface-700/60">
                <span class="text-base font-semibold text-surface-900 dark:text-surface-0">{{ title }}</span>
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
                <ul v-else class="space-y-2">
                    <li
                        v-for="item in items"
                        :key="item.key || item.label"
                        class="flex items-center justify-between gap-3 rounded-xl border border-surface-200/60 bg-surface-50/80 px-3 py-2 text-sm text-surface-700 shadow-sm transition hover:shadow-md dark:border-surface-700/60 dark:bg-surface-800/70 dark:text-surface-200"
                    >
                        <span class="font-medium">{{ item.label }}</span>
                        <Tag :value="item.value" :severity="item.severity || 'secondary'" />
                    </li>
                    <li v-if="!items.length" class="text-sm text-surface-400">{{ emptyLabel }}</li>
                </ul>
                <slot name="footer" />
            </template>
        </template>
    </Card>
</template>
