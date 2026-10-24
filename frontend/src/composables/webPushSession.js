import { apiPrefix } from '@/config';
import { getDeviceMetadata } from '@/utils/deviceFingerprint';

const ENDPOINT_KEY = 'dentalsoft:web-push-endpoint';

function readStoredEndpoint() {
    try {
        return window.localStorage.getItem(ENDPOINT_KEY);
    } catch (_) {
        return null;
    }
}

export function rememberPushEndpoint(endpoint) {
    if (!endpoint || typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(ENDPOINT_KEY, endpoint);
    } catch (_) {
        // ignore storage failures
    }
}

export function forgetPushEndpoint() {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.removeItem(ENDPOINT_KEY);
    } catch (_) {
        // ignore storage failures
    }
}

function headerValue(value) {
    return String(value || '')
        .replace(/[^\x20-\x7E]/g, '')
        .slice(0, 180);
}

function deleteServerSubscription(token, endpoint) {
    if (!token || !endpoint || typeof fetch !== 'function') {
        return;
    }

    let device = { id: '', name: '', type: 'desktop' };
    try {
        device = getDeviceMetadata();
    } catch (_) {
        // device metadata is optional for this best-effort call
    }

    fetch(`${apiPrefix}/me/push/subscription`, {
        method: 'DELETE',
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
            'X-Device-Id': headerValue(device.id),
            'X-Device-Name': headerValue(device.name),
            'X-Device-Type': headerValue(device.type)
        },
        body: JSON.stringify({ endpoint }),
        keepalive: true
    }).catch(() => {});
}

/**
 * Retire l'abonnement de ce navigateur tant que le JWT est encore connu.
 * À appeler avant d'effacer le token de session.
 */
export function releasePushSubscription(token) {
    const storedEndpoint = readStoredEndpoint();
    forgetPushEndpoint();
    deleteServerSubscription(token, storedEndpoint);

    if (typeof navigator === 'undefined' || !navigator.serviceWorker) {
        return;
    }

    navigator.serviceWorker.ready
        .then((registration) => registration.pushManager.getSubscription())
        .then(async (subscription) => {
            if (!subscription) {
                return;
            }

            if (subscription.endpoint && subscription.endpoint !== storedEndpoint) {
                deleteServerSubscription(token, subscription.endpoint);
            }

            await subscription.unsubscribe();
        })
        .catch(() => {});
}
