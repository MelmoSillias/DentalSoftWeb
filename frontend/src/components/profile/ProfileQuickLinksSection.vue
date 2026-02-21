<script setup>
const props = defineProps({
    links: { type: Array, default: () => [] }
});
</script>

<template>
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden">
        <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Accès rapides</h3>
        </div>
        <div class="p-5">
            <div v-if="links.length" class="space-y-3">
                <a v-for="link in links" :key="link.label" :href="link.to" class="flex items-center justify-between p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-primary-300/50 transition">
                    <div class="flex items-center gap-3">
                        <i :class="link.icon" class="text-primary-500"></i>
                        <span class="font-medium text-surface-900 dark:text-surface-100">{{ link.label }}</span>
                    </div>
                    <i class="pi pi-arrow-right text-surface-400"></i>
                </a>
            </div>
            <div v-else class="text-sm text-surface-500">Aucun raccourci disponible.</div>
        </div>
    </div>
</template>
