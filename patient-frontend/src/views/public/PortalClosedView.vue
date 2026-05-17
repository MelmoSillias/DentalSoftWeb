<script setup>
import { computed, onMounted } from 'vue';
import { usePortalSettingsStore } from '../../stores/portalSettings';

const portalSettings = usePortalSettingsStore();
const closedMessage = computed(() => portalSettings.data.patientPortalClosedMessage || 'Le portail patient est temporairement indisponible.');
const showcaseUrl = computed(() => portalSettings.data.cabinetShowcaseWebsiteUrl || '');

onMounted(async () => {
    await portalSettings.load();
});

function openShowcase() {
    if (!showcaseUrl.value) return;
    window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <main class="public-page">
        <Card class="public-card">
            <template #title>Portail patient fermé</template>
            <template #content>
                <Message severity="warn" :closable="false">{{ closedMessage }}</Message>
                <p class="muted mt-3">Le cabinet continue de vous recevoir. Vous pouvez revenir plus tard ou nous contacter directement.</p>
                <Button
                    v-if="showcaseUrl"
                    class="mt-2"
                    label="Visiter le site du cabinet"
                    icon="pi pi-globe"
                    text
                    @click="openShowcase"
                />
            </template>
        </Card>
    </main>
</template>

<style scoped>
.public-page {
    min-height: 100dvh;
    display: grid;
    place-items: center;
    padding: 1rem;
}

.public-card {
    width: min(100%, 620px);
}
</style>
