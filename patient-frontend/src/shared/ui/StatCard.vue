<script setup>
defineProps({
    item: {
        type: Object,
        default: () => ({}),
    },
    loading: {
        type: Boolean,
        default: false,
    },
});
</script>

<template>
    <div class="stat-card">
        <div
            class="stat-icon"
        >
            <i
                v-if="!loading"
                :class="item.icon"
            />
            <Skeleton v-else width="1.5rem" height="1.5rem" />
        </div>

        <div class="stat-body">
            <span class="stat-label" v-if="!loading">{{ item.label }}</span>
            <Skeleton v-else width="6rem" height="0.8rem" class="mb-1" />

            <p class="stat-value" v-if="!loading">{{ item.value }}</p>
            <Skeleton v-else width="4rem" height="1.4rem" class="mb-1" />

            <small class="stat-hint muted" v-if="!loading">{{
                item.hint
            }}</small>
            <Skeleton v-else width="5rem" height="0.7rem" />
        </div>
    </div>
</template>
