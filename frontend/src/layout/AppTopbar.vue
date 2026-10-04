<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { useAuthStore } from '@/stores/auth';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import router from '@/router';
import { useRoute } from 'vue-router';
import { getTaskMenuItemsForRoute, isGuidedTourRoute, requestGuidedTourStart } from '@/tours';
import cabinetConfig from '@/cabinetConfig';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { useSmsTopbarCredits } from '@/composables/useSmsTopbarCredits';
import NotificationBell from '@/components/notifications/NotificationBell.vue';
import Tag from 'primevue/tag';

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const { isLocalDeploymentMode } = useInternetFeatures();
const auth = useAuthStore();
const toast = useToast();
const route = useRoute();

const {
    showInTopbar: showSmsCredits,
    canOpenSmsSettings,
    providerLabel: smsProviderLabel,
    displayUnits: smsDisplayUnits,
    displayExpiration: smsDisplayExpiration,
    overviewSuccess: smsOverviewSuccess,
    loading: smsCreditsLoading,
    refresh: refreshSmsCredits,
    startPolling: startSmsCreditsPolling,
    stopPolling: stopSmsCreditsPolling
} = useSmsTopbarCredits(
    () => auth.token,
    () => auth.user?.roles || []
);
const currentTime = ref('');
const currentDate = ref('');
const showProfilePopover = ref(false);
const profileButton = ref(null);
const profilePopover = ref(null);
const isLoggingOut = ref(false);
const showHelpPopover = ref(false);
const helpButton = ref(null);
const helpPopover = ref(null);

const isGuidedTourAvailable = computed(() => isGuidedTourRoute(route.name));
const guidedTourMenuItems = computed(() => {
    if (!isGuidedTourAvailable.value) {
        return [];
    }

    return getTaskMenuItemsForRoute(route.name, { roles: auth.roles || auth.user?.roles || [] });
});

const profileDisplayName = computed(() => auth.user?.username || 'Utilisateur');
const profileRoleLabel = computed(() => {
    const roles = auth.user?.roles || auth.roles || [];
    if (roles.includes('ROLE_ADMIN')) return 'Administrateur';
    if (roles.includes('ROLE_MEDECIN')) return 'Médecin';
    if (roles.includes('ROLE_RECEPTION') || roles.includes('ROLE_RECEPTIONNISTE')) return 'Réception';
    return auth.user?.role || 'Utilisateur';
});
const profileInitials = computed(() => {
    const name = String(profileDisplayName.value || '').trim();
    if (!name) return 'U';
    const parts = name.split(/[\s._-]+/).filter(Boolean);
    if (parts.length >= 2) {
        return `${parts[0][0]}${parts[1][0]}`.toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
});

function updateDateTime() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
    currentDate.value = now.toLocaleDateString('fr-FR', { weekday: 'short', day: 'numeric', month: 'short' });
}

let timer;
onMounted(async () => {
    updateDateTime();
    timer = setInterval(updateDateTime, 1000);
    if (auth.token && !auth.user) {
        try {
            await auth.fetchUser();
        } catch {
            // ignore — notifications / SMS topbar gérés ci-dessous
        }
    }
    if (auth.token) {
        startSmsCreditsPolling();
    }
});
onBeforeUnmount(() => {
    clearInterval(timer);
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

const smsCreditsTooltip = computed(() => {
    const units = smsDisplayUnits.value ?? '—';
    const expiration = smsDisplayExpiration.value ?? '—';
    return `${smsProviderLabel.value} — Restants: ${units} — Expiration: ${expiration}`;
});

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
        showProfilePopover.value = false; // Close Popover
        toast.add({
            severity: 'success',
            summary: 'Déconnexion réussie',
            detail: 'Vous avez été déconnecté.',
            life: 3000
        });

        router.push({ name: 'login' });
    } catch (err) {
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
    // hide popover if available, then navigate
    try {
        if (profilePopover.value && profilePopover.value && typeof profilePopover.value.hide === 'function') {
            profilePopover.value.hide();
        }
    } catch (e) {
        // ignore
    }
    router.push({ name: 'profile' });
}

function openManual() {
    profilePopover.value?.hide?.();
    router.push({ name: 'manual' });
}

function toggleHelpPopover(event) {
    if (!isGuidedTourAvailable.value) {
        toast.add({
            severity: 'info',
            summary: 'Aide guidee',
            detail: 'Aucun tour n est encore disponible sur cette page.',
            life: 2500
        });
        return;
    }

    if (showHelpPopover.value) {
        helpPopover.value?.hide?.();
    } else {
        helpPopover.value?.show?.(event);
    }
    showHelpPopover.value = !showHelpPopover.value;
}

function closeHelpPopover() {
    showHelpPopover.value = false;
    helpPopover.value?.hide?.();
}

function handleStartGuidedTourTask(taskId, variantId = null) {
    closeHelpPopover();
    requestGuidedTourStart(route.name, { taskId, variantId });
}
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button type="button" class="layout-menu-button layout-topbar-action" aria-label="Ouvrir le menu" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/" class="layout-topbar-logo" :aria-label="cabinetConfig.brandName">
                <div class="layout-topbar-logo-mark">
                    <img src="/logo.png" class="app-logo" width="40" height="40" :alt="cabinetConfig.brandName" />
                </div>
                <div class="layout-topbar-logo-text">
                    <span class="layout-topbar-logo-title">{{ cabinetConfig.brandName }}</span>
                    <span v-if="cabinetConfig.brandSubtitle" class="layout-topbar-logo-subtitle">{{ cabinetConfig.brandSubtitle }}</span>
                </div>
            </router-link>
            <Tag v-if="isLocalDeploymentMode" value="Mode local" severity="warn" class="local-mode-tag" role="status" />
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-topbar-datetime" aria-live="polite">
                <span class="layout-topbar-datetime-time">{{ currentTime }}</span>
                <span class="layout-topbar-datetime-date">{{ currentDate }}</span>
            </div>

            <div class="layout-topbar-divider hidden xl:block" aria-hidden="true"></div>

            <div class="layout-config-menu">
                <button
                    v-if="showSmsCredits && canOpenSmsSettings"
                    type="button"
                    class="sms-credits-widget sms-credits-widget--clickable"
                    :class="{ 'sms-credits-widget--warn': !smsOverviewSuccess && !smsCreditsLoading }"
                    :title="smsCreditsTooltip"
                    :aria-label="smsCreditsTooltip"
                    @click="openSmsSettings"
                >
                    <div class="sms-credits-widget__icon" aria-hidden="true">
                        <i class="pi pi-comment"></i>
                    </div>
                    <div class="sms-credits-widget__content">
                        <span class="sms-credits-widget__provider">{{ smsProviderLabel }}</span>
                        <div class="sms-credits-widget__metrics">
                            <div class="sms-credits-widget__metric sms-credits-widget__metric--units">
                                <span class="sms-credits-widget__metric-label">Restants</span>
                                <span class="sms-credits-widget__metric-value">{{ smsDisplayUnits }}</span>
                            </div>
                            <div class="sms-credits-widget__metric sms-credits-widget__metric--expiration">
                                <span class="sms-credits-widget__metric-label">Expiration</span>
                                <span class="sms-credits-widget__metric-value sms-credits-widget__metric-value--date">{{ smsDisplayExpiration }}</span>
                            </div>
                        </div>
                    </div>
                </button>
                <div
                    v-else-if="showSmsCredits"
                    class="sms-credits-widget"
                    :class="{ 'sms-credits-widget--warn': !smsOverviewSuccess && !smsCreditsLoading }"
                    role="status"
                    aria-live="polite"
                    :title="smsCreditsTooltip"
                    :aria-label="smsCreditsTooltip"
                >
                    <div class="sms-credits-widget__icon" aria-hidden="true">
                        <i class="pi pi-comment"></i>
                    </div>
                    <div class="sms-credits-widget__content">
                        <span class="sms-credits-widget__provider">{{ smsProviderLabel }}</span>
                        <div class="sms-credits-widget__metrics">
                            <div class="sms-credits-widget__metric sms-credits-widget__metric--units">
                                <span class="sms-credits-widget__metric-label">Restants</span>
                                <span class="sms-credits-widget__metric-value">{{ smsDisplayUnits }}</span>
                            </div>
                            <div class="sms-credits-widget__metric sms-credits-widget__metric--expiration">
                                <span class="sms-credits-widget__metric-label">Expiration</span>
                                <span class="sms-credits-widget__metric-value sms-credits-widget__metric-value--date">{{ smsDisplayExpiration }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <button
                    type="button"
                    class="layout-topbar-action layout-topbar-action-desktop-only"
                    :title="isDarkTheme ? 'Mode clair' : 'Mode sombre'"
                    :aria-label="isDarkTheme ? 'Passer en mode clair' : 'Passer en mode sombre'"
                    @click="toggleDarkMode"
                >
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <button
                    type="button"
                    class="layout-topbar-action layout-topbar-action-desktop-only"
                    :class="{ 'layout-topbar-action-disabled': !isGuidedTourAvailable }"
                    ref="helpButton"
                    :aria-disabled="!isGuidedTourAvailable"
                    title="Aide guidée"
                    aria-label="Aide guidée"
                    @click="toggleHelpPopover($event)"
                >
                    <i class="pi pi-question-circle"></i>
                </button>
                <Popover
                    ref="helpPopover"
                    v-model:visible="showHelpPopover"
                    :autoHide="true"
                    :dismissable="true"
                    :target="helpButton"
                    position="bottom"
                    class="w-[22rem] max-w-[90vw] bg-surface-0 dark:bg-surface-900 shadow-md rounded-lg border border-surface-200 dark:border-surface-700 p-0 overflow-hidden"
                    style="z-index: 1000"
                >
                    <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-question-circle text-primary-500"></i>
                            <span class="font-semibold text-surface-900 dark:text-surface-50">Aide guidée</span>
                        </div>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">Choisissez une action à découvrir sur cette page.</p>
                    </div>
                    <div class="p-2 space-y-1 max-h-[24rem] overflow-y-auto">
                        <button
                            v-for="item in guidedTourMenuItems"
                            :key="`${item.taskId}:${item.variantId || 'default'}`"
                            type="button"
                            class="w-full text-left p-3 rounded-md border border-transparent hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                            @click="handleStartGuidedTourTask(item.taskId, item.variantId)"
                        >
                            <div class="flex items-start gap-3">
                                <i :class="[item.icon, 'mt-0.5 text-primary-500']"></i>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-surface-800 dark:text-surface-100 leading-5">
                                        {{ item.label }}
                                    </p>
                                    <p v-if="item.description" class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </button>
                    </div>
                </Popover>
                <NotificationBell variant="topbar" popover-position="bottom" />
            </div>

            <button
                type="button"
                class="layout-topbar-menu-button layout-topbar-action"
                aria-label="Plus d'actions"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button
                        type="button"
                        class="layout-topbar-action layout-topbar-action-mobile-only"
                        :aria-label="isDarkTheme ? 'Passer en mode clair' : 'Passer en mode sombre'"
                        @click="toggleDarkMode"
                    >
                        <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                        <span>{{ isDarkTheme ? 'Mode clair' : 'Mode sombre' }}</span>
                    </button>
                    <button
                        type="button"
                        class="layout-topbar-action layout-topbar-action-mobile-only"
                        :class="{ 'layout-topbar-action-disabled': !isGuidedTourAvailable }"
                        :aria-disabled="!isGuidedTourAvailable"
                        aria-label="Aide guidée"
                        @click="toggleHelpPopover($event)"
                    >
                        <i class="pi pi-question-circle"></i>
                        <span>Aide guidée</span>
                    </button>
                    <div class="relative">
                        <button type="button" class="layout-topbar-profile" ref="profileButton" aria-label="Menu profil" @click="toggleProfilePopover">
                            <span class="layout-topbar-profile-avatar" aria-hidden="true">{{ profileInitials }}</span>
                            <span class="layout-topbar-profile-meta">
                                <span class="layout-topbar-profile-name">{{ profileDisplayName }}</span>
                                <span class="layout-topbar-profile-role">{{ profileRoleLabel }}</span>
                            </span>
                            <i class="pi pi-chevron-down layout-topbar-profile-caret" aria-hidden="true"></i>
                        </button>
                        <Popover
                            ref="profilePopover"
                            v-model:visible="showProfilePopover"
                            :autoHide="true"
                            :dismissable="true"
                            :target="profileButton"
                            position="bottom"
                            class="w-72 max-w-[90vw] bg-surface-0 dark:bg-surface-900 shadow-md rounded-lg border border-surface-200 dark:border-surface-700 p-0 overflow-hidden"
                            style="z-index: 1000"
                        >
                            <div class="px-4 py-3 border-b border-surface-200 dark:border-surface-700">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-md bg-primary-500 text-sm font-bold text-white">
                                        {{ profileInitials }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-surface-900 dark:text-surface-50 truncate">
                                            {{ profileDisplayName }}
                                        </p>
                                        <p class="text-xs text-surface-500 dark:text-surface-400 capitalize truncate">
                                            {{ profileRoleLabel }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="p-3 space-y-2">
                                <Button class="p-button-secondary p-button-sm w-full" label="Mon profil" icon="pi pi-user" iconPos="left" @click="openProfile" />
                                <Button class="p-button-secondary p-button-sm w-full" label="Manuel d'utilisation" icon="pi pi-book" iconPos="left" @click="openManual" />
                                <Button :loading="isLoggingOut" class="p-button-danger p-button-sm w-full" label="Déconnexion" icon="pi pi-sign-out" iconPos="left" @click="handleLogout" />
                            </div>
                        </Popover>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.local-mode-tag {
    flex-shrink: 0;
    font-size: 0.7rem;
    font-weight: 600;
}

.layout-topbar-action-disabled {
    opacity: 0.55;
}

:deep(.p-button.p-button-danger) {
    background-color: #ef4444;
    border-color: #ef4444;
}

:deep(.p-button.p-button-danger:hover) {
    background-color: #dc2626;
    border-color: #dc2626;
}

.sms-credits-widget {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.25rem 0.5rem;
    max-height: 2.25rem;
    border-radius: 0.375rem;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.18);
    color: #fff;
    text-align: left;
    flex-shrink: 0;
}

.sms-credits-widget--clickable {
    cursor: pointer;
    font: inherit;
    appearance: none;
    transition: background-color 0.15s ease;
}

.sms-credits-widget--clickable:hover {
    background: rgba(255, 255, 255, 0.18);
}

.sms-credits-widget--warn {
    border-color: rgba(251, 191, 36, 0.55);
    background: rgba(251, 191, 36, 0.18);
}

.sms-credits-widget__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.35rem;
    height: 1.35rem;
    border-radius: 0.25rem;
    background: transparent;
    color: #fff;
    font-size: 0.8rem;
    flex-shrink: 0;
    opacity: 0.9;
}

.sms-credits-widget__content {
    display: flex;
    flex-direction: column;
    gap: 0.05rem;
    min-width: 0;
    line-height: 1.15;
}

.sms-credits-widget__provider {
    font-size: 0.625rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    opacity: 0.75;
}

.sms-credits-widget__metrics {
    display: flex;
    align-items: baseline;
    gap: 0.55rem;
}

.sms-credits-widget__metric {
    display: inline-flex;
    align-items: baseline;
    gap: 0.2rem;
    white-space: nowrap;
}

.sms-credits-widget__metric-label {
    font-size: 0.6875rem;
    opacity: 0.75;
}

.sms-credits-widget__metric-value {
    font-size: 0.8125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
}

.sms-credits-widget__metric-value--date {
    font-size: 0.75rem;
    font-weight: 500;
}

@media (max-width: 991.98px) {
    .sms-credits-widget {
        gap: 0.25rem;
        padding: 0.2rem 0.4rem;
    }

    .sms-credits-widget__provider,
    .sms-credits-widget__metric--expiration,
    .sms-credits-widget__metric-label {
        display: none;
    }

    .sms-credits-widget__metrics {
        gap: 0;
    }
}

@media (max-width: 520px) {
    .sms-credits-widget {
        padding: 0.15rem 0.3rem;
        gap: 0.15rem;
    }

    .sms-credits-widget__icon {
        width: 1.2rem;
        height: 1.2rem;
        font-size: 0.7rem;
    }

    .local-mode-tag {
        display: none;
    }
}

:deep(.notification-btn) {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 2.25rem;
    height: 2.25rem;
    border-radius: 0.375rem;
    color: #fff;
    background: transparent;
    border: none;
    transition: background-color 0.15s ease;
    cursor: pointer;
    position: relative;

    &:hover {
        background-color: rgba(255, 255, 255, 0.12);
    }

    &:focus-visible {
        outline: none;
        box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.35);
    }

    .pi-bell {
        font-size: 1.05rem;
    }

    &.has-unread .pi-bell {
        color: #fee2e2;
    }
}
</style>
