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
            <i class="pi pi-images"></i>
            <strong>{{ props.section.title }}</strong>
        </div>
        <p class="preset-description">Zone de documents, radios et images cliniques.</p>
        <div class="gallery-placeholder">
            <div class="thumb"></div>
            <div class="thumb"></div>
            <div class="thumb"></div>
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

.gallery-placeholder {
    display: flex;
    gap: 0.4rem;
}

.thumb {
    width: 64px;
    height: 52px;
    border-radius: 10px;
    background: linear-gradient(135deg, var(--surface-200), var(--surface-100));
    border: 1px solid var(--surface-border);
}
</style>
