<script setup>
defineProps({
    icon: { type: String, default: 'pi pi-search' },
    title: { type: String, required: true },
    description: { type: String, default: '' }
});
</script>

<template>
    <div class="vitrine-empty" role="status">
        <span class="vitrine-empty__icon" aria-hidden="true"><i :class="icon" /></span>
        <h3>{{ title }}</h3>
        <p v-if="description">{{ description }}</p>
        <slot />
    </div>
</template>

<style scoped>
.vitrine-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.625rem;
    padding: 3rem 1rem;
    text-align: center;
    min-width: 0;
}
.vitrine-empty__icon {
    display: grid;
    place-items: center;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: var(--page-section-radius);
    background: color-mix(in srgb, var(--p-primary-color) 8%, var(--surface-card));
    color: var(--p-primary-color);
    font-size: 1.25rem;
    margin-bottom: 0.25rem;
}
.vitrine-empty h3 {
    margin: 0;
    color: var(--text-color);
    font-size: var(--page-section-title-size);
    font-weight: 600;
}
.vitrine-empty p {
    margin: 0;
    max-width: 24rem;
    color: var(--text-color-secondary);
    line-height: 1.5;
}
</style>
