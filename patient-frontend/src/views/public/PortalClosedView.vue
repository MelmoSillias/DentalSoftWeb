<script setup>
import BrandMark from '../../components/BrandMark.vue';
import { computed, onMounted } from 'vue';
import { usePortalSettingsStore } from '../../stores/portalSettings';

const portalSettings = usePortalSettingsStore();
const closedMessage = computed(
    () =>
        portalSettings.data.patientPortalClosedMessage ||
        'Le portail patient est temporairement indisponible.',
);
const showcaseUrl = computed(
    () => portalSettings.data.cabinetShowcaseWebsiteUrl || '',
);

onMounted(async () => {
    await portalSettings.load();
});

function openShowcase() {
    if (!showcaseUrl.value) return;
    window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <main id="main-content" class="public-page">
        <Card class="public-card">
            <template #title>Portail patient fermé</template>
            <template #content
                ><div class="public-heading">
                    <BrandMark /><span class="welcome-icon"
                        ><i class="pi pi-clock"
                    /></span>
                    <h1>Nous serons bientôt de retour.</h1>
                </div>
                <Message severity="warn" :closable="false">{{
                    closedMessage
                }}</Message>
                <p class="muted mt-3">
                    Le cabinet continue de vous recevoir. Vous pouvez revenir
                    plus tard ou nous contacter directement.
                </p>
                <Button
                    v-if="showcaseUrl"
                    class="mt-2"
                    label="Visiter le site du cabinet"
                    icon="pi pi-globe"
                    text
                    @click="openShowcase"
                />
                <RouterLink
                    to="/login"
                    class="hero-link"
                    style="margin-top: 24px; background: var(--pp-soft)"
                    ><i class="pi pi-arrow-left" />Retour à la
                    connexion</RouterLink
                ></template
            >
        </Card>
    </main>
</template>
