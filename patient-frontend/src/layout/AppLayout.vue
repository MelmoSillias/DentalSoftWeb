<script setup>
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { usePortalSettingsStore } from '../stores/portalSettings';
import { useTheme } from '../composables/useTheme';
import AppHeader from './AppHeader.vue';
import AppBottomNav from './AppBottomNav.vue';
import BrandMark from '../components/BrandMark.vue';
const router = useRouter();
const auth = useAuthStore();
const portal = usePortalSettingsStore();
const { isDark, initTheme, toggleTheme } = useTheme();
onMounted(initTheme);
const items = [
    { label: 'Vue d’ensemble', icon: 'pi pi-th-large', to: '/dashboard' },
    { label: 'Mes rendez-vous', icon: 'pi pi-calendar', to: '/rendez-vous' },
    {
        label: 'Mes consultations',
        icon: 'pi pi-file-edit',
        to: '/consultations',
    },
    { label: 'Mes paiements', icon: 'pi pi-wallet', to: '/paiements' },
    { label: 'Mes documents', icon: 'pi pi-folder-open', to: '/documents' },
];
function logout() {
    auth.logout();
    router.push('/login');
}
</script>
<template>
    <div class="app-shell">
        <aside class="sidebar">
            <RouterLink
                to="/dashboard"
                class="sidebar-brand"
                aria-label="Accueil"
                ><BrandMark
            /></RouterLink>
            <p class="sidebar-label">MON PORTAIL</p>
            <nav class="sidebar-nav" aria-label="Navigation principale">
                <RouterLink
                    v-for="item in items"
                    :key="item.to"
                    :to="item.to"
                    active-class="active"
                    ><i :class="item.icon" aria-hidden="true" /><span>{{
                        item.label
                    }}</span
                    ><i class="pi pi-angle-right nav-arrow" aria-hidden="true"
                /></RouterLink>
            </nav>
            <div class="sidebar-care">
                <span class="care-symbol"
                    ><i class="pi pi-heart" aria-hidden="true" /></span
                ><strong>À vos côtés,<br />à chaque sourire.</strong>
                <p>Votre suivi dentaire, en toute sérénité.</p>
                <a
                    v-if="portal.data.cabinetShowcaseWebsiteUrl"
                    :href="portal.data.cabinetShowcaseWebsiteUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    >Découvrir le cabinet <i class="pi pi-arrow-up-right"
                /></a>
            </div>
            <div class="sidebar-bottom">
                <RouterLink to="/profil" active-class="active"
                    ><i class="pi pi-user" />Mon profil</RouterLink
                ><button type="button" @click="logout">
                    <i class="pi pi-sign-out" />Se déconnecter</button
                ><span class="secure-note"
                    ><i class="pi pi-shield" />Espace personnel sécurisé</span
                >
            </div>
        </aside>
        <div class="workspace">
            <AppHeader :dark="isDark" @toggle-theme="toggleTheme" />
            <main id="main-content" class="app-content">
                <slot />
                <footer class="page-footer">
                    <span>Prenez soin de votre sourire.</span
                    ><span
                        ><i class="pi pi-shield" />Vos données restent
                        confidentielles</span
                    ><button
                        class="mobile-logout"
                        type="button"
                        @click="logout"
                    >
                        Se déconnecter
                    </button>
                </footer>
            </main>
        </div>
        <AppBottomNav />
    </div>
</template>
