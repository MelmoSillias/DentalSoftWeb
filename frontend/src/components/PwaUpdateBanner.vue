<script setup>
import Button from 'primevue/button';
import { usePwaUpdate } from '@/composables/usePwaUpdate';

const { needRefresh, applyUpdate, dismissUpdate } = usePwaUpdate();
</script>

<template>
    <div v-if="needRefresh" class="pwa-update-banner" role="status" aria-live="polite">
        <div class="pwa-update-banner__content">
            <i class="pi pi-refresh pwa-update-banner__icon" aria-hidden="true" />
            <p class="pwa-update-banner__text">Nouvelle version disponible</p>
            <div class="pwa-update-banner__actions">
                <Button label="Actualiser" size="small" severity="primary" @click="applyUpdate" />
                <Button icon="pi pi-times" size="small" text rounded severity="secondary" aria-label="Plus tard" @click="dismissUpdate" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.pwa-update-banner {
    position: fixed;
    bottom: 0.75rem;
    left: 50%;
    transform: translateX(-50%);
    z-index: 11000;
    width: max-content;
    max-width: calc(100vw - 1.5rem);
    padding: 0.4rem 0.5rem 0.4rem 0.75rem;
    border-radius: 0.65rem;
    background: var(--p-surface-0, #ffffff);
    border: 1px solid color-mix(in srgb, var(--p-primary-color, #4e73df) 28%, var(--p-surface-200, #e2e8f0));
    box-shadow: 0 6px 20px color-mix(in srgb, #0f172a 14%, transparent);
    color: var(--p-text-color, #1e293b);
}

.pwa-update-banner__content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.pwa-update-banner__icon {
    color: var(--p-primary-color, #4e73df);
    font-size: 0.85rem;
    flex-shrink: 0;
}

.pwa-update-banner__text {
    margin: 0;
    font-size: 0.8rem;
    font-weight: 600;
    line-height: 1.2;
    white-space: nowrap;
}

.pwa-update-banner__actions {
    display: flex;
    align-items: center;
    gap: 0.15rem;
    flex-shrink: 0;
}

.pwa-update-banner__actions :deep(.p-button) {
    font-size: 0.75rem;
}

.pwa-update-banner__actions :deep(.p-button-sm) {
    padding: 0.25rem 0.55rem;
}

:global(.app-dark) .pwa-update-banner {
    background: var(--p-surface-800, #1e293b);
    border-color: color-mix(in srgb, var(--p-primary-color, #5ad6f5) 35%, var(--p-surface-600, #475569));
    box-shadow: 0 8px 24px color-mix(in srgb, #000 45%, transparent);
    color: var(--p-text-color, #f1f5f9);
}

:global(.app-dark) .pwa-update-banner__icon {
    color: var(--p-primary-color, #5ad6f5);
}

@media (max-width: 640px) {
    .pwa-update-banner {
        bottom: 0.5rem;
        width: calc(100vw - 1rem);
        max-width: none;
    }

    .pwa-update-banner__text {
        white-space: normal;
        flex: 1 1 auto;
        min-width: 0;
    }
}
</style>
