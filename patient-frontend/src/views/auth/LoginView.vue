<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import LoginForm from './components/LoginForm.vue';
import { usePortalSettingsStore } from '../../stores/portalSettings';
import cabinetConfig from '../../cabinetConfig';

const route = useRoute();
const router = useRouter();
const portalSettings = usePortalSettingsStore();

const isPortalClosed = computed(() => portalSettings.isPortalClosed);
const closedMessage = computed(() => portalSettings.data.patientPortalClosedMessage || 'Le portail patient est temporairement indisponible.');
const showcaseUrl = computed(() => portalSettings.data.cabinetShowcaseWebsiteUrl || '');

onMounted(async () => {
    await portalSettings.load();
});

function handleSuccess() {
    const redirect = route.query.redirect || '/dashboard';
    router.push(String(redirect));
}

function openShowcase() {
    if (!showcaseUrl.value) return;
    window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <main class="login-page">
        <section class="hero-card">
            <div class="hero-logo">
                <img
                    :src="'/' + (cabinetConfig.logo || 'logo.png')"
                    :alt="cabinetConfig.displayName"
                    class="logo-img"
                    @error="$event.target.style.display='none'"
                />
            </div>
            <div>
                <h1 class="hero-title">{{ cabinetConfig.displayName }}</h1>
                <p class="hero-sub">Votre espace patient — rendez-vous, consultations et paiements.</p>
            </div>
            <Message v-if="isPortalClosed" severity="warn" :closable="false" class="portal-closed-msg">
                {{ closedMessage }}
            </Message>
            <Button
                v-if="showcaseUrl"
                text
                icon="pi pi-globe"
                label="Visiter le site du cabinet"
                size="small"
                class="showcase-btn"
                @click="openShowcase"
            />
        </section>

        <LoginForm :disabled="isPortalClosed" @success="handleSuccess" />

        <p class="login-footer">Votre espace est sécurisé et chiffré.</p>
    </main>
</template>

<style scoped>
.login-page {
    min-height: 100dvh;
    display: grid;
    align-content: center;
    gap: 1rem;
    padding: 1.25rem 1rem;
    width: min(100%, 480px);
    margin: 0 auto;
}

.hero-card {
    background: var(--pp-gradient);
    border-radius: var(--pp-radius);
    padding: 1.75rem 1.5rem;
    display: grid;
    gap: 0.5rem;
    box-shadow: 0 4px 24px rgba(30,58,138,0.22);
}

.hero-logo {
    width: 4rem;
    height: 4rem;
    border-radius: 16px;
    background: rgb(255, 255, 255);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.25rem;
    overflow: hidden;
    padding: 0.25rem;
}

.logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    border-radius: 12px;
}

.hero-title {
    margin: 0;
    font-size: 1.45rem;
    font-weight: 800;
    color: #fff;
    letter-spacing: -0.02em;
}

.hero-sub {
    margin: 0.2rem 0 0;
    font-size: 0.88rem;
    color: rgba(255,255,255,0.75);
    line-height: 1.5;
}

.portal-closed-msg {
    margin-top: 0.5rem;
}

.showcase-btn {
    color: rgba(255,255,255,0.85) !important;
    margin-top: 0.25rem;
    width: fit-content;
    padding-left: 0 !important;
}

.login-footer {
    text-align: center;
    margin: 0;
    font-size: 0.75rem;
    color: var(--p-text-muted-color);
    opacity: 0.7;
}
</style>
