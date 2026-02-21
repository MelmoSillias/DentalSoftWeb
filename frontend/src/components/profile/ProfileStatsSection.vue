<script setup>
const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    unreadCount: { type: Number, default: 0 }
});
</script>

<template>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 p-5">
            <p class="text-xs uppercase tracking-wider text-surface-500">Statut du compte</p>
            <div class="text-2xl font-semibold text-surface-900 dark:text-surface-100">{{ stats.accountStatus || 'Actif' }}</div>
            <small class="text-surface-500">Dernière mise à jour {{ stats.lastAccess || 'récente' }}</small>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 p-5">
            <p class="text-xs uppercase tracking-wider text-surface-500">Notifications</p>
            <div class="text-2xl font-semibold text-blue-600">{{ unreadCount }}</div>
            <small class="text-surface-500">Non lues</small>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 p-5">
            <p class="text-xs uppercase tracking-wider text-surface-500">Activité récente</p>
            <div class="text-2xl font-semibold text-emerald-600">{{ stats.activityCount || 0 }}</div>
            <small class="text-surface-500">Connexions & actions</small>
        </div>
        <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 p-5">
            <p class="text-xs uppercase tracking-wider text-surface-500">Dernier accès</p>
            <div class="text-2xl font-semibold text-amber-500">{{ stats.lastAccess || '--' }}</div>
            <small class="text-surface-500">Surveillance temps réel</small>
        </div>
    </div>
</template>
