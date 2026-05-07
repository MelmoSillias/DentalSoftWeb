<script setup>
const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    unreadCount: { type: Number, default: 0 }
});
</script>

<template>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <!-- Statut du compte -->
        <div class="relative overflow-hidden rounded-2xl border border-emerald-200/60 dark:border-emerald-800/40 bg-gradient-to-br from-emerald-50 to-emerald-100/40 dark:from-emerald-900/20 dark:to-emerald-800/10 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">Statut du compte</p>
                    <div class="mt-1.5 text-2xl font-bold text-surface-900 dark:text-surface-50 truncate">{{ stats.accountStatus || 'Actif' }}</div>
                    <small class="text-xs text-emerald-700/60 dark:text-emerald-400/60">Compte en bonne santé</small>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 dark:bg-emerald-900/40 shadow-sm">
                    <i class="pi pi-shield text-lg text-emerald-600 dark:text-emerald-400"></i>
                </div>
            </div>
            <div class="absolute -bottom-3 -right-3 h-16 w-16 rounded-full bg-emerald-100/60 dark:bg-emerald-900/20 pointer-events-none"></div>
        </div>

        <!-- Notifications -->
        <div class="relative overflow-hidden rounded-2xl border border-amber-200/60 dark:border-amber-800/40 bg-gradient-to-br from-amber-50 to-amber-100/40 dark:from-amber-900/20 dark:to-amber-800/10 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Notifications</p>
                    <div class="mt-1.5 text-3xl font-bold text-amber-600 dark:text-amber-400">{{ unreadCount }}</div>
                    <small class="text-xs text-amber-700/60 dark:text-amber-400/60">Non lue{{ unreadCount > 1 ? 's' : '' }}</small>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 dark:bg-amber-900/40 shadow-sm">
                    <i class="pi pi-bell text-lg text-amber-600 dark:text-amber-400"></i>
                </div>
            </div>
            <div class="absolute -bottom-3 -right-3 h-16 w-16 rounded-full bg-amber-100/60 dark:bg-amber-900/20 pointer-events-none"></div>
        </div>

        <!-- Activité récente -->
        <div class="relative overflow-hidden rounded-2xl border border-blue-200/60 dark:border-blue-800/40 bg-gradient-to-br from-blue-50 to-blue-100/40 dark:from-blue-900/20 dark:to-blue-800/10 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400">Activité récente</p>
                    <div class="mt-1.5 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ stats.activityCount || 0 }}</div>
                    <small class="text-xs text-blue-700/60 dark:text-blue-400/60">Connexions & actions</small>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900/40 shadow-sm">
                    <i class="pi pi-chart-line text-lg text-blue-600 dark:text-blue-400"></i>
                </div>
            </div>
            <div class="absolute -bottom-3 -right-3 h-16 w-16 rounded-full bg-blue-100/60 dark:bg-blue-900/20 pointer-events-none"></div>
        </div>

        <!-- Dernier accès -->
        <div class="relative overflow-hidden rounded-2xl border border-purple-200/60 dark:border-purple-800/40 bg-gradient-to-br from-purple-50 to-purple-100/40 dark:from-purple-900/20 dark:to-purple-800/10 p-5 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-purple-600 dark:text-purple-400">Dernier accès</p>
                    <div class="mt-1.5 text-lg font-bold text-purple-600 dark:text-purple-400 truncate">{{ stats.lastAccess || '--' }}</div>
                    <small class="text-xs text-purple-700/60 dark:text-purple-400/60">Surveillance temps réel</small>
                </div>
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-purple-100 dark:bg-purple-900/40 shadow-sm">
                    <i class="pi pi-clock text-lg text-purple-600 dark:text-purple-400"></i>
                </div>
            </div>
            <div class="absolute -bottom-3 -right-3 h-16 w-16 rounded-full bg-purple-100/60 dark:bg-purple-900/20 pointer-events-none"></div>
        </div>
    </div>
</template>
