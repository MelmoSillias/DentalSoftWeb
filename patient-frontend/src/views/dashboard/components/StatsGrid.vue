<script setup>
import StatCard from '@/components/StatCard.vue'; 

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
        <StatCard v-for="item in stats" :key="item.key" :item="item" :loading="loading" :empty="empty" />  
    </section>
</template>

<style scoped>
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
