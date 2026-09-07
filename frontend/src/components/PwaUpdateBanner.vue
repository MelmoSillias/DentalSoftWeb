<script setup>
import Button from 'primevue/button';
import { usePwaUpdate } from '@/composables/usePwaUpdate';

const { needRefresh, applyUpdate, dismissUpdate } = usePwaUpdate();
</script>

<template>
    <div v-if="needRefresh" class="pwa-update-banner" role="status" aria-live="polite">
        <div class="pwa-update-banner__content">
            <i class="pi pi-refresh pwa-update-banner__icon" aria-hidden="true" />
            <div class="pwa-update-banner__text">
                <strong>Nouvelle version disponible</strong>
                <span>Actualisez pour charger la dernière version de l’application.</span>
            </div>
            <div class="pwa-update-banner__actions">
                <Button label="Actualiser" size="small" severity="primary" @click="applyUpdate" />
                <Button label="Plus tard" size="small" text severity="secondary" @click="dismissUpdate" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.pwa-update-banner {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 11000;
    padding: 0.75rem 1rem;
    background: color-mix(in srgb, var(--p-primary-color, #4e73df) 12%, var(--p-surface-0, #ffffff));
    border-bottom: 1px solid color-mix(in srgb, var(--p-primary-color, #4e73df) 28%, transparent);
    box-shadow: 0 8px 24px color-mix(in srgb, #0f172a 12%, transparent);
}

.pwa-update-banner__content {
    max-width: 72rem;
    margin: 0 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem 1rem;
}

.pwa-update-banner__icon {
    color: var(--p-primary-color, #4e73df);
    font-size: 1.1rem;
}

.pwa-update-banner__text {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
    flex: 1 1 14rem;
    min-width: 0;
    color: var(--p-text-color, #1e293b);
    font-size: 0.9rem;
    line-height: 1.35;
}

.pwa-update-banner__text strong {
    font-weight: 600;
}

.pwa-update-banner__text span {
    opacity: 0.85;
}

.pwa-update-banner__actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
}

@media (max-width: 640px) {
    .pwa-update-banner {
        padding: 0.65rem 0.75rem;
    }

    .pwa-update-banner__actions {
        width: 100%;
    }

    .pwa-update-banner__actions :deep(.p-button) {
        flex: 1 1 auto;
    }
}
</style>
