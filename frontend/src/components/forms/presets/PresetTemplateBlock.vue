<script setup>
const props = defineProps({
    title: { type: String, default: '' },
    icon: { type: String, default: 'pi pi-box' },
    description: { type: String, default: '' },
    bullets: { type: Array, default: () => [] }
});
</script>

<template>
    <div class="preset-shell">
        <div class="preset-header">
            <i :class="props.icon"></i>
            <strong>{{ props.title }}</strong>
        </div>
        <p class="preset-description">{{ props.description }}</p>
        <ul v-if="props.bullets.length" class="preset-list">
            <li v-for="item in props.bullets" :key="item">{{ item }}</li>
        </ul>
    </div>
</template>

<style scoped>
.preset-shell {
    border: 1px dashed var(--surface-border);
    border-radius: 12px;
    padding: 0.85rem;
    background: color-mix(in srgb, var(--surface-0), var(--primary-color) 2%);
}

.preset-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.4rem;
}

.preset-description {
    margin: 0;
    font-size: 0.82rem;
    color: var(--text-color-secondary);
}

.preset-list {
    margin: 0.45rem 0 0;
    padding-left: 1rem;
    display: grid;
    gap: 0.2rem;
    font-size: 0.78rem;
    color: var(--text-color-secondary);
}
</style>
