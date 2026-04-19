import { fetchEventSource } from '@microsoft/fetch-event-source';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';

export function useFocusRealtime(onEvent) {
    const auth = useAuthStore();
    const realtimeEnabled = ref(false);

    let controller = null;
    let reconnectTimer = null;

    const mercureConfig = computed(() => auth.mercure || null);

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
            signal: controller.signal,
            onopen(response) {
                if (response.ok && (response.headers.get('content-type') || '').includes('text/event-stream')) {
                    return;
                }
                throw new Error('Mercure focus connection failed');
            },
            onmessage(event) {
                if (event.event !== 'focus-consultation') {
                    return;
                }

                try {
                    const payload = JSON.parse(event.data || '{}');
                    if (typeof onEvent === 'function') {
                        onEvent(payload);
                    }
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

    onUnmounted(() => {
        disconnect();
    });

    return {
        realtimeEnabled,
        connect,
        disconnect
    };
}