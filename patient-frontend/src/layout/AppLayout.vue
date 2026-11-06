<script setup>
import { onMounted, ref, watch } from 'vue';
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
const isSidebarCollapsed = ref(localStorage.getItem('patient-sidebar-collapsed') === 'true');
const items = [
    { label: 'Vue d’ensemble', icon: 'pi pi-th-large', to: '/dashboard' },
    { label: 'Mes rendez-vous', icon: 'pi pi-calendar', to: '/rendez-vous' },
    { label: 'Mes consultations', icon: 'pi pi-file-edit', to: '/consultations' },
    { label: 'Mes paiements', icon: 'pi pi-wallet', to: '/paiements' },
    { label: 'Mes documents', icon: 'pi pi-folder-open', to: '/documents' },
];
onMounted(initTheme);
watch(isSidebarCollapsed, (value) => localStorage.setItem('patient-sidebar-collapsed', String(value)));
function logout() { auth.logout(); router.push('/login'); }
</script>
<template>
    <div class="app-shell" :class="{ 'sidebar-is-collapsed': isSidebarCollapsed }">
        <aside id="portal-sidebar" class="sidebar" aria-label="Menu du portail">
            <div class="sidebar-head">
                <RouterLink to="/dashboard" class="sidebar-brand" aria-label="Accueil"><BrandMark /></RouterLink>
                <button class="sidebar-toggle" type="button" :aria-label="isSidebarCollapsed ? 'Déployer la navigation' : 'Rétracter la navigation'" :aria-expanded="!isSidebarCollapsed" aria-controls="portal-navigation" :title="isSidebarCollapsed ? 'Déployer le menu' : 'Rétracter le menu'" @click="isSidebarCollapsed = !isSidebarCollapsed">
                    <i class="pi pi-angle-left" aria-hidden="true" />
                </button>
            </div>
            <nav id="portal-navigation" class="sidebar-nav mt-4" aria-label="Navigation principale">
                <RouterLink v-for="item in items" :key="item.to" :to="item.to" active-class="active" :title="isSidebarCollapsed ? item.label : undefined">
                    <i :class="item.icon" aria-hidden="true" /><span class="sidebar-item-label">{{ item.label }}</span>
                </RouterLink>
            </nav>
            <div class="sidebar-care" :inert="isSidebarCollapsed">
                <span class="care-symbol"><i class="pi pi-heart" aria-hidden="true" /></span>
                <strong>À vos côtés,<br />à chaque sourire.</strong>
                <p>Votre suivi dentaire, en toute sérénité.</p>
                <a v-if="portal.data.cabinetShowcaseWebsiteUrl" :href="portal.data.cabinetShowcaseWebsiteUrl" target="_blank" rel="noopener noreferrer">Découvrir le cabinet <i class="pi pi-arrow-up-right" /></a>
            </div>
            <div class="sidebar-bottom">
                <RouterLink to="/profil" active-class="active" title="Mon profil"><i class="pi pi-user" aria-hidden="true" /><span class="sidebar-item-label">Mon profil</span></RouterLink>
                <button type="button" title="Se déconnecter" @click="logout"><i class="pi pi-sign-out" aria-hidden="true" /><span class="sidebar-item-label">Se déconnecter</span></button>
                <button class="sidebar-theme" type="button" :aria-pressed="isDark" :title="isDark ? 'Thème clair' : 'Thème sombre'" @click="toggleTheme"><i :class="isDark ? 'pi pi-sun' : 'pi pi-moon'" aria-hidden="true" /><span class="sidebar-item-label">{{ isDark ? 'Thème clair' : 'Thème sombre' }}</span><span class="theme-switch" aria-hidden="true" /></button>
                <span class="secure-note"><i class="pi pi-shield" />Espace personnel sécurisé</span>
            </div>
        </aside>
        <div class="workspace">
            <AppHeader :dark="isDark" @toggle-theme="toggleTheme" />
            <main id="main-content" class="app-content"><slot /><footer class="page-footer"><span>Prenez soin de votre sourire.</span><span><i class="pi pi-shield" />Vos données restent confidentielles</span><button class="mobile-logout" type="button" @click="logout">Se déconnecter</button></footer></main>
        </div>
        <AppBottomNav />
    </div>
</template>
