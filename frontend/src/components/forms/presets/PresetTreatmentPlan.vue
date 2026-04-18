<script setup>
const props = defineProps({
    section: {
        type: Object,
        required: true
    }
});
</script>

<template>
    <div class="preset-shell">
        <div class="preset-header">
            <i class="pi pi-sitemap"></i>
            <strong>{{ props.section.title }}</strong>
        </div>
        <p class="preset-description">Etapes de traitement, chronologie et cout estime.</p>
        <ul class="timeline">
            <li>Diagnostic</li>
            <li>Intervention</li>
            <li>Controle</li>
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
    margin: 0 0 0.6rem;
    font-size: 0.85rem;
    color: var(--text-color-secondary);
}

.timeline {
    margin: 0;
    padding-left: 1rem;
    display: grid;
    gap: 0.2rem;
    font-size: 0.8rem;
    color: var(--text-color-secondary);
}
</style>
