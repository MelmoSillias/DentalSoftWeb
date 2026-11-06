<script setup>
import { computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import BrandMark from '../../components/BrandMark.vue';
import LoginForm from './components/LoginForm.vue';
import { usePortalSettingsStore } from '../../stores/portalSettings';
import cabinetConfig from '../../cabinetConfig';

const route = useRoute();
const router = useRouter();
const portalSettings = usePortalSettingsStore();
const isPortalClosed = computed(() => portalSettings.isPortalClosed);
const closedMessage = computed(() => portalSettings.data.patientPortalClosedMessage || 'Le portail patient est temporairement indisponible.');
const showcaseUrl = computed(() => portalSettings.data.cabinetShowcaseWebsiteUrl || '');

onMounted(() => portalSettings.load());

function handleSuccess() {
    router.push(String(route.query.redirect || '/dashboard'));
}

function openShowcase() {
    if (showcaseUrl.value) window.open(showcaseUrl.value, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <main id="main-content" class="login-page">
        <header class="login-topbar">
            <RouterLink to="/login" aria-label="Connexion"><BrandMark /></RouterLink>
            <span class="login-top-note"><i class="pi pi-lock" />Connexion sécurisée</span>
        </header>
        <div class="login-stage">
            <section class="login-story" aria-labelledby="login-story-title">
                <p class="story-kicker">Espace patient</p>
                <h1 id="login-story-title">Vos soins,<br /><span>au même endroit.</span></h1>
                <p class="story-description">Retrouvez vos rendez-vous, vos documents et les informations transmises par votre cabinet, quand vous en avez besoin.</p>
                <div class="story-note"><i class="pi pi-info-circle" /><span>Vous êtes au bon endroit pour reprendre votre parcours de soins.</span></div>
            </section>
            <section class="login-panel" aria-labelledby="login-panel-title">
                <div class="panel-welcome">
                    <span class="welcome-icon" aria-hidden="true"><i class="pi pi-user" /></span>
                    <p class="eyebrow">Ravi de vous revoir</p>
                    <h2 id="login-panel-title">Connectez-vous à votre espace.</h2>
                    <p>Utilisez les identifiants remis par votre cabinet.</p>
                </div>
                <Message v-if="isPortalClosed" severity="warn" :closable="false">{{ closedMessage }}</Message>
                <LoginForm :disabled="isPortalClosed" @success="handleSuccess" />
                <div class="login-help"><i class="pi pi-info-circle" aria-hidden="true" /><p>Première connexion ou identifiants oubliés ?<br /><strong>Contactez l’accueil de votre cabinet.</strong></p></div>
                <Button v-if="showcaseUrl" text icon="pi pi-arrow-up-right" label="Découvrir le cabinet" class="showcase-btn" @click="openShowcase" />
                <p class="login-confidential"><i class="pi pi-lock" />Accès réservé aux patients du cabinet</p>
            </section>
        </div>
        <footer class="login-footer"><span>© {{ new Date().getFullYear() }} {{ cabinetConfig.brandName || cabinetConfig.displayName }}</span><span>Un espace simple pour suivre vos soins.</span></footer>
    </main>
</template>
