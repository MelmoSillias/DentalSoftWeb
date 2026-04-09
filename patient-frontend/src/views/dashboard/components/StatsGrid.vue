<script setup>
defineProps({
    stats: {
        type: Array,
        default: () => []
    },
    loading: {
        type: Boolean,
        default: false
    },
    empty: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <section class="stats-grid">
        <template v-if="loading">
            <PvCard v-for="n in 4" :key="`stats-skeleton-${n}`" class="stat-card">
                <template #title>
                    <PvSkeleton width="9rem" height="1rem" />
                </template>
                <template #content>
                    <PvSkeleton width="6rem" height="1.5rem" class="mb-2" />
                    <PvSkeleton width="8rem" height="0.8rem" />
                </template>
            </PvCard>
        </template>

        <PvCard v-else-if="empty" class="stats-empty-card">
            <template #title>Statistiques</template>
            <template #content>
                <p class="muted">Aucune statistique disponible pour le moment.</p>
            </template>
        </PvCard>

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

.stats-empty-card {
    grid-column: 1 / -1;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

@media (max-width: 420px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>
