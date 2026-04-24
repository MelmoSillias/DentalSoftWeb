import { fetchEventSource } from '@microsoft/fetch-event-source';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function useFocusRealtime(onEvent) {
    const auth = useAuthStore();
    const realtimeEnabled = ref(false);

    let controller = null;
    let reconnectTimer = null;
    let refreshInFlight = null;
    let refreshQueued = false;
    const recentEventIds = new Set();

    const mercureConfig = computed(() => auth.mercure || null);

    const isFocusRealtimePayload = (payload, event) => {
        if (event?.event === 'focus-consultation') {
            return true;
        }

        if (typeof event?.id === 'string' && event.id.startsWith('focus-consultation-')) {
            return true;
        }

        return ['consultation', 'patient', 'devis', 'payment'].includes(payload?.entity) && typeof payload?.action === 'string';
    };

    const markEventAsSeen = (event) => {
        const eventId = event?.id || event?.lastEventId || null;
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
    };

    const runRefresh = async (payload) => {
        if (refreshInFlight) {
            refreshQueued = true;
            return refreshInFlight;
        }

        refreshInFlight = Promise.resolve()
            .then(() => (typeof onEvent === 'function' ? onEvent(payload) : undefined))
            .finally(async () => {
                refreshInFlight = null;
                if (refreshQueued) {
                    refreshQueued = false;
                    await runRefresh(payload);
                }
            });

        return refreshInFlight;
    };

    const disconnect = () => {
        if (controller) {
            try {
                controller.abort();
            } catch (_) {
                // ignore
            }
            controller = null;
        }

        if (reconnectTimer) {
            window.clearTimeout(reconnectTimer);
            reconnectTimer = null;
        }

        refreshQueued = false;
    };

    const scheduleReconnect = () => {
        if (reconnectTimer || !realtimeEnabled.value || !auth.token) {
            return;
        }

        reconnectTimer = window.setTimeout(() => {
            reconnectTimer = null;
            connect();
        }, 5000);
    };

    const connect = async () => {
        if (!realtimeEnabled.value || !auth.token) {
            disconnect();
            return;
        }

        const config = mercureConfig.value;
        if (!config?.publicUrl || !config?.topic || !config?.token) {
            disconnect();
            return;
        }

        disconnect();

        const url = new URL(config.publicUrl);
        url.searchParams.append('topic', config.topic);

        controller = new AbortController();

        fetchEventSource(url.toString(), {
            method: 'GET',
            headers: {
                Authorization: `Bearer ${config.token}`
            },
            openWhenHidden: true,
            signal: controller.signal,
            onopen(response) {
                if (response.ok && (response.headers.get('content-type') || '').includes('text/event-stream')) {
                    runRefresh({ reason: 'focus-realtime-connected' });
                    return;
                }
                throw new Error('Mercure focus connection failed');
            },
            onmessage(event) {
                try {
                    const payload = JSON.parse(event.data || '{}');
                    if (!isFocusRealtimePayload(payload, event)) {
                        return;
                    }

                    if (markEventAsSeen(event)) {
                        return;
                    }

                    runRefresh(payload);
                } catch (_) {
                    // ignore malformed focus payloads
                }
            },
            onclose() {
                scheduleReconnect();
            },
            onerror() {
                scheduleReconnect();
            }
        }).catch(() => {
            scheduleReconnect();
        });
    };

    watch(
        () => [auth.token, mercureConfig.value?.publicUrl, mercureConfig.value?.topic, mercureConfig.value?.token, realtimeEnabled.value],
        () => {
            if (realtimeEnabled.value) {
                connect();
                return;
            }
            disconnect();
        },
        { immediate: true }
    );

    const handleVisibilityChange = () => {
        if (document.visibilityState !== 'visible' || !realtimeEnabled.value || !auth.token) {
            return;
        }

        connect();
    };

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    onUnmounted(() => {
        disconnect();
        if (typeof document !== 'undefined') {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        }
    });

    return {
        realtimeEnabled,
        connect,
        disconnect
    };
}