<script setup>
const props = defineProps({
    activity: { type: Array, default: () => [] }
});

const iconColorClass = (item) => {
    const icon = item.icon || '';
    if (icon.includes('sign-in') || icon.includes('login')) return 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/40 dark:text-emerald-400';
    if (icon.includes('sign-out') || icon.includes('logout')) return 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400';
    if (icon.includes('shield') || icon.includes('lock')) return 'bg-purple-100 text-purple-600 dark:bg-purple-900/40 dark:text-purple-400';
    if (icon.includes('pencil') || icon.includes('edit')) return 'bg-blue-100 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400';
    return 'bg-primary-100 text-primary-600 dark:bg-primary-900/40 dark:text-primary-400';
};
</script>

<template>
    <div class="overflow-hidden rounded-2xl border border-surface-200/50 dark:border-surface-700/50 bg-surface-0 dark:bg-surface-800/80 shadow-sm">
        <!-- Header -->
        <div class="flex items-center justify-between px-5 py-4 border-b border-surface-100 dark:border-surface-700/60">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-slate-500 to-slate-600 shadow-sm">
                    <i class="pi pi-history text-white text-sm"></i>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-surface-400">Sécurité</p>
                    <h3 class="text-base font-semibold text-surface-900 dark:text-surface-50">Activité récente</h3>
                </div>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-surface-100 dark:bg-surface-700 px-2.5 py-1 text-xs font-medium text-surface-600 dark:text-surface-300">
                <i class="pi pi-calendar text-xs"></i>
                {{ activity.length }} événements
            </span>
        </div>

        <div class="p-5">
            <div v-if="activity.length" class="relative">
                <!-- Ligne verticale timeline -->
                <div class="absolute left-[19px] top-0 bottom-0 w-px bg-surface-100 dark:bg-surface-700/60"></div>

                <div class="space-y-4">
                    <div v-for="(item, idx) in activity" :key="idx" class="relative flex items-start gap-4">
                        <!-- Icône timeline -->
                        <div :class="['relative z-10 flex h-10 w-10 shrink-0 items-center justify-center rounded-full shadow-sm ring-2 ring-surface-0 dark:ring-surface-800', iconColorClass(item)]">
                            <i :class="item.icon || 'pi pi-info-circle'" class="text-sm"></i>
                        </div>

                        <!-- Contenu -->
                        <div class="flex-1 min-w-0 pb-4 last:pb-0 border-b border-surface-100 dark:border-surface-700/40 last:border-b-0">
                            <div class="flex items-start justify-between gap-2">
                                <p class="font-semibold text-sm text-surface-900 dark:text-surface-50 leading-snug">{{ item.title }}</p>
                                <span class="shrink-0 text-xs text-surface-400 dark:text-surface-500 whitespace-nowrap">{{ item.time || '--' }}</span>
                            </div>
                            <p class="text-sm text-surface-500 dark:text-surface-400 mt-0.5">{{ item.subtitle }}</p>
                            <div v-if="item.ip" class="mt-1 inline-flex items-center gap-1 rounded-md bg-surface-100 dark:bg-surface-700 px-2 py-0.5 text-xs text-surface-500">
                                <i class="pi pi-globe text-[10px]"></i>
                                {{ item.ip }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-10 text-center text-surface-400">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-surface-100 dark:bg-surface-700 mb-3">
                    <i class="pi pi-history text-2xl opacity-40"></i>
                </div>
                <p class="text-sm font-medium text-surface-500">Aucune activité enregistrée</p>
                <p class="text-xs text-surface-400 mt-1">Vos connexions apparaîtront ici</p>
            </div>
        </div>
    </div>
</template>
