<script setup>

import { useTheme } from '@/composables/useTheme.js'

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
    <Card class="stat-card" :style="{ background: `var(--p-${item.color}-50)`, border: `1px solid var(--p-${item.color}-200)` }" >
        <template #content>
            <div class="stat-card-inner">
                
                <!-- Texte -->
                <div class="stat-text">
                    <span class="title" v-if="!loading">
                        {{ item.label }}
                    </span>
                    <Skeleton v-else width="9rem" height="1rem" />

                    <p class="value" v-if="!loading">
                        {{ item.value }}
                    </p>
                    <Skeleton v-else width="6rem" height="1.5rem" />

                    <small class="muted" v-if="!loading">
                        {{ item.hint }}
                    </small>
                    <Skeleton v-else width="8rem" height="0.8rem" />
                </div>

                <!-- Icon -->
                <div class="stat-icon">
                    <i
                        v-if="!loading"
                        :class="item.icon"
                        :style="{ color: `var(--p-${item.color}-500)` }"
                    />
                    <Skeleton v-else width="2rem" height="2rem" circle />
                </div>

            </div>
        </template>
    </Card>
</template>

<style scoped>
.stat-card-inner {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.75rem;
    align-items: center;
}

.stat-text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.title {
    font-weight: 500;
}

.value {
    font-size: 1.5rem;
    font-weight: 600;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 1.8rem;
}
</style>