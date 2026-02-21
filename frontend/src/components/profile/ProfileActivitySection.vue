<script setup>
const props = defineProps({
    activity: { type: Array, default: () => [] }
});
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 flex items-center justify-between">
            <div>
                <p class="text-xs uppercase tracking-wider text-surface-500">Sécurité</p>
                <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Activité récente</h3>
            </div>
            <span class="text-sm text-surface-500">{{ activity.length }} événements</span>
        </div>
        <div class="p-5">
            <div v-if="activity.length" class="space-y-4">
                <div v-for="(item, idx) in activity" :key="idx" class="flex items-start gap-3 border-b border-surface-100 dark:border-surface-700/60 pb-4 last:border-b-0">
                    <div class="w-10 h-10 rounded-full bg-primary-50 text-primary-600 flex items-center justify-center">
                        <i :class="item.icon || 'pi pi-info-circle'"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div class="font-medium text-surface-900 dark:text-surface-100">{{ item.title }}</div>
                            <div class="text-xs text-surface-500">{{ item.time || '--' }}</div>
                        </div>
                        <div class="text-sm text-surface-600 dark:text-surface-400">{{ item.subtitle }}</div>
                        <div v-if="item.ip" class="text-xs text-surface-500">IP: {{ item.ip }}</div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center text-surface-500 py-6">
                Aucune activité enregistrée.
            </div>
        </div>
    </div>
</template>
