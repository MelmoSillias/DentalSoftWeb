<script setup>
defineProps({
    fields: {
        type: Array,
        default: () => []
    },
    columns: {
        type: Number,
        default: 2
    }
});

const formatValue = (value) => {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? 'Oui' : 'Non';
    return String(value);
};
</script>

<template>
    <div
        class="grid gap-3"
        :class="{
            'grid-cols-1': columns === 1,
            'grid-cols-1 md:grid-cols-2': columns === 2,
            'grid-cols-1 md:grid-cols-2 lg:grid-cols-3': columns === 3
        }"
    >
        <div v-for="field in fields" :key="field.label" class="p-3 rounded-lg bg-surface-0 dark:bg-surface-800 border border-surface-200/70 dark:border-surface-700/70">
            <div class="text-xs font-medium uppercase tracking-wide text-surface-500 dark:text-surface-400">
                {{ field.label }}
            </div>
            <div class="text-sm text-surface-800 dark:text-surface-200 mt-1 whitespace-pre-wrap break-words">
                {{ formatValue(field.value) }}
            </div>
        </div>
    </div>
</template>
