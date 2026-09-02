import { computed, onUnmounted, ref, watch } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useMedecinsStore } from '@/stores/medecins';
import { usePaymentMethodsStore } from '@/stores/paymentMethods';
import { useMercureClient } from '@/composables/realtime/useMercureClient';

const FOCUS_ENTITIES = new Set(['consultation', 'patient', 'devis', 'payment', 'medecin', 'payment_method', 'facture']);

export function useFocusRealtime(onEvent) {
    const auth = useAuthStore();
    const medecinsStore = useMedecinsStore();
    const paymentMethodsStore = usePaymentMethodsStore();
    const mercureClient = useMercureClient();
    const realtimeEnabled = ref(true);

    let refreshInFlight = null;
    let refreshQueued = false;

    const connectionState = mercureClient.connectionState;

    const isFocusPayload = (payload) => {
        return FOCUS_ENTITIES.has(payload?.entity) && typeof payload?.action === 'string';
    };

    const refreshReferenceStores = async (payload) => {
        if (!payload?.entity || !auth.token) {
            return;
        }

        if (payload.entity === 'medecin') {
            medecinsStore.invalidate();
            await medecinsStore.load(auth.token, { force: true });
            return;
        }

        if (payload.entity === 'payment_method') {
            paymentMethodsStore.invalidate();
            await paymentMethodsStore.load(auth.token, { force: true });
        }
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

    const handleFocusEvent = (payload) => {
        if (!realtimeEnabled.value) {
            return;
        }

        if (!isFocusPayload(payload)) {
            return;
        }

        refreshReferenceStores(payload).catch(() => {
            // ignore reference refresh errors from realtime events
        });

        runRefresh(payload);
    };

    const unsubscribeFocus = mercureClient.on('focus-*', handleFocusEvent);

    watch(
        () => realtimeEnabled.value,
        (enabled) => {
            if (!enabled) {
                refreshQueued = false;
            }
        }
    );

    onUnmounted(() => {
        unsubscribeFocus();
        refreshQueued = false;
    });

    return {
        realtimeEnabled,
        connectionState,
        connect: () => mercureClient.connect(true),
        disconnect: () => {
            realtimeEnabled.value = false;
        }
    };
}
