<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { useAuthStore } from '@/stores/auth';
import AppConfigurator from './AppConfigurator.vue';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import OverlayBadge from 'primevue/overlaybadge';
import { useToast } from 'primevue/usetoast';
import router from '@/router';
import { useMercureNotifications } from '@/composables/useMercureNotifications';

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();
const auth = useAuthStore();
const toast = useToast();
const currentTime = ref('');
const currentDate = ref('');
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
    onNotificationReceived // à ajouter dans le composable
} = useMercureNotifications();

// Son de notification (bip)
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
    } catch (_) { }
}

// Active le son après la première interaction utilisateur (clic n'importe où)
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
};

// Toast notification
function showNotificationToast(notification) {
    toast.add({
        severity: resolveNotificationSeverity(notification.type),
        summary: notification.title || 'Notification',
        detail: notification.message,
        life: 3000
    });
}

// Gestion de l'événement notification reçue
if (onNotificationReceived) {
    onNotificationReceived((notif) => {
        playNotificationSound();
        showNotificationToast(notif);
    });
}

const topbarNotifications = computed(() => notifications.value.slice(0, 5));

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

function updateDateTime() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    currentDate.value = now.toLocaleDateString([], { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}

let timer;
onMounted(() => {
    updateDateTime();
    timer = setInterval(updateDateTime, 1000);
    if (auth.token && !auth.user) {
        auth.fetchUser(); // Fetch user data if token exists
    }
    if (auth.token) {
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
    clearInterval(timer);
});

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
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <button class="layout-menu-button layout-topbar-action" @click="toggleMenu">
                <i class="pi pi-bars"></i>
            </button>
            <router-link to="/" class="layout-topbar-logo">
                <div class="h-12 w-12 rounded-full rounded-50 p-1 bg-white dark:bg-white/90">
                    <img src="@/assets/logo.png" class="app-logo" width="54" height="40" />
                </div>

                <span style="font-weight: 500;">Dentalsoft <br> <small>Cabinet Dentaire Orodent</small></span>

            </router-link>
        </div>
        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
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
                <button type="button" class="notification-btn" :class="{ 'has-unread': unreadCount > 0 }"
                    @click="toggleNotificationsPopover($event)" ref="notificationsButton">
                    <OverlayBadge v-if="unreadCount && unreadCount !== 0" :value="unreadCount" severity="danger"
                        class="inline-flex items-center justify-center">
                        <i class="pi pi-bell text-2xl" /> <!-- augmente la taille pour tester visibilité -->
                    </OverlayBadge>
                    <i v-else class="pi pi-bell text-2xl" /> <!-- fallback icon when no unread notifications -->

                    <span class="sr-only">Notifications ({{ unreadCount }} non lues)</span>
                </button>
                <Popover ref="notificationsPopover" v-model:visible="showNotificationsPopover" :autoHide="true"
                    :dismissable="true" :target="notificationsButton" position="bottom"
                    class="w-[24rem] max-w-[90vw] bg-surface-0 dark:bg-surface-900 shadow-xl rounded-2xl border border-surface-200/70 dark:border-surface-700/70 p-0 overflow-hidden"
                    style="z-index: 1000">
                    <div
                        class="px-4 py-3 border-b border-surface-200/70 dark:border-surface-700/70 bg-surface-50/80 dark:bg-surface-800/80">
                        <div class="flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <i class="pi pi-bell text-primary-500"></i>
                                <span class="font-semibold text-surface-900 dark:text-surface-50">Notifications</span>
                                <span v-if="unreadCount"
                                    class="text-xs px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300">
                                    {{ unreadCount }} non lue(s)
                                </span>
                            </div>
                            <button type="button"
                                class="text-xs font-medium text-primary-600 dark:text-primary-400 hover:underline"
                                @click="markAllNotificationsRead">
                                Tout lire
                            </button>
                        </div>
                    </div>
                    <div v-if="isNotificationsLoading"
                        class="p-6 text-sm text-surface-600 dark:text-surface-300 text-center">
                        <i class="pi pi-spin pi-spinner mr-2"></i>
                        Chargement des notifications...
                    </div>
                    <div v-else-if="!notifications.length" class="p-6 text-center">
                        <div
                            class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                            <i class="pi pi-bell-slash text-surface-400"></i>
                        </div>
                        <p class="text-sm font-medium text-surface-700 dark:text-surface-200">Aucune notification</p>
                        <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">Les nouvelles alertes
                            apparaîtront ici.</p>
                    </div>
                    <div v-else class="p-2 space-y-1 max-h-[22rem] overflow-y-auto">
                        <button v-for="notification in topbarNotifications" :key="notification.id" type="button"
                            class="group w-full text-left p-3 rounded-xl border border-transparent hover:border-surface-200 dark:hover:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                            :class="{
                                'bg-primary-50/50 dark:bg-primary-900/20 border-primary-200/60 dark:border-primary-800/60': notification.status !== 'vu'
                            }" @click="handleNotificationClick(notification)">
                            <div class="flex items-start gap-3">
                                <div class="mt-0.5">
                                    <i
                                        :class="[getNotificationIcon(notification), getNotificationIconClass(notification)]"></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-surface-800 dark:text-surface-100 leading-5"
                                        :class="{ 'font-semibold': notification.status !== 'vu' }">
                                        {{ notification.message }}
                                    </p>
                                    <div
                                        class="mt-1 flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
                                        <span>{{ formatNotificationDate(notification.createdAt) }}</span>
                                        <span v-if="notification.link" class="inline-flex items-center gap-1">
                                            <i class="pi pi-link"></i>
                                            Action disponible
                                        </span>
                                    </div>
                                </div>
                                <div v-if="notification.status !== 'vu'"
                                    class="mt-1 h-2 w-2 rounded-full bg-primary-500"></div>
                            </div>
                        </button>
                    </div>
                </Popover>
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
.layout-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 60px;
    padding: 0 1rem;
    background-color: var(--primary-color);
    border-bottom: 1px solid rgba(255, 255, 255, 0.18);
}

.layout-topbar-logo span {
    text-wrap-mode: nowrap;
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
