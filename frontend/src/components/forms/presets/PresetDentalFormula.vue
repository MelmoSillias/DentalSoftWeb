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
            <i class="pi pi-heart"></i>
            <strong>{{ props.section.title }}</strong>
        </div>
        <p class="preset-description">Module odontogramme, score de risque et notes cliniques.</p>
        <div class="preset-schema">Schema: odontogram(object) / riskScore(number) / notes(string)</div>
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
    font-size: 0.85rem;
    color: var(--text-color-secondary);
}

.preset-schema {
    margin-top: 0.55rem;
    font-size: 0.78rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    color: var(--text-color-secondary);
}
</style>
