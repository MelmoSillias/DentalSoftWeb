<script setup>
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';

const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});
</script>

<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 v-if="title" class="text-lg font-semibold text-surface-900 dark:text-surface-0">{{ title }}</h3>
                <p v-if="subtitle" class="text-sm text-surface-500 dark:text-surface-400">{{ subtitle }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-5">
            <Card
                v-for="item in items"
                :key="item.key || item.label"
                class="rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 via-surface-0 to-surface-50/80 shadow-sm transition hover:shadow-md dark:border-surface-700 dark:from-surface-900 dark:to-surface-800"
            >
                <template #content>
                    <div class="flex min-h-[110px] items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                {{ item.label }}
                            </p>
                            <Skeleton v-if="loading" width="6rem" height="1.8rem" borderRadius="8px" />
                            <p v-else class="text-2xl font-semibold text-surface-900 dark:text-surface-0">
                                {{ item.value }}
                            </p>
                            <p v-if="item.sub" class="text-xs text-surface-500 dark:text-surface-400">{{ item.sub }}</p>
                        </div>
                        <div
                            v-if="item.icon"
                            class="flex h-11 w-11 items-center justify-center rounded-2xl ring-1 ring-inset ring-surface-200/70 dark:ring-surface-700/80"
                            :class="item.iconBg || 'bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300'"
                        >
                            <i :class="item.icon"></i>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>
