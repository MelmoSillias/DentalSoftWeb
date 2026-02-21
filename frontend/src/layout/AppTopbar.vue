<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { useAuthStore } from '@/stores/auth';
import AppConfigurator from './AppConfigurator.vue';
import Popover from 'primevue/popover';
import Button from 'primevue/button';
import { useToast } from 'primevue/usetoast';
import router from '@/router';
import http from '@/service/http';

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
const notifications = ref([]);
const unreadCount = ref(0);
const isNotificationsLoading = ref(false);

let eventSource = null;
let reconnectTimer = null;

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
        loadNotifications();
        connectMercure();
    }
});
onBeforeUnmount(() => {
    clearInterval(timer);
    disconnectMercure();
});

watch(
    () => auth.token,
    (token) => {
        if (token) {
            loadNotifications();
            connectMercure();
        } else {
            notifications.value = [];
            unreadCount.value = 0;
            disconnectMercure();
        }
    }
);

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
        disconnectMercure();
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

async function loadNotifications() {
    if (!auth.token) return;

    isNotificationsLoading.value = true;
    try {
        const [profileRes, notificationsRes] = await Promise.all([
            http.get('me'),
            http.get('me/notifications?filter=unread&limit=5')
        ]);

        unreadCount.value = profileRes?.data?.notificationsUnreadCount || 0;
        const items = notificationsRes?.data?.items || [];
        notifications.value = items.map(normalizeNotification);
    } catch (err) {
        toast.add({
            severity: 'warn',
            summary: 'Notifications',
            detail: 'Impossible de charger les notifications.',
            life: 3000
        });
    } finally {
        isNotificationsLoading.value = false;
    }
}

async function connectMercure() {
    if (!auth.token) return;

    disconnectMercure();

    try {
        const res = await http.get('me/notifications/mercure');
        const { publicUrl, topic, token } = res?.data || {};
        if (!publicUrl || !topic || !token) {
            return;
        }

        const url = new URL(publicUrl);
        url.searchParams.append('topic', topic);
        url.searchParams.append('token', token);

        eventSource = new EventSource(url.toString());
        eventSource.addEventListener('notification', (event) => {
            try {
                const payload = JSON.parse(event.data);
                handleIncomingNotification(payload);
            } catch (_) {
                // Ignore malformed payloads
            }
        });

        eventSource.onerror = () => {
            scheduleReconnect();
        };
    } catch (err) {
        scheduleReconnect();
    }
}

function disconnectMercure() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
    if (reconnectTimer) {
        clearTimeout(reconnectTimer);
        reconnectTimer = null;
    }
}

function scheduleReconnect() {
    if (reconnectTimer) {
        return;
    }
    disconnectMercure();
    reconnectTimer = setTimeout(() => {
        reconnectTimer = null;
        if (auth.token) {
            connectMercure();
        }
    }, 5000);
}

function normalizeNotification(item) {
    const status = item?.status || item?.etatVu;
    return {
        id: item?.id,
        message: item?.message || '',
        type: item?.type || 'info',
        priority: item?.priority || 'info',
        date: item?.createdAt || item?.date || null,
        read: (item?.read ?? status === 'vu') || (status === 'lu'),
        link: item?.link || null,
        emitter: item?.emitter || null
    };
}

function handleIncomingNotification(payload) {
    const notification = normalizeNotification(payload);
    if (!notification.id) return;

    const exists = notifications.value.some((item) => item.id === notification.id);
    if (!exists) {
        notifications.value.unshift(notification);
    }

    notifications.value = notifications.value.slice(0, 5);

    if (!notification.read) {
        unreadCount.value = Math.max(0, unreadCount.value + 1);
    }

    toast.add({
        severity: mapNotificationSeverity(notification.type),
        summary: 'Nouvelle notification',
        detail: notification.message,
        life: 5000
    });
}

function mapNotificationSeverity(type) {
    switch (type) {
        case 'success':
            return 'success';
        case 'warning':
            return 'warn';
        case 'danger':
            return 'error';
        default:
            return 'info';
    }
}

async function markNotificationRead(notification) {
    if (!notification?.id || notification.read) {
        return;
    }

    try {
        await http.post('me/notifications/mark-read', { ids: [notification.id] });
        notification.read = true;
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    } catch (_) {
        // ignore
    }
}

async function markAllNotificationsRead() {
    try {
        await http.post('me/notifications/mark-all', {});
        notifications.value = notifications.value.map((item) => ({
            ...item,
            read: true
        }));
        unreadCount.value = 0;
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
                
                <span style="font-weight: 500;">Dentalsoft <br> <small>Cabinet Dentaire Massaman</small></span>
                
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
            </div>
            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'animate-scalein', leaveToClass: 'hidden', leaveActiveClass: 'animate-fadeout', hideOnOutsideClick: true }"
            >
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
                        <button type="button" class="layout-topbar-action flex items-center gap-1" @click="toggleNotificationsPopover" ref="notificationsButton">
                            <i class="pi pi-bell"></i>
                            <span>Notifications</span>
                            <span v-if="unreadCount" class="notification-badge">{{ unreadCount > 99 ? '99+' : unreadCount }}</span>
                        </button>
                        <Popover
                            ref="notificationsPopover"
                            v-model:visible="showNotificationsPopover"
                            :autoHide="true"
                            :dismissable="true"
                            :target="notificationsButton"
                            position="bottom"
                            class="w-72 bg-white dark:bg-gray-800 shadow-lg rounded-lg p-4"
                            style="z-index: 1000"
                        >
                            <div class="flex items-center justify-between mb-3">
                                <span class="font-semibold text-gray-800 dark:text-gray-100">Notifications</span>
                                <button
                                    type="button"
                                    class="text-xs text-primary-600 dark:text-primary-400 hover:underline"
                                    @click="markAllNotificationsRead"
                                >
                                    Tout lire
                                </button>
                            </div>
                            <div v-if="isNotificationsLoading" class="text-sm text-gray-600 dark:text-gray-300 text-center">
                                Chargement...
                            </div>
                            <div v-else-if="!notifications.length" class="text-sm text-gray-600 dark:text-gray-300 text-center">
                                Aucune notification
                            </div>
                            <div v-else class="space-y-2">
                                <button
                                    v-for="notification in notifications"
                                    :key="notification.id"
                                    type="button"
                                    class="w-full text-left p-2 rounded-md hover:bg-gray-50 dark:hover:bg-gray-700"
                                    @click="handleNotificationClick(notification)"
                                >
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-100" :class="{ 'opacity-70': notification.read }">
                                        {{ notification.message }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ formatNotificationDate(notification.date) }}
                                    </p>
                                </button>
                            </div>
                        </Popover>
                    </div>
                    <!-- ======= PROFIL avec POPOVER ======= -->
                    <div class="relative">
                        <button type="button" class="layout-topbar-action flex items-center gap-1" @click="toggleProfilePopover" ref="profileButton">
                            <i class="pi pi-user"></i>
                            <span>Profil</span>
                        </button>
                        <Popover
                            ref="profilePopover"
                            v-model:visible="showProfilePopover"
                            :autoHide="true"
                            :dismissable="true"
                            :target="profileButton"
                            position="bottom"
                            class="w-64 bg-white dark:bg-gray-800 shadow-lg rounded-lg p-4"
                            style="z-index: 1000"
                        >
                            <div class="flex items-center gap-3 border-b pb-3 mb-3">
                                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="User Avatar" class="w-12 h-12 rounded-full border border-gray-300 dark:border-gray-600" />
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
                                <Button class="p-button-secondary p-button-sm w-full" label="Mon profil" icon="pi pi-user" iconPos="left" @click="openProfile" />
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
</style>
