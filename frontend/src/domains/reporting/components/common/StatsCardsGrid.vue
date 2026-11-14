<script setup>
import Card from 'primevue/card';
import Skeleton from 'primevue/skeleton';

defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false }
});

const toneClasses = {
    blue: {
        card: 'border-l-4 border-l-blue-500',
        icon: 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-300',
        value: 'text-blue-700 dark:text-blue-300'
    },
    cyan: {
        card: 'border-l-4 border-l-cyan-500',
        icon: 'bg-cyan-50 text-cyan-600 dark:bg-cyan-900/20 dark:text-cyan-300',
        value: 'text-cyan-700 dark:text-cyan-300'
    },
    emerald: {
        card: 'border-l-4 border-l-emerald-500',
        icon: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/20 dark:text-emerald-300',
        value: 'text-emerald-700 dark:text-emerald-300'
    },
    indigo: {
        card: 'border-l-4 border-l-indigo-500',
        icon: 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/20 dark:text-indigo-300',
        value: 'text-indigo-700 dark:text-indigo-300'
    },
    orange: {
        card: 'border-l-4 border-l-orange-500',
        icon: 'bg-orange-50 text-orange-600 dark:bg-orange-900/20 dark:text-orange-300',
        value: 'text-orange-700 dark:text-orange-300'
    },
    teal: {
        card: 'border-l-4 border-l-teal-500',
        icon: 'bg-teal-50 text-teal-600 dark:bg-teal-900/20 dark:text-teal-300',
        value: 'text-teal-700 dark:text-teal-300'
    },
    violet: {
        card: 'border-l-4 border-l-violet-500',
        icon: 'bg-violet-50 text-violet-600 dark:bg-violet-900/20 dark:text-violet-300',
        value: 'text-violet-700 dark:text-violet-300'
    },
    rose: {
        card: 'border-l-4 border-l-rose-500',
        icon: 'bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-300',
        value: 'text-rose-700 dark:text-rose-300'
    }
};

function toneClass(item, part) {
    return toneClasses[item.tone]?.[part] || '';
}

function subLines(sub) {
    if (Array.isArray(sub)) {
        return sub.filter((line) => line != null && String(line).trim() !== '');
    }
    if (sub == null || String(sub).trim() === '') {
        return [];
    }
    return [sub];
}
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
                class="overflow-hidden rounded-2xl border border-surface-200/60 bg-gradient-to-br from-surface-0 via-surface-0 to-surface-50/80 shadow-sm transition hover:shadow-md dark:border-surface-700 dark:from-surface-900 dark:to-surface-800"
                :class="toneClass(item, 'card')"
            >
                <template #content>
                    <div class="flex min-h-[110px] items-start justify-between gap-3">
                        <div class="space-y-1">
                            <p class="text-xs font-semibold uppercase tracking-wide text-surface-500 dark:text-surface-400">
                                {{ item.label }}
                            </p>
                            <Skeleton v-if="loading" width="6rem" height="1.8rem" borderRadius="8px" />
                            <p v-else class="text-2xl font-semibold text-surface-900 dark:text-surface-0" :class="toneClass(item, 'value')">
                                {{ item.value }}
                            </p>
                            <div v-if="item.subStats?.length" class="mt-3 space-y-2 border-t border-surface-200/70 pt-3 dark:border-surface-700/70">
                                <div v-for="(stat, index) in item.subStats" :key="`${item.key || item.label}-stat-${index}`" class="flex items-center justify-between gap-3 text-xs">
                                    <span class="text-surface-500 dark:text-surface-400">{{ stat.label }}</span>
                                    <span class="text-right font-semibold text-surface-700 dark:text-surface-200">{{ stat.value }}</span>
                                </div>
                            </div>
                            <div v-else-if="subLines(item.sub).length" class="space-y-0.5">
                                <p v-for="(line, index) in subLines(item.sub)" :key="`${item.key || item.label}-sub-${index}`" class="text-xs text-surface-500 dark:text-surface-400">
                                    {{ line }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="item.icon"
                            class="flex h-11 w-11 items-center justify-center rounded-2xl ring-1 ring-inset ring-surface-200/70 dark:ring-surface-700/80"
                            :class="item.iconBg || toneClass(item, 'icon') || 'bg-primary-50 text-primary-600 dark:bg-primary-900/20 dark:text-primary-300'"
                        >
                            <i :class="item.icon"></i>
                        </div>
                    </div>
                </template>
            </Card>
        </div>
    </div>
</template>
