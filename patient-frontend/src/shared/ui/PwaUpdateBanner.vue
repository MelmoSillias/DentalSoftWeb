<script setup>
import Button from 'primevue/button';
import { usePwaUpdate } from '@/shared/composables/usePwaUpdate.js';

const { mandatory, needRefresh, installing, checking, errorMessage, applyUpdate, dismissUpdate, checkForUpdates } = usePwaUpdate();
</script>

<template>
    <main v-if="mandatory" class="pwa-required-update" aria-labelledby="pwa-required-title" aria-live="polite">
        <section class="pwa-required-update__card">
            <i class="pi pi-refresh" aria-hidden="true" />
            <h1 id="pwa-required-title">Mise à jour nécessaire</h1>
            <p>Cette version de votre espace patient ne peut plus être utilisée. Installez la mise à jour pour continuer.</p>
            <p v-if="!needRefresh">Téléchargement de la nouvelle version… La connexion Internet est nécessaire.</p>
            <p v-if="errorMessage" role="alert">{{ errorMessage }}</p>
            <Button v-if="needRefresh" label="Installer la mise à jour" icon="pi pi-download" :loading="installing" @click="applyUpdate" />
            <Button label="Réessayer" text :loading="checking" :disabled="installing" @click="checkForUpdates" />
            <p class="pwa-required-update__note">Votre session et vos préférences ne seront pas effacées. Les autres onglets du portail seront actualisés après installation.</p>
        </section>
    </main>
    <div v-else-if="needRefresh" class="pwa-update-banner" role="status" aria-live="polite">
        <div class="pwa-update-banner__content">
            <i class="pi pi-refresh pwa-update-banner__icon" aria-hidden="true" />
            <p class="pwa-update-banner__text">{{ errorMessage || 'Nouvelle version disponible' }}</p>
            <div class="pwa-update-banner__actions">
                <Button label="Actualiser" size="small" severity="primary" :loading="installing" @click="applyUpdate" />
                <Button icon="pi pi-times" size="small" text rounded severity="secondary" aria-label="Plus tard" @click="dismissUpdate" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.pwa-required-update {
    position: fixed;
    inset: 0;
    z-index: 2147483647;
    display: grid;
    place-items: center;
    overflow: auto;
    padding: 1.5rem;
    color: var(--p-text-color, #1e293b);
    background: var(--p-content-background, #ffffff);
}
.pwa-required-update__card {
    max-width: 34rem;
    text-align: center;
    padding: 2rem;
    border: 1px solid var(--p-content-border-color, #e2e8f0);
    border-radius: 1rem;
}
.pwa-required-update__card > i {
    font-size: 2rem;
    color: var(--p-primary-color);
}
.pwa-required-update__card h1 {
    font-size: 1.5rem;
}
.pwa-required-update__card p {
    margin: 1rem 0;
}
.pwa-required-update__note {
    font-size: 0.85rem;
    color: var(--p-text-muted-color);
}
.pwa-update-banner {
    position: fixed;
    bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px));
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

.app-dark .pwa-update-banner {
    background: var(--p-surface-800, #1e293b);
    border-color: color-mix(in srgb, var(--p-primary-color, #5ad6f5) 35%, var(--p-surface-600, #475569));
    box-shadow: 0 8px 24px color-mix(in srgb, #000 45%, transparent);
    color: var(--p-text-color, #f1f5f9);
}

.app-dark .pwa-update-banner__icon {
    color: var(--p-primary-color, #5ad6f5);
}

@media (max-width: 640px) {
    .pwa-update-banner {
        bottom: calc(5rem + env(safe-area-inset-bottom, 0px));
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
