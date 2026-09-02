import { computed, onUnmounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications';
import { useMercureClient } from '@/composables/realtime/useMercureClient';
import http from '@/service/http';

const notificationHandlers = new Set();

export function useMercureNotifications() {
    const auth = useAuthStore();
    const notificationsStore = useNotificationsStore();
    const mercureClient = useMercureClient();

    const notifications = computed(() => notificationsStore.notifications);
    const unreadCount = computed(() => notificationsStore.unreadCount);
    const connectionState = mercureClient.connectionState;

    const normalizeNotification = (item) => ({
        id: item?.id,
        title: item?.title || 'Notification',
        message: item?.message || '',
        status: item?.status || (item?.read ? 'vu' : 'non_vu'),
        type: item?.type || 'info',
        priority: item?.priority || 'info',
        createdAt: item?.createdAt || item?.date || null,
        link: item?.link || null
    });

    const handleRealtimeNotification = (payload) => {
        const notif = normalizeNotification(payload);
        notificationsStore.addNotification(notif);
        notificationHandlers.forEach((handler) => handler(notif));
    };

    const unsubscribeNotification = mercureClient.on('notification', handleRealtimeNotification);

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
            await loadInitialNotifications();
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

    const handleDegradedPoll = () => {
        if (auth.user?.notificationsEnabled !== false) {
            refreshFromRest();
        }
    };

    if (typeof window !== 'undefined') {
        window.addEventListener('mercure:degraded-poll', handleDegradedPoll);
    }

    watch(
        () => auth.token,
        async (token) => {
            if (!token) {
                notificationsStore.setNotifications([]);
                return;
            }

            try {
                await start();
            } catch (_) {
                // ignore transient failures during auth transitions
            }
        }
    );

    watch(
        () => auth.user?.notificationsEnabled,
        async (enabled) => {
            if (enabled === false) {
                notificationsStore.setNotifications([]);
                return;
            }

            if (enabled === true && auth.token) {
                try {
                    await start();
                } catch (_) {
                    // ignore
                }
            }
        }
    );

    onUnmounted(() => {
        unsubscribeNotification();
        notificationHandlers.clear();

        if (typeof window !== 'undefined') {
            window.removeEventListener('mercure:degraded-poll', handleDegradedPoll);
        }
    });

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
