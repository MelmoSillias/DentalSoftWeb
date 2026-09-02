<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { useAuthStore } from '@/stores/auth';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import router from '@/router';
import { useRoute } from 'vue-router';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { useSmsTopbarCredits } from '@/composables/useSmsTopbarCredits';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import cabinetConfig from '@/cabinetConfig';

const { toggleDarkMode, isDarkTheme } = useLayout();
const { isLocalDeploymentMode } = useInternetFeatures();
const auth = useAuthStore();
const toast = useToast();
const route = useRoute();

const {
    showInTopbar: showSmsCredits,
    canOpenSmsSettings,
    displayUnits: smsDisplayUnits,
    overviewSuccess: smsOverviewSuccess,
    loading: smsCreditsLoading,
    refresh: refreshSmsCredits,
    startPolling: startSmsCreditsPolling,
    stopPolling: stopSmsCreditsPolling
} = useSmsTopbarCredits(
    () => auth.token,
    () => auth.user?.roles || []
);

const showProfilePopover = ref(false);
const profileButton = ref(null);
const profilePopover = ref(null);
const isLoggingOut = ref(false);

const isOnHub = computed(() => route.name === 'navigation-hub');

onMounted(async () => {
    if (auth.token && !auth.user) {
        try {
            await auth.fetchUser();
        } catch {
            // ignore
        }
    }
    if (auth.token) {
        startSmsCreditsPolling();
    }
});

onBeforeUnmount(() => {
    stopSmsCreditsPolling();
});

watch(
    () => route.name,
    (name) => {
        if (name === 'administration-api-sms' && auth.token) {
            refreshSmsCredits({ silent: true });
        }
    }
);

function goHome() {
    router.push({ name: 'navigation-hub' });
}

function openSmsSettings() {
    if (!canOpenSmsSettings.value) {
        return;
    }
    router.push({ name: 'administration-api-sms' });
}

function toggleProfilePopover(event) {
    if (showProfilePopover.value) {
        profilePopover.value.hide();
    } else {
        profilePopover.value.show(event);
    }
    showProfilePopover.value = !showProfilePopover.value;
}

async function handleLogout() {
    isLoggingOut.value = true;
    try {
        auth.logout();
        showProfilePopover.value = false;
        toast.add({
            severity: 'success',
            summary: 'Déconnexion réussie',
            detail: 'Vous avez été déconnecté.',
            life: 3000
        });
        router.push({ name: 'login' });
    } catch (_) {
        toast.add({
            severity: 'error',
            summary: 'Erreur de déconnexion',
            detail: "Une erreur s'est produite lors de la déconnexion.",
            life: 3000
        });
    } finally {
        isLoggingOut.value = false;
    }
}

function openProfile() {
    try {
        if (profilePopover?.value?.hide) {
            profilePopover.value.hide();
        }
    } catch (_) {
        // ignore
    }
    router.push({ name: 'profile' });
}
</script>

<template>
    <aside class="layout-right-rail" aria-label="Actions rapides">
        <div class="layout-right-rail__top">
            <button
                type="button"
                class="layout-right-rail__btn"
                :class="{ 'is-active': isOnHub }"
                title="Accueil"
                aria-label="Accueil"
                @click="goHome"
            >
                <i class="pi pi-th-large"></i>
            </button>
            <router-link
                to="/dashboard"
                class="layout-right-rail__btn layout-right-rail__logo"
                :title="cabinetConfig.brandName"
                :aria-label="cabinetConfig.brandName"
            >
                <img src="/logo.png" alt="" width="28" height="22" />
            </router-link>
            <span
                v-if="isLocalDeploymentMode"
                class="layout-right-rail__local"
                title="Mode local"
                aria-label="Mode local"
            >
                <i class="pi pi-wifi"></i>
            </span>
        </div>

        <div class="layout-right-rail__middle">
            <button
                v-if="showSmsCredits"
                type="button"
                class="layout-right-rail__btn"
                :class="{
                    'is-warn': !smsOverviewSuccess && !smsCreditsLoading,
                    'is-clickable': canOpenSmsSettings
                }"
                :title="canOpenSmsSettings ? `SMS · ${smsDisplayUnits}` : `SMS · ${smsDisplayUnits}`"
                :aria-label="`Crédits SMS: ${smsDisplayUnits}`"
                :disabled="!canOpenSmsSettings"
                @click="openSmsSettings"
            >
                <i class="pi pi-comment"></i>
            </button>

            <button
                type="button"
                class="layout-right-rail__btn"
                :title="isDarkTheme ? 'Mode clair' : 'Mode sombre'"
                :aria-label="isDarkTheme ? 'Mode clair' : 'Mode sombre'"
                @click="toggleDarkMode"
            >
                <i :class="['pi', isDarkTheme ? 'pi-sun' : 'pi-moon']"></i>
            </button>

            <NotificationBell variant="rail" popover-position="left" />
        </div>

        <div class="layout-right-rail__bottom">
            <button
                type="button"
                class="layout-right-rail__btn"
                title="Profil"
                aria-label="Profil"
                ref="profileButton"
                @click="toggleProfilePopover($event)"
            >
                <i class="pi pi-user"></i>
            </button>
            <Popover
                ref="profilePopover"
                v-model:visible="showProfilePopover"
                :autoHide="true"
                :dismissable="true"
                :target="profileButton"
                position="left"
                class="w-56 bg-white dark:bg-gray-800 shadow-lg rounded-lg p-3"
                style="z-index: 1000"
            >
                <p class="font-semibold text-sm text-gray-800 dark:text-gray-100 mb-3 truncate">
                    {{ auth.user?.username || 'Utilisateur' }}
                </p>
                <div class="space-y-2">
                    <Button
                        class="p-button-secondary p-button-sm w-full"
                        label="Profil"
                        icon="pi pi-user"
                        @click="openProfile"
                    />
                    <Button
                        :loading="isLoggingOut"
                        class="p-button-danger p-button-sm w-full"
                        label="Quitter"
                        icon="pi pi-sign-out"
                        @click="handleLogout"
                    />
                </div>
            </Popover>
        </div>
    </aside>
</template>

<style scoped>
.layout-right-rail {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    width: 3.75rem;
    padding: 0.75rem 0.35rem;
    background: var(--primary-color, #0ea5e9);
    color: #fff;
    border-left: 1px solid rgba(255, 255, 255, 0.12);
    z-index: 100;
}

.layout-right-rail__top,
.layout-right-rail__middle,
.layout-right-rail__bottom {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
}

.layout-right-rail__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    border: none;
    border-radius: 0.65rem;
    background: transparent;
    color: #fff;
    cursor: pointer;
    text-decoration: none;
    transition: background-color 0.15s ease;
}

.layout-right-rail__btn:hover,
.layout-right-rail__btn.is-active {
    background: rgba(255, 255, 255, 0.16);
    color: #fff;
}

.layout-right-rail__btn:disabled {
    opacity: 0.55;
    cursor: default;
}

.layout-right-rail__btn.is-warn {
    background: rgba(251, 191, 36, 0.25);
}

.layout-right-rail__logo img {
    display: block;
    object-fit: contain;
    filter: brightness(0) invert(1);
}

.layout-right-rail__local {
    display: inline-flex;
    width: 1.75rem;
    height: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    background: rgba(251, 191, 36, 0.35);
    font-size: 0.7rem;
}

.layout-right-rail__notif.has-unread {
    animation: rail-ring 2s ease-in-out infinite;
}

@keyframes rail-ring {
    0% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.55);
    }
    70% {
        box-shadow: 0 0 0 8px rgba(239, 68, 68, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}

@media (max-width: 991px) {
    .layout-right-rail {
        flex-direction: row;
        justify-content: space-around;
        width: 100%;
        height: 3.5rem;
        padding: 0.35rem 0.75rem;
        border-left: none;
        border-top: 1px solid rgba(255, 255, 255, 0.12);
    }

    .layout-right-rail__top,
    .layout-right-rail__middle,
    .layout-right-rail__bottom {
        flex-direction: row;
        gap: 0.25rem;
    }

    .layout-right-rail__local {
        display: none;
    }
}
</style>
