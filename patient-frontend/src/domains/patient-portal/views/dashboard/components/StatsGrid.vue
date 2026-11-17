<script setup>
import StatCard from '@/shared/ui/StatCard.vue';

defineProps({
    stats: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <section class="stats-grid">
        <StatCard
            v-for="item in stats"
            :key="item.key"
            :item="item"
            :loading="loading"
        />
    </section>
</template>
