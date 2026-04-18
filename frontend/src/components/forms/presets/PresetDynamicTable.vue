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
            <i class="pi pi-table"></i>
            <strong>{{ props.section.title }}</strong>
        </div>
        <p class="preset-description">Table metier dynamique avec colonnes, lignes et resume.</p>
        <div class="preview-grid">
            <div class="preview-cell heading">Colonne A</div>
            <div class="preview-cell heading">Colonne B</div>
            <div class="preview-cell">Valeur</div>
            <div class="preview-cell">Valeur</div>
        </div>
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

.preview-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    border: 1px solid var(--surface-border);
    border-radius: 10px;
    overflow: hidden;
}

.preview-cell {
    padding: 0.4rem 0.5rem;
    border-bottom: 1px solid var(--surface-border);
    border-right: 1px solid var(--surface-border);
    font-size: 0.78rem;
}

.preview-cell:nth-child(2n) {
    border-right: none;
}

.preview-cell.heading {
    font-weight: 600;
    background: color-mix(in srgb, var(--surface-100), transparent 25%);
}
</style>
