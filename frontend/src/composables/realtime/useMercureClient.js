import { computed } from 'vue';
import { fetchEventSource } from '@microsoft/fetch-event-source';
import http from '@/service/http';
import { useAuthStore } from '@/stores/auth';
import { useRealtimeStore } from '@/stores/realtime';
import * as mercureEventRouter from './mercureEventRouter';

const STAFF_ROLES = new Set([
    'ROLE_ADMIN',
    'ROLE_RECEPTION',
    'ROLE_RECEPTIONNISTE',
    'ROLE_SECRETAIRE',
    'ROLE_MEDECIN'
]);

const TOKEN_REFRESH_BUFFER_MS = 5 * 60 * 1000;
const POLL_INTERVAL_MS = 60 * 1000;
const DISCONNECTED_POLL_THRESHOLD_MS = 30 * 1000;

let clientInstance = null;
let controller = null;
let reconnectTimer = null;
let tokenRefreshTimer = null;
let pollTimer = null;
let reconnectAttempts = 0;
let connectGeneration = 0;
let disconnectedAt = null;
let subscription = null;
let started = false;

const recentEventIds = new Set();

function parseEnvelope(raw) {
    if (raw?.v === 1 && raw?.data) {
        return {
            eventName: raw.event || raw.type,
            payload: raw.data,
            eventId: raw.id
        };
    }

    return {
        eventName: null,
        payload: raw,
        eventId: raw?.id
    };
}

function hasStaffRole(user) {
    const roles = user?.roles || [];
    return roles.some((role) => STAFF_ROLES.has(role));
}

function canUseRealtime(auth) {
    if (!auth.token) {
        return false;
    }

    if (auth.user?.notificationsEnabled === false && !hasStaffRole(auth.user)) {
        return false;
    }

    return true;
}

function markEventSeen(eventId) {
    if (!eventId) {
        return false;
    }

    const key = String(eventId);
    if (recentEventIds.has(key)) {
        return true;
    }

    recentEventIds.add(key);
    if (recentEventIds.size > 200) {
        const firstKey = recentEventIds.values().next().value;
        if (firstKey) {
            recentEventIds.delete(firstKey);
        }
    }

    return false;
}

function getReconnectDelay() {
    const base = Math.min(30000, 1000 * (2 ** reconnectAttempts));
    const jitter = Math.random() * 500;
    return base + jitter;
}

function createMercureClient() {
    const auth = useAuthStore();
    const realtimeStore = useRealtimeStore();

    function clearTimers() {
        if (reconnectTimer) {
            clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }

        if (tokenRefreshTimer) {
            clearTimeout(tokenRefreshTimer);
            tokenRefreshTimer = null;
        }
    }

    function abortConnection() {
        if (controller) {
            try {
                controller.abort();
            } catch (_) {
                // ignore
            }
            controller = null;
        }

        clearTimers();
    }

    function stopConnection() {
        connectGeneration += 1;
        abortConnection();
    }

    function stopDegradedPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
        disconnectedAt = null;
    }

    function startDegradedPolling() {
        if (pollTimer) {
            return;
        }

        pollTimer = window.setInterval(() => {
            if (!disconnectedAt || Date.now() - disconnectedAt < DISCONNECTED_POLL_THRESHOLD_MS) {
                return;
            }

            if (realtimeStore.connectionState === 'connected') {
                return;
            }

            window.dispatchEvent(new CustomEvent('mercure:degraded-poll'));
        }, POLL_INTERVAL_MS);
    }

    function scheduleReconnect() {
        if (reconnectTimer || !auth.token || !started) {
            return;
        }

        realtimeStore.setConnectionState('disconnected');
        disconnectedAt = disconnectedAt || Date.now();
        startDegradedPolling();

        reconnectTimer = window.setTimeout(() => {
            reconnectTimer = null;
            reconnectAttempts += 1;
            connect();
        }, getReconnectDelay());
    }

    function scheduleTokenRefresh(expiresAt) {
        if (tokenRefreshTimer) {
            clearTimeout(tokenRefreshTimer);
            tokenRefreshTimer = null;
        }

        if (!expiresAt) {
            return;
        }

        const expiresMs = new Date(expiresAt).getTime();
        if (Number.isNaN(expiresMs)) {
            return;
        }

        const delay = Math.max(1000, expiresMs - Date.now() - TOKEN_REFRESH_BUFFER_MS);
        tokenRefreshTimer = window.setTimeout(() => {
            tokenRefreshTimer = null;
            connect(true);
        }, delay);
    }

    async function fetchSubscription() {
        try {
            const response = await http.get('me/realtime');
            return response?.data || null;
        } catch (error) {
            const status = error?.response?.status;
            if (status === 400 || status === 401 || status === 403) {
                return null;
            }

            throw error;
        }
    }

    async function connect(forceRefresh = false) {
        if (!started || !canUseRealtime(auth)) {
            disconnect();
            return;
        }

        const generation = ++connectGeneration;
        abortConnection();
        realtimeStore.setConnectionState('connecting');

        try {
            if (!subscription || forceRefresh) {
                subscription = await fetchSubscription();
            }

            if (!subscription?.publicUrl || !subscription?.token) {
                realtimeStore.setConnectionState('disconnected');
                return;
            }

            const topics = Array.isArray(subscription.topics) && subscription.topics.length
                ? subscription.topics
                : (subscription.topic ? [subscription.topic] : []);

            if (!topics.length) {
                realtimeStore.setConnectionState('disconnected');
                return;
            }

            scheduleTokenRefresh(subscription.expiresAt);

            const url = new URL(subscription.publicUrl);
            topics.forEach((topic) => url.searchParams.append('topic', topic));

            controller = new AbortController();

            await fetchEventSource(url.toString(), {
                method: 'GET',
                headers: {
                    Authorization: `Bearer ${subscription.token}`
                },
                openWhenHidden: true,
                signal: controller.signal,
                onopen(response) {
                    if (generation !== connectGeneration) {
                        return;
                    }

                    if (response.ok && (response.headers.get('content-type') || '').includes('text/event-stream')) {
                        reconnectAttempts = 0;
                        realtimeStore.setConnectionState('connected');
                        stopDegradedPolling();
                        return;
                    }

                    throw new Error('Mercure connection failed');
                },
                onmessage(event) {
                    if (generation !== connectGeneration) {
                        return;
                    }

                    try {
                        const raw = JSON.parse(event.data || '{}');
                        const { eventName, payload, eventId } = parseEnvelope(raw);
                        const resolvedEvent = event?.event || eventName || 'message';
                        const dedupeId = eventId || event?.id || event?.lastEventId;

                        if (markEventSeen(dedupeId)) {
                            return;
                        }

                        mercureEventRouter.emit(resolvedEvent, payload, event);
                    } catch (_) {
                        // ignore malformed payloads
                    }
                },
                onerror() {
                    if (generation !== connectGeneration) {
                        return;
                    }

                    realtimeStore.setConnectionState('error');
                    scheduleReconnect();
                },
                onclose() {
                    if (generation !== connectGeneration) {
                        return;
                    }

                    scheduleReconnect();
                }
            }).catch(() => {
                if (generation !== connectGeneration) {
                    return;
                }

                realtimeStore.setConnectionState('error');
                scheduleReconnect();
            });
        } catch (_) {
            if (generation !== connectGeneration) {
                return;
            }

            realtimeStore.setConnectionState('error');
            scheduleReconnect();
        }
    }

    function disconnect() {
        started = false;
        stopConnection();
        subscription = null;
        recentEventIds.clear();
        stopDegradedPolling();
        realtimeStore.setConnectionState('disconnected');
    }

    async function start() {
        if (!canUseRealtime(auth)) {
            disconnect();
            return;
        }

        started = true;
        await connect(true);
    }

    function handleVisibilityChange() {
        if (document.visibilityState !== 'visible' || !started || !auth.token) {
            return;
        }

        if (realtimeStore.connectionState !== 'connected') {
            connect(true);
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    return {
        start,
        connect,
        disconnect,
        on: mercureEventRouter.on,
        off: mercureEventRouter.off,
        connectionState: computed(() => realtimeStore.connectionState),
        isConnected: computed(() => realtimeStore.isConnected),
        hasConnectionIssue: computed(() => realtimeStore.hasConnectionIssue)
    };
}

export function useMercureClient() {
    if (!clientInstance) {
        clientInstance = createMercureClient();
    }

    return clientInstance;
}

export function resetMercureClient() {
    if (clientInstance) {
        clientInstance.disconnect();
    }

    mercureEventRouter.clearAllListeners();
    clientInstance = null;
}
