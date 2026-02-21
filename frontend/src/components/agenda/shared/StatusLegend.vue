<script setup>
import Tag from 'primevue/tag';
import { useRdvStatus } from '@/composables/useRdvStatus';

const { statusList } = useRdvStatus();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 rounded-full bg-surface-50 px-3 py-2 text-sm text-surface-700 dark:bg-surface-800 dark:text-surface-50">
        <div v-for="status in statusList" :key="status.key" class="flex items-center">
            <Tag :value="status.label" :severity="status.severity" rounded />
        </div>
    </div>
</template>

