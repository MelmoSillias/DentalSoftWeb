<script setup>
defineProps({
    stats: {
        type: Array,
        default: () => []
    }
});
</script>

<template>
    <section class="stats-grid">
        <PvCard v-for="item in stats" :key="item.key" class="stat-card">
            <template #title>{{ item.label }}</template>
            <template #content>
                <p class="value">{{ item.value }}</p>
                <small class="muted">{{ item.hint }}</small>
            </template>
        </PvCard>
    </section>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.value {
    margin: 0 0 0.2rem;
    font-size: 1.4rem;
    font-weight: 700;
}

@media (max-width: 420px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
