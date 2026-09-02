<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'primevue/usetoast';
import Popover from 'primevue/popover';
import OverlayBadge from 'primevue/overlaybadge';
import { useMercureNotifications } from '@/composables/useMercureNotifications';
import { useNotificationPresentation } from '@/composables/useNotificationPresentation';

const props = defineProps({
    variant: {
        type: String,
        default: 'topbar',
        validator: (value) => ['topbar', 'rail'].includes(value)
    },
    popoverPosition: {
        type: String,
        default: 'bottom'
    }
});

const router = useRouter();
const toast = useToast();

const {
    notifications,
    unreadCount,
    connectionState,
    start: startNotifications,
    markAsRead,
    markAllAsRead,
    onNotificationReceived
} = useMercureNotifications();
const { shouldShowInApp } = useNotificationPresentation();

const showNotificationsPopover = ref(false);
const notificationsButton = ref(null);
const notificationsPopover = ref(null);
const isNotificationsLoading = ref(false);

const topbarNotifications = computed(() => notifications.value.slice(0, 5));

const connectionIndicatorClass = computed(() => {
    if (connectionState.value === 'connected') {
        return 'is-connected';
    }

    if (connectionState.value === 'connecting') {
        return 'is-connecting';
    }

    return 'is-disconnected';
});

const connectionIndicatorTitle = computed(() => {
    if (connectionState.value === 'connected') {
        return 'Temps réel actif';
    }

    if (connectionState.value === 'connecting') {
        return 'Connexion temps réel en cours';
    }

    return 'Temps réel indisponible — reconnexion en cours';
});

let notificationAudio = null;
let audioEnabled = false;
let toastGroupTimer = null;
let pendingToastCount = 0;

function enableNotificationAudio() {
    if (!notificationAudio) {
        notificationAudio = new Audio('/notification.mp3');
    }
    audioEnabled = true;
}

function playNotificationSound() {
    if (!audioEnabled) {
        return;
    }

    try {
        notificationAudio.currentTime = 0;
        notificationAudio.play();
    } catch (_) {
        // ignore autoplay restrictions
    }
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
    pendingToastCount += 1;

    if (toastGroupTimer) {
        clearTimeout(toastGroupTimer);
    }

    toastGroupTimer = setTimeout(() => {
        const count = pendingToastCount;
        pendingToastCount = 0;
        toastGroupTimer = null;

        toast.add({
            severity: resolveNotificationSeverity(notification.type),
            summary: count > 1 ? `${count} nouvelles notifications` : (notification.title || 'Notification'),
            detail: count > 1 ? 'Consultez la cloche pour les détails.' : notification.message,
            life: 3000
        });
    }, 400);
}

onNotificationReceived((notification) => {
    if (!shouldShowInApp()) {
        return;
    }

    playNotificationSound();
    showNotificationToast(notification);
});

if (typeof window !== 'undefined') {
    const enableAudioOnce = () => {
        enableNotificationAudio();
        window.removeEventListener('click', enableAudioOnce, true);
    };
    window.addEventListener('click', enableAudioOnce, true);
}

onMounted(async () => {
    isNotificationsLoading.value = true;
    try {
        await startNotifications();
    } catch (_) {
        toast.add({
            severity: 'warn',
            summary: 'Notifications',
            detail: 'Impossible de charger les notifications.',
            life: 3000
        });
    } finally {
        isNotificationsLoading.value = false;
    }
});

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

function toggleNotificationsPopover(event) {
    if (showNotificationsPopover.value) {
        notificationsPopover.value?.hide?.();
    } else {
        notificationsPopover.value?.show?.(event);
    }
    showNotificationsPopover.value = !showNotificationsPopover.value;
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
        notificationsPopover.value?.hide?.();
    } catch (_) {
        // ignore
    }
}
</script>

<template>
    <div class="notification-bell" :class="[`notification-bell--${variant}`, connectionIndicatorClass]">
        <button
            type="button"
            class="notification-bell__trigger"
            :class="{
                'has-unread': unreadCount > 0,
                'notification-btn': variant === 'topbar',
                'layout-right-rail__btn layout-right-rail__notif': variant === 'rail'
            }"
            :title="connectionIndicatorTitle"
            @click="toggleNotificationsPopover($event)"
            ref="notificationsButton"
        >
            <span class="notification-bell__status" :title="connectionIndicatorTitle" aria-hidden="true" />
            <OverlayBadge
                v-if="unreadCount && unreadCount !== 0"
                :value="unreadCount"
                severity="danger"
                class="inline-flex items-center justify-center"
            >
                <i :class="variant === 'topbar' ? 'pi pi-bell text-2xl' : 'pi pi-bell'" />
            </OverlayBadge>
            <i v-else :class="variant === 'topbar' ? 'pi pi-bell text-2xl' : 'pi pi-bell'" />
            <span class="sr-only">Notifications ({{ unreadCount }} non lues)</span>
        </button>

        <Popover
            ref="notificationsPopover"
            v-model:visible="showNotificationsPopover"
            :autoHide="true"
            :dismissable="true"
            :target="notificationsButton"
            :position="popoverPosition"
            class="w-[24rem] max-w-[90vw] bg-surface-0 dark:bg-surface-900 shadow-xl rounded-2xl border border-surface-200/70 dark:border-surface-700/70 p-0 overflow-hidden"
            style="z-index: 1000"
        >
            <div class="px-4 py-3 border-b border-surface-200/70 dark:border-surface-700/70 bg-surface-50/80 dark:bg-surface-800/80">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="pi pi-bell text-primary-500"></i>
                        <span class="font-semibold text-surface-900 dark:text-surface-50">Notifications</span>
                        <span
                            v-if="unreadCount"
                            class="text-xs px-2 py-0.5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300"
                        >
                            {{ unreadCount }} non lue(s)
                        </span>
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
                Chargement des notifications...
            </div>
            <div v-else-if="!notifications.length" class="p-6 text-center">
                <div class="inline-flex items-center justify-center h-10 w-10 rounded-full bg-surface-100 dark:bg-surface-800 mb-3">
                    <i class="pi pi-bell-slash text-surface-400"></i>
                </div>
                <p class="text-sm font-medium text-surface-700 dark:text-surface-200">Aucune notification</p>
                <p class="text-xs text-surface-500 dark:text-surface-400 mt-1">Les nouvelles alertes apparaîtront ici.</p>
            </div>
            <div v-else class="p-2 space-y-1 max-h-[22rem] overflow-y-auto">
                <button
                    v-for="notification in topbarNotifications"
                    :key="notification.id"
                    type="button"
                    class="group w-full text-left p-3 rounded-xl border border-transparent hover:border-surface-200 dark:hover:border-surface-700 hover:bg-surface-50 dark:hover:bg-surface-800 transition-colors"
                    :class="{
                        'bg-primary-50/50 dark:bg-primary-900/20 border-primary-200/60 dark:border-primary-800/60': notification.status !== 'vu'
                    }"
                    @click="handleNotificationClick(notification)"
                >
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5">
                            <i :class="[getNotificationIcon(notification), getNotificationIconClass(notification)]"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p
                                class="text-sm text-surface-800 dark:text-surface-100 leading-5"
                                :class="{ 'font-semibold': notification.status !== 'vu' }"
                            >
                                {{ notification.message }}
                            </p>
                            <div class="mt-1 flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400">
                                <span>{{ formatNotificationDate(notification.createdAt) }}</span>
                                <span v-if="notification.link" class="inline-flex items-center gap-1">
                                    <i class="pi pi-link"></i>
                                    Action disponible
                                </span>
                            </div>
                        </div>
                        <div v-if="notification.status !== 'vu'" class="mt-1 h-2 w-2 rounded-full bg-primary-500"></div>
                    </div>
                </button>
            </div>
        </Popover>
    </div>
</template>

<style scoped>
.notification-bell {
    position: relative;
    display: inline-flex;
}

.notification-bell__trigger {
    position: relative;
}

.notification-bell__status {
    position: absolute;
    right: 0.15rem;
    bottom: 0.15rem;
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 9999px;
    border: 1px solid var(--surface-0, #fff);
    z-index: 2;
}

.notification-bell--rail .notification-bell__status {
    right: 0.35rem;
    bottom: 0.35rem;
}

.notification-bell.is-connected .notification-bell__status {
    background: #22c55e;
}

.notification-bell.is-connecting .notification-bell__status {
    background: #f59e0b;
}

.notification-bell.is-disconnected .notification-bell__status {
    background: #ef4444;
}
</style>
