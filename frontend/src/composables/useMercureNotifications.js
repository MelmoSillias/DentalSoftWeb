import { computed, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useMercureClient } from '@/composables/realtime/useMercureClient';
import http from '@/service/http';

const notificationHandlers = new Set();
const BACKGROUND_POLL_MS = 30000;

let backgroundPollTimer = null;
let globalListenersAttached = false;
let mercureHandlerAttached = false;

function stopBackgroundPolling() {
    if (typeof window === 'undefined' || !backgroundPollTimer) {
        return;
    }

    window.clearInterval(backgroundPollTimer);
    backgroundPollTimer = null;
}

async function refreshNotificationsFromRest({ emitNew = false } = {}) {
    const auth = useAuthStore();
    const notificationsStore = useNotificationsStore();

    if (!auth.token || auth.user?.notificationsEnabled === false) {
        return;
    }

    const response = await http.get('me/notifications?filter=all&limit=20');
    const items = (response?.data?.items || []).map((item) => normalizeNotification(item));

    if (emitNew) {
        emitNewNotifications(items);
    }

    notificationsStore.setNotifications(items);
}

function normalizeNotification(item) {
    return {
        id: item?.id,
        title: item?.title || 'Notification',
        message: item?.message || '',
        status: item?.status || (item?.read ? 'vu' : 'non_vu'),
        type: item?.type || 'info',
        priority: item?.priority || 'info',
        createdAt: item?.createdAt || item?.date || null,
        link: item?.link || null
    };
}

function emitNewNotifications(items = []) {
    const notificationsStore = useNotificationsStore();
    const knownIds = new Set(notificationsStore.notifications.map((item) => item.id));

    for (const item of items) {
        if (!item?.id || knownIds.has(item.id)) {
            continue;
        }

        notificationHandlers.forEach((handler) => handler(item));
    }
}

function syncBackgroundPolling() {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    const auth = useAuthStore();
    const shouldPoll = document.hidden && auth.token && auth.user?.notificationsEnabled !== false;

    if (shouldPoll && !backgroundPollTimer) {
        backgroundPollTimer = window.setInterval(() => {
            refreshNotificationsFromRest({ emitNew: true }).catch(() => {
                // ignore transient REST failures during background polling
            });
        }, BACKGROUND_POLL_MS);

        refreshNotificationsFromRest({ emitNew: true }).catch(() => {
            // ignore transient REST failures during background polling
        });
        return;
    }

    if (!shouldPoll) {
        stopBackgroundPolling();
    }
}

function handleDegradedPoll() {
    const auth = useAuthStore();

    if (auth.user?.notificationsEnabled === false) {
        return;
    }

    refreshNotificationsFromRest({ emitNew: true }).catch(() => {
        // ignore transient REST failures during degraded polling
    });
}

function attachMercureHandler() {
    if (mercureHandlerAttached) {
        return;
    }

    mercureHandlerAttached = true;
    const mercureClient = useMercureClient();

    mercureClient.on('notification', (payload) => {
        const notif = normalizeNotification(payload);
        const notificationsStore = useNotificationsStore();
        notificationsStore.addNotification(notif);
        notificationHandlers.forEach((handler) => handler(notif));
    });
}

function attachGlobalListeners() {
    if (globalListenersAttached || typeof window === 'undefined') {
        return;
    }

    globalListenersAttached = true;
    attachMercureHandler();
    window.addEventListener('mercure:degraded-poll', handleDegradedPoll);
    document.addEventListener('visibilitychange', syncBackgroundPolling);
    syncBackgroundPolling();
}

export function useMercureNotifications() {
    const auth = useAuthStore();
    const notificationsStore = useNotificationsStore();
    const mercureClient = useMercureClient();

    attachGlobalListeners();

    const notifications = computed(() => notificationsStore.notifications);
    const unreadCount = computed(() => notificationsStore.unreadCount);
    const connectionState = mercureClient.connectionState;

    const loadInitialNotifications = async () => {
        if (!auth.token) {
            return;
        }

        if (auth.user?.notificationsEnabled === false) {
            notificationsStore.setNotifications([]);
            return;
        }

        const response = await http.get('me/notifications?filter=all&limit=20');
        const items = (response?.data?.items || []).map(normalizeNotification);
        notificationsStore.setNotifications(items);
    };

    const start = async () => {
        if (!auth.token) {
            return;
        }

        if (auth.user?.notificationsEnabled === false) {
            notificationsStore.setNotifications([]);
            return;
        }

        await loadInitialNotifications();
    };

    const refreshFromRest = async () => {
        try {
            await refreshNotificationsFromRest({ emitNew: true });
        } catch (_) {
            // ignore transient REST failures during degraded polling
        }
    };

    const markAsRead = async (ids = []) => {
        if (!ids.length) {
            return;
        }

        await http.post('me/notifications/mark-read', { ids });
        notificationsStore.markAsRead(ids);
    };

    const markAllAsRead = async () => {
        await http.post('me/notifications/mark-all', {});
        notificationsStore.markAllAsRead();
    };

    function onNotificationReceived(callback) {
        if (typeof callback !== 'function') {
            return () => {};
        }

        notificationHandlers.add(callback);

        return () => {
            notificationHandlers.delete(callback);
        };
    }

    watch(
        () => auth.token,
        async (token) => {
            if (!token) {
                notificationsStore.setNotifications([]);
                syncBackgroundPolling();
                return;
            }

            try {
                await start();
            } catch (_) {
                // ignore transient failures during auth transitions
            }

            syncBackgroundPolling();
        }
    );

    watch(
        () => auth.user?.notificationsEnabled,
        async (enabled) => {
            if (enabled === false) {
                notificationsStore.setNotifications([]);
                syncBackgroundPolling();
                return;
            }

            if (enabled === true && auth.token) {
                try {
                    await start();
                } catch (_) {
                    // ignore
                }
            }

            syncBackgroundPolling();
        }
    );

    return {
        notifications,
        unreadCount,
        connectionState,
        start,
        refreshFromRest,
        markAsRead,
        markAllAsRead,
        onNotificationReceived
    };
}
