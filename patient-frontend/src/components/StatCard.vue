<script setup>
defineProps({
    item: {
        type: Object,
        default: () => ({})
    },
    loading: {
        type: Boolean,
        default: false
    }
});
</script>

<template>
    <div class="stat-card">
        <div class="stat-icon" :style="{ background: `var(--p-${item.color}-100)` }">
            <i
                v-if="!loading"
                :class="item.icon"
                :style="{ color: `var(--p-${item.color}-600)` }"
            />
            <Skeleton v-else width="1.5rem" height="1.5rem" />
        </div>

        <div class="stat-body">
            <span class="stat-label" v-if="!loading">{{ item.label }}</span>
            <Skeleton v-else width="6rem" height="0.8rem" class="mb-1" />

            <p class="stat-value" v-if="!loading">{{ item.value }}</p>
            <Skeleton v-else width="4rem" height="1.4rem" class="mb-1" />

            <small class="stat-hint muted" v-if="!loading">{{ item.hint }}</small>
            <Skeleton v-else width="5rem" height="0.7rem" />
        </div>
    </div>
</template>

<style scoped>
.stat-card {
    background: #fff;
    border-radius: var(--pp-radius);
    box-shadow: var(--pp-shadow-sm);
    border: 1px solid rgba(0,0,0,0.04);
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.85rem;
    transition: box-shadow 0.2s ease, transform 0.18s ease;
}

.stat-card:hover {
    box-shadow: var(--pp-shadow-md);
    transform: translateY(-2px);
}

.stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon i {
    font-size: 1.3rem;
}

.stat-body {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    min-width: 0;
}

.stat-label {
    font-size: 0.78rem;
    font-weight: 500;
    color: var(--p-text-muted-color);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.stat-value {
    margin: 0;
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.2;
    color: var(--p-text-color);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.stat-hint {
    font-size: 0.72rem;
}

.mb-1 {
    margin-bottom: 0.25rem;
}
</style>