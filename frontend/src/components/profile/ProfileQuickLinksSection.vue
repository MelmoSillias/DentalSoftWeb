<script setup>
import { useRouter } from 'vue-router';

const props = defineProps({
    links: { type: Array, default: () => [] }
});

const router = useRouter();

const iconColorClass = (icon) => {
    if (icon?.includes('chart')) return 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400';
    if (icon?.includes('users') || icon?.includes('user')) return 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400';
    if (icon?.includes('calendar')) return 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400';
    if (icon?.includes('wallet') || icon?.includes('money') || icon?.includes('inbox')) return 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400';
    if (icon?.includes('briefcase')) return 'bg-cyan-100 text-cyan-600 dark:bg-cyan-900/40 dark:text-cyan-400';
    return 'bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-400';
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 shadow-sm">
        <!-- Header -->
        <div class="flex items-center gap-3 px-5 py-4 border-b border-surface-100 dark:border-surface-700/60">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-primary-600 shadow-sm">
                <i class="pi pi-th-large text-white text-sm"></i>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-surface-400">Navigation</p>
                <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Accès rapides</h3>
            </div>
        </div>

        <div class="p-4">
            <div v-if="links.length" class="space-y-2">
                <RouterLink
                    v-for="link in links"
                    :key="link.label"
                    :to="link.to"
                    class="group flex items-center justify-between rounded-xl border border-surface-100 dark:border-surface-700/60 bg-surface-50/60 dark:bg-surface-700/30 px-4 py-3 transition-all hover:border-primary-200 dark:hover:border-primary-700 hover:bg-primary-50/50 dark:hover:bg-primary-900/20 hover:shadow-sm"
                >
                    <div class="flex items-center gap-3">
                        <div :class="['flex h-8 w-8 items-center justify-center rounded-lg transition-colors', iconColorClass(link.icon)]">
                            <i :class="link.icon" class="text-sm"></i>
                        </div>
                        <span class="text-sm font-medium text-surface-800 dark:text-surface-100 group-hover:text-primary-600 dark:group-hover:text-primary-400 transition-colors">{{ link.label }}</span>
                    </div>
                    <i class="pi pi-arrow-right text-xs text-surface-300 dark:text-surface-500 group-hover:text-primary-400 group-hover:translate-x-0.5 transition-all"></i>
                </RouterLink>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-8 text-center text-surface-400">
                <i class="pi pi-link text-2xl mb-2 opacity-40"></i>
                <p class="text-sm">Aucun raccourci disponible</p>
            </div>
        </div>
    </div>
</template>
