<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { useAuthStore } from '@/stores/auth';
import AppConfigurator from './AppConfigurator.vue';
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

function updateDateTime() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    currentDate.value = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
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
        if (profilePopover && profilePopover.value && typeof profilePopover.value.hide === 'function') {
            profilePopover.value.hide();
        }
    } catch (e) {
        // ignore
    }
    router.push({ name: 'profile' });
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
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/" class="layout-topbar-logo">
                <div class="h-12 w-12 rounded-full rounded-50 p-1 bg-white dark:bg-white/90">
                    <img src="/logo.png" class="app-logo" width="54" height="40" :alt="cabinetConfig.brandName" />
                </div>

                <span style="font-weight: 500;">{{ cabinetConfig.brandName }} <br> <small>{{ cabinetConfig.brandSubtitle }}</small></span>

            </router-link>
            <Tag
                v-if="isLocalDeploymentMode"
                value="Mode local"
                severity="warn"
                class="local-mode-tag"
                role="status"
            />
        </div>
        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <button
                    v-if="showSmsCredits && canOpenSmsSettings"
                    type="button"
                    class="sms-credits-widget sms-credits-widget--clickable"
                    :class="{ 'sms-credits-widget--warn': !smsOverviewSuccess && !smsCreditsLoading }"
                    @click="openSmsSettings"
                >
                    <div class="sms-credits-widget__icon" aria-hidden="true">
                        <i class="pi pi-comment"></i>
                    </div>
                    <div class="sms-credits-widget__content">
                        <span class="sms-credits-widget__provider">{{ smsProviderLabel }}</span>
                        <div class="sms-credits-widget__metrics">
                            <div class="sms-credits-widget__metric">
                                <span class="sms-credits-widget__metric-label">Restants</span>
                                <span class="sms-credits-widget__metric-value">{{ smsDisplayUnits }}</span>
                            </div>
                            <div class="sms-credits-widget__metric">
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
                >
                    <div class="sms-credits-widget__icon" aria-hidden="true">
                        <i class="pi pi-comment"></i>
                    </div>
                    <div class="sms-credits-widget__content">
                        <span class="sms-credits-widget__provider">{{ smsProviderLabel }}</span>
                        <div class="sms-credits-widget__metrics">
                            <div class="sms-credits-widget__metric">
                                <span class="sms-credits-widget__metric-label">Restants</span>
                                <span class="sms-credits-widget__metric-value">{{ smsDisplayUnits }}</span>
                            </div>
                            <div class="sms-credits-widget__metric">
                                <span class="sms-credits-widget__metric-label">Expiration</span>
                                <span class="sms-credits-widget__metric-value sms-credits-widget__metric-value--date">{{ smsDisplayExpiration }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <button
                    type="button"
                    class="layout-topbar-action"
                    :class="{ 'layout-topbar-action-disabled': !isGuidedTourAvailable }"
                    @click="toggleHelpPopover($event)"
                    ref="helpButton"
                    :aria-disabled="!isGuidedTourAvailable"
                    title="Aide guidee"
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
                    class="w-[22rem] max-w-[90vw] bg-surface-0 dark:bg-surface-900 shadow-xl rounded-2xl border border-surface-200/70 dark:border-surface-700/70 p-0 overflow-hidden"
                    style="z-index: 1000"
                >
                    <div class="px-4 py-3 border-b border-surface-200/70 dark:border-surface-700/70 bg-surface-50/80 dark:bg-surface-800/80">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-question-circle text-primary-500"></i>
                            <span class="font-semibold text-surface-900 dark:text-surface-50">Aide guidee</span>
                        </div>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">
                            Choisissez une action a decouvrir sur cette page.
                        </p>
                    </div>
                    <div class="p-2 space-y-1 max-h-[24rem] overflow-y-auto">
                        <button
                            v-for="item in guidedTourMenuItems"
                            :key="`${item.taskId}:${item.variantId || 'default'}`"
                            type="button"
                            class="w-full text-left p-3 rounded-xl border border-transparent hover:border-surface-200 dark:hover:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
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
                <!-- <div class="relative">
                     <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button> -->
                <!-- <AppConfigurator />
                </div> -->
                <NotificationBell variant="topbar" popover-position="bottom" />
            </div>
            <button class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }">
                <i class="pi pi-ellipsis-v"></i>
            </button>
            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content flex items-center gap-4">
                    <!-- ======= CALENDRIER + DATE/HEURE ======= -->
                    <!-- <div class="relative flex items-center gap-2 layout-topbar-action">
                        <i class="pi pi-calendar"></i>
                        <div class="flex flex-col leading-tight text-base">
                            <span>{{ currentTime }}</span>
                            <span class="text-sm opacity-80">{{ currentDate }}</span>
                        </div>
                    </div> -->
                    <!-- ======= NOTIFICATIONS avec POPOVER ======= -->
                    <div class="relative">

                    </div>
                    <!-- ======= PROFIL avec POPOVER ======= -->
                    <div class="relative">
                        <button type="button" class="layout-topbar-action flex items-center gap-1"
                            @click="toggleProfilePopover" ref="profileButton">
                            <i class="pi pi-user"></i>
                            <span>Profil</span>
                        </button>
                        <Popover ref="profilePopover" v-model:visible="showProfilePopover" :autoHide="true"
                            :dismissable="true" :target="profileButton" position="bottom"
                            class="w-64 bg-white dark:bg-gray-800 shadow-lg rounded-lg p-4" style="z-index: 1000">
                            <div class="flex items-center gap-3 border-b pb-3 mb-3">
                                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="User Avatar"
                                    class="w-12 h-12 rounded-full border border-gray-300 dark:border-gray-600" />
                                <div>
                                    <p class="font-semibold text-lg text-gray-800 dark:text-gray-100">
                                        {{ auth.user?.username || 'Utilisateur' }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                        {{ auth.user?.role || 'Administrateur' }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-2">
                                <Button class="p-button-secondary p-button-sm w-full" label="Mon profil"
                                    icon="pi pi-user" iconPos="left" @click="openProfile" />
                                <Button :loading="isLoggingOut" class="p-button-danger p-button-sm w-full"
                                    label="Déconnexion" icon="pi pi-sign-out" iconPos="left" @click="handleLogout" />
                            </div>
                        </Popover>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* .layout-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
    padding: 0 1rem;
    background-color: var(--primary-color);
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
} */

.layout-topbar-logo span {
    text-wrap-mode: nowrap;
}

.local-mode-tag {
    flex-shrink: 0;
    margin-left: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
}

.layout-topbar-action {
    display: flex;
    align-items: center;
    gap: 0.3rem;
    cursor: pointer;
    color: #fff;
    padding: 0.5rem;
    border-radius: 4px;
    transition: background-color 0.2s;
}

.layout-topbar-action:hover {
    color: #fff;
    background-color: rgba(255, 255, 255, 0.12);
}

.layout-topbar-action-disabled {
    opacity: 0.55;
}

.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: #ef4444;
    color: #fff;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1;
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
    gap: 0.5rem;
    padding: 0.3rem 0.65rem 0.3rem 0.45rem;
    max-height: 2.65rem;
    border-radius: 0.625rem;
    background: rgba(255, 255, 255, 0.94);
    border: 1px solid rgba(255, 255, 255, 0.55);
    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.08),
        inset 0 1px 0 rgba(255, 255, 255, 0.85);
    color: #334155;
    text-align: left;
    flex-shrink: 0;
}

.sms-credits-widget--clickable {
    cursor: pointer;
    font: inherit;
    appearance: none;
    transition: box-shadow 0.15s ease, transform 0.15s ease;
}

.sms-credits-widget--clickable:hover {
    box-shadow:
        0 2px 8px rgba(15, 23, 42, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.85);
    transform: translateY(-1px);
}

.sms-credits-widget--clickable:active {
    transform: translateY(0);
}

.sms-credits-widget--warn {
    border-color: rgba(251, 191, 36, 0.65);
    background: rgba(255, 251, 235, 0.96);
}

.sms-credits-widget__icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.45rem;
    background: color-mix(in srgb, var(--primary-color, #3b82f6) 14%, white);
    color: var(--primary-color, #3b82f6);
    font-size: 0.85rem;
    flex-shrink: 0;
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
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: #64748b;
}

.sms-credits-widget__metrics {
    display: flex;
    align-items: baseline;
    gap: 0.65rem;
}

.sms-credits-widget__metric {
    display: inline-flex;
    align-items: baseline;
    gap: 0.25rem;
    white-space: nowrap;
}

.sms-credits-widget__metric-label {
    font-size: 0.6875rem;
    color: #64748b;
}

.sms-credits-widget__metric-value {
    font-size: 0.8125rem;
    font-weight: 700;
    color: #0f172a;
    font-variant-numeric: tabular-nums;
}

.sms-credits-widget__metric-value--date {
    font-size: 0.75rem;
    font-weight: 600;
}

.notification-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    color: #fff;
    background: transparent;
    transition: background-color 0.2s ease;
    cursor: pointer;
    position: relative;

    &:hover {
        background-color: rgba(255, 255, 255, 0.12);
    }

    &:focus-visible {
        outline: none;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.3);
    }

    .pi-bell {
        font-size: 1.3rem;
    }

    &.has-unread {
        animation: ring 2s ease-in-out infinite;

        .pi-bell {
            color: #fef2f2;
        }
    }
}

@keyframes ring {
    0% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.55);
    }

    70% {
        box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
    }

    100% {
        box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
    }
}
</style>
