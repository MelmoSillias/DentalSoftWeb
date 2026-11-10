<script setup>
import Tag from 'primevue/tag';
import { useRdvStatus } from '@/composables/useRdvStatus';

const { statusList } = useRdvStatus();
defineProps({
    stats: { type: Object, default: null },
    vertical: { type: Boolean, default: false },
    loading: { type: Boolean, default: false }
});
</script>

<template>
    <div class="flex gap-2 bg-surface-50 px-3 py-2 text-sm text-surface-700 dark:bg-surface-800 dark:text-surface-50" :class="vertical ? 'flex-col rounded-xl' : 'flex-wrap items-center rounded-xl'" :aria-busy="loading">
        <div v-for="status in statusList" :key="status.key" class="flex items-center justify-between gap-3">
            <Tag :value="status.label" :severity="status.severity" rounded />
            <span v-if="stats" class="font-semibold tabular-nums">{{ loading ? '…' : (stats[status.key] ?? 0) }}</span>
        </div>
    </div>
</template>
