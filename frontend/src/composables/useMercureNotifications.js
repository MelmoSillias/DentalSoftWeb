import { computed, onUnmounted, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useNotificationsStore } from '@/stores/notifications'; 
import http from '@/service/http';
import { fetchEventSource } from '@microsoft/fetch-event-source';
 

export function useMercureNotifications() {
    const auth = useAuthStore();
    const notificationsStore = useNotificationsStore();

    let eventSource = null; // will hold an AbortController for fetchEventSource
    let reconnectTimer = null;
    const recentEventIds = new Set();

    const notifications = computed(() => notificationsStore.notifications);
    const unreadCount = computed(() => notificationsStore.unreadCount);

    let notificationReceivedCb = null;
    function onNotificationReceived(cb) {
        notificationReceivedCb = cb;
    }

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

    const isFocusRealtimePayload = (payload, event) => {
        if (event?.event === 'focus-consultation') {
            return true;
        }

        if (typeof event?.id === 'string' && event.id.startsWith('focus-consultation-')) {
            return true;
        }

        return ['consultation', 'patient', 'devis', 'payment'].includes(payload?.entity) && typeof payload?.action === 'string';
    };

    const markEventAsSeen = (notificationId) => {
        if (!notificationId) return false;

        const key = String(notificationId);
        if (recentEventIds.has(key)) {
            return true;
        }

        recentEventIds.add(key);
        if (recentEventIds.size > 200) {
            const firstKey = recentEventIds.values().next().value;
            if (firstKey) recentEventIds.delete(firstKey);
        }

        return false;
    };

    const disconnect = () => {
        if (eventSource) {
            try {
                eventSource.abort();
            } catch (e) {
                // ignore
            }
            eventSource = null;
        }
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }
    };

    const scheduleReconnect = () => {
        if (reconnectTimer || !auth.token) return;
        disconnect();
        reconnectTimer = setTimeout(() => {
            reconnectTimer = null;
            connect();
        }, 5000);
    };

    const loadInitialNotifications = async () => {
        if (!auth.token) return;

        const res = await http.get('me/notifications?filter=all&limit=20');
        const items = (res?.data?.items || []).map(normalizeNotification);
        notificationsStore.setNotifications(items);
    };

    const connect = async () => {
        if (!auth.token) return;

        disconnect();

        try {
            const res = await http.get('me/notifications/mercure');
            const { publicUrl, topic, token } = res?.data || {};
            if (!publicUrl || !topic || !token) return;

            const url = new URL(publicUrl);
            url.searchParams.append('topic', topic);

            const controller = new AbortController();
            eventSource = controller;

            fetchEventSource(url.toString(), {
                method: 'GET',
                headers: {
                    Authorization: `Bearer ${token}`
                },
                signal: controller.signal,
                onopen(response) {
                    if (response.ok && (response.headers.get('content-type') || '').includes('text/event-stream')) {
                        return;
                    }
                    throw new Error('Connection failed');
                },
                onmessage(event) {
                    try {
                        const payload = JSON.parse(event.data);
                        if (isFocusRealtimePayload(payload, event)) {
                            return;
                        }

                        if (event?.event && event.event !== 'notification') {
                            return;
                        }

                        const notif = normalizeNotification(payload);
                        if (markEventAsSeen(notif.id)) return;
                        notificationsStore.addNotification(notif);
                        if (notificationReceivedCb) notificationReceivedCb(notif);
                    } catch (_) {
                        // ignore malformed events
                    }
                },
                onerror() {
                    scheduleReconnect();
                },
                onclose() {
                    scheduleReconnect();
                }
            }).catch(() => {
                scheduleReconnect();
            });
        } catch (_) {
            scheduleReconnect();
        }
    };

    const start = async () => {
        if (!auth.token) return;
        if (auth.user && auth.user.notificationsEnabled === false) {
            disconnect();
            notificationsStore.setNotifications([]);
            return;
        }
        await loadInitialNotifications();
        await connect();
    };

    const markAsRead = async (ids = []) => {
        if (!ids.length) return;
        await http.post('me/notifications/mark-read', { ids });
        notificationsStore.markAsRead(ids);
    };

    const markAllAsRead = async () => {
        await http.post('me/notifications/mark-all', {});
        notificationsStore.markAllAsRead();
    };

    watch(
        () => auth.token,
        async (token) => {
            if (token) {
                try {
                    await start();
                } catch (_) {
                    // Ignore transient failures during auth transitions
                }
            } else {
                disconnect();
                notificationsStore.setNotifications([]);
                recentEventIds.clear();
            }
        }
    );

    watch(
        () => auth.user?.notificationsEnabled,
        async (enabled) => {
            if (enabled === false) {
                disconnect();
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
        disconnect();
    });

    return {
        notifications,
        unreadCount,
        start,
        connect,
        disconnect,
        markAsRead,
        markAllAsRead,
        onNotificationReceived
    };
}
