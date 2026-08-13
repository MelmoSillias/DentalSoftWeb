<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { useAuthStore } from '@/stores/auth';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import OverlayBadge from 'primevue/overlaybadge';
import { useToast } from 'primevue/usetoast';
import router from '@/router';
import { useMercureNotifications } from '@/composables/useMercureNotifications';
import { useRoute } from 'vue-router';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { useSmsTopbarCredits } from '@/composables/useSmsTopbarCredits';
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

const showNotificationsPopover = ref(false);
const showProfilePopover = ref(false);
const notificationsButton = ref(null);
const profileButton = ref(null);
const notificationsPopover = ref(null);
const profilePopover = ref(null);
const isLoggingOut = ref(false);
const isNotificationsLoading = ref(false);

const {
    notifications,
    unreadCount,
    start: startNotifications,
    markAsRead,
    markAllAsRead,
    onNotificationReceived
} = useMercureNotifications();

let notificationAudio = null;
let audioEnabled = false;
function enableNotificationAudio() {
    if (!notificationAudio) notificationAudio = new Audio('/notification.mp3');
    audioEnabled = true;
}
function playNotificationSound() {
    if (!audioEnabled) return;
    try {
        notificationAudio.currentTime = 0;
        notificationAudio.play();
    } catch (_) { /* ignore */ }
}

if (typeof window !== 'undefined') {
    const enableAudioOnce = () => {
        enableNotificationAudio();
        window.removeEventListener('click', enableAudioOnce, true);
    };
    window.addEventListener('click', enableAudioOnce, true);
}

function resolveNotificationSeverity(type) {
    switch (type) {
        case 'success':
            return 'success';
        case 'error':
            return 'error';
        case 'warning':
            return 'warn';
        default:
            return 'info';
    }
}

function showNotificationToast(notification) {
    toast.add({
        severity: resolveNotificationSeverity(notification.type),
        summary: notification.title || 'Notification',
        detail: notification.message,
        life: 3000
    });
}

if (onNotificationReceived) {
    onNotificationReceived((notif) => {
        playNotificationSound();
        showNotificationToast(notif);
    });
}

const topbarNotifications = computed(() => notifications.value.slice(0, 5));
const isOnHub = computed(() => route.name === 'navigation-hub');

function getNotificationIcon(notification) {
    const type = notification?.type;
    if (type === 'success') return 'pi pi-check-circle';
    if (type === 'error') return 'pi pi-times-circle';
    if (type === 'warning') return 'pi pi-exclamation-triangle';
    return 'pi pi-info-circle';
}

function getNotificationIconClass(notification) {
    const type = notification?.type;
    if (type === 'success') return 'text-green-500';
    if (type === 'error') return 'text-red-500';
    if (type === 'warning') return 'text-orange-500';
    return 'text-primary-500';
}

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
        isNotificationsLoading.value = true;
        startNotifications()
            .catch(() => {
                toast.add({
                    severity: 'warn',
                    summary: 'Notifications',
                    detail: 'Impossible de charger les notifications.',
                    life: 3000
                });
            })
            .finally(() => {
                isNotificationsLoading.value = false;
            });
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

function toggleNotificationsPopover(event) {
    if (showNotificationsPopover.value) {
        notificationsPopover.value.hide();
    } else {
        notificationsPopover.value.show(event);
    }
    showNotificationsPopover.value = !showNotificationsPopover.value;
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

async function markNotificationRead(notification) {
    if (!notification?.id || notification.status === 'vu') {
        return;
    }
    try {
        await markAsRead([notification.id]);
    } catch (_) {
        // ignore
    }
}

async function markAllNotificationsRead() {
    try {
        await markAllAsRead();
    } catch (_) {
        // ignore
    }
}

function formatNotificationDate(value) {
    if (!value) return '';
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }
    return parsed.toLocaleString();
}

async function handleNotificationClick(notification) {
    await markNotificationRead(notification);
    if (notification?.link) {
        router.push(notification.link);
    }
    try {
        if (notificationsPopover?.value?.hide) {
            notificationsPopover.value.hide();
        }
    } catch (_) {
        // ignore
    }
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

            <button
                type="button"
                class="layout-right-rail__btn layout-right-rail__notif"
                :class="{ 'has-unread': unreadCount > 0 }"
                title="Notifications"
                aria-label="Notifications"
                ref="notificationsButton"
                @click="toggleNotificationsPopover($event)"
            >
                <OverlayBadge
                    v-if="unreadCount && unreadCount !== 0"
                    :value="unreadCount"
                    severity="danger"
                    class="inline-flex"
                >
                    <i class="pi pi-bell" />
                </OverlayBadge>
                <i v-else class="pi pi-bell" />
            </button>
            <Popover
                ref="notificationsPopover"
                v-model:visible="showNotificationsPopover"
                :autoHide="true"
                :dismissable="true"
                :target="notificationsButton"
                position="left"
                class="w-[24rem] max-w-[90vw] bg-surface-0 dark:bg-surface-900 shadow-xl rounded-2xl border border-surface-200/70 dark:border-surface-700/70 p-0 overflow-hidden"
                style="z-index: 1000"
            >
                <div
                    class="px-4 py-3 border-b border-surface-200/70 dark:border-surface-700/70 bg-surface-50/80 dark:bg-surface-800/80"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <i class="pi pi-bell text-primary-500"></i>
                            <span class="font-semibold text-surface-900 dark:text-surface-50">Notifications</span>
                        </div>
                        <button
                            type="button"
                            class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline"
                            @click="markAllNotificationsRead"
                        >
                            Tout lire
                        </button>
                    </div>
                </div>
                <div v-if="isNotificationsLoading" class="p-6 text-sm text-surface-600 dark:text-surface-300 text-center">
                    <i class="pi pi-spin pi-spinner mr-2"></i>
                    Chargement...
                </div>
                <div v-else-if="!notifications.length" class="p-6 text-center">
                    <p class="text-sm font-medium text-surface-700 dark:text-surface-200">Aucune notification</p>
                </div>
                <div v-else class="p-2 space-y-1 max-h-[22rem] overflow-y-auto">
                    <button
                        v-for="notification in topbarNotifications"
                        :key="notification.id"
                        type="button"
                        class="group w-full text-left p-3 rounded-xl border border-transparent hover:border-surface-200 dark:hover:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                        :class="{
                            'bg-primary-50/50 dark:bg-primary-900/20 border-primary-200/60 dark:border-primary-800/60':
                                notification.status !== 'vu'
                        }"
                        @click="handleNotificationClick(notification)"
                    >
                        <div class="flex items-start gap-3">
                            <i :class="[getNotificationIcon(notification), getNotificationIconClass(notification)]"></i>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-sm text-surface-800 dark:text-surface-100 leading-5"
                                    :class="{ 'font-semibold': notification.status !== 'vu' }"
                                >
                                    {{ notification.message }}
                                </p>
                                <span class="text-xs text-surface-500 dark:text-surface-400">
                                    {{ formatNotificationDate(notification.createdAt) }}
                                </span>
                            </div>
                        </div>
                    </button>
                </div>
            </Popover>
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
