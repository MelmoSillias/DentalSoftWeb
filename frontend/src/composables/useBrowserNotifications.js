import { ref, computed } from 'vue';
import { navigateToNotificationLink, resolveNotificationLink } from '@/utils/notificationLinks';
import { apiPrefix } from '@/config';
import http from '@/service/http';
import router from '@/router';
import { forgetPushEndpoint, rememberPushEndpoint, releasePushSubscription } from '@/composables/webPushSession';

const STORAGE_KEY = 'dentalsoft:desktop-notifications';
const SERVICE_WORKER_TIMEOUT_MS = 4000;

let browserNotificationsInstance = null;

function readEnabledPreference() {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        return window.localStorage.getItem(STORAGE_KEY) === 'true';
    } catch (_) {
        return false;
    }
}

function writeEnabledPreference(enabled) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(STORAGE_KEY, enabled ? 'true' : 'false');
    } catch (_) {
        // ignore storage failures
    }
}

function readToken() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        return window.localStorage.getItem('token');
    } catch (_) {
        return null;
    }
}

function readPermission() {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return 'denied';
    }

    return Notification.permission;
}

function canUsePushApi() {
    return typeof window !== 'undefined' && window.isSecureContext === true && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = `${base64String}${padding}`.replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for (let index = 0; index < raw.length; index += 1) {
        output[index] = raw.charCodeAt(index);
    }

    return output;
}

function sameKey(left, right) {
    if (!left || !right) {
        return false;
    }

    const current = new Uint8Array(left);
    const expected = right instanceof Uint8Array ? right : new Uint8Array(right);
    if (current.byteLength !== expected.byteLength) {
        return false;
    }

    for (let index = 0; index < current.byteLength; index += 1) {
        if (current[index] !== expected[index]) {
            return false;
        }
    }

    return true;
}

function waitForServiceWorker() {
    return new Promise((resolve, reject) => {
        const timer = window.setTimeout(() => reject(new Error('service-worker-timeout')), SERVICE_WORKER_TIMEOUT_MS);
        navigator.serviceWorker.ready
            .then((registration) => {
                window.clearTimeout(timer);
                resolve(registration);
            })
            .catch((error) => {
                window.clearTimeout(timer);
                reject(error);
            });
    });
}

function buildNotificationOptions(notification) {
    const body = notification.message || '';
    const tag = notification.id != null ? `notif-${notification.id}` : `notif-${Date.now()}`;

    return {
        body,
        icon: '/logo.png',
        tag,
        data: { link: resolveNotificationLink(notification.link) || null }
    };
}

async function showViaServiceWorker(title, options) {
    if (!('serviceWorker' in navigator)) {
        return false;
    }

    const registration = await navigator.serviceWorker.getRegistration();
    if (!registration) {
        return false;
    }

    await registration.showNotification(title, options);
    return true;
}

function showViaNotificationApi(title, options, link) {
    const systemNotification = new Notification(title, options);

    systemNotification.onclick = () => {
        window.focus();
        systemNotification.close();

        if (link) {
            navigateToNotificationLink(router, link);
        }
    };
}

async function subscribeAndStore() {
    if (!canUsePushApi()) {
        return false;
    }

    const response = await http.get(`${apiPrefix}/me/push/config`);
    const publicKey = response.data?.publicKey;
    if (!response.data?.configured || typeof publicKey !== 'string' || publicKey === '') {
        return false;
    }

    const registration = await waitForServiceWorker();
    const applicationServerKey = urlBase64ToUint8Array(publicKey);
    let subscription = await registration.pushManager.getSubscription();
    if (subscription && !sameKey(subscription.options?.applicationServerKey, applicationServerKey)) {
        await subscription.unsubscribe();
        subscription = null;
    }

    if (!subscription) {
        subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey
        });
    }

    const body = subscription.toJSON();
    const supportedEncodings = registration.pushManager.supportedContentEncodings;
    const encodings = supportedEncodings ? Array.from(supportedEncodings) : ['aes128gcm'];
    body.contentEncoding = encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0] || 'aes128gcm';

    try {
        await http.put(`${apiPrefix}/me/push/subscription`, body);
    } catch (error) {
        await subscription.unsubscribe();
        forgetPushEndpoint();
        throw error;
    }

    rememberPushEndpoint(subscription.endpoint);
    return true;
}

export function useBrowserNotifications() {
    if (browserNotificationsInstance) {
        return browserNotificationsInstance;
    }

    const permission = ref(readPermission());
    const enabled = ref(readEnabledPreference() && permission.value === 'granted');
    const hasPushSubscription = ref(false);

    const isSupported = computed(() => typeof window !== 'undefined' && 'Notification' in window);
    const canShowSystemNotifications = computed(() => isSupported.value && enabled.value && permission.value === 'granted');

    function syncPermissionFromBrowser() {
        permission.value = readPermission();

        if (permission.value !== 'granted' && enabled.value) {
            enabled.value = false;
            hasPushSubscription.value = false;
            writeEnabledPreference(false);
            releasePushSubscription(readToken());
        }
    }

    async function requestPermission() {
        if (!isSupported.value) {
            return 'denied';
        }

        syncPermissionFromBrowser();

        if (permission.value === 'granted') {
            return 'granted';
        }

        if (permission.value === 'denied') {
            return 'denied';
        }

        const result = await Notification.requestPermission();
        permission.value = result;
        return result;
    }

    async function syncSubscription() {
        if (!readEnabledPreference() || readPermission() !== 'granted' || !canUsePushApi()) {
            hasPushSubscription.value = false;
            return false;
        }

        try {
            const subscribed = await subscribeAndStore();
            hasPushSubscription.value = subscribed;
            return subscribed;
        } catch (error) {
            hasPushSubscription.value = false;
            if (error?.name === 'NotAllowedError') {
                enabled.value = false;
                writeEnabledPreference(false);
                permission.value = readPermission();
            }
            return false;
        }
    }

    async function setEnabled(value) {
        const nextEnabled = Boolean(value);

        if (!nextEnabled) {
            enabled.value = false;
            hasPushSubscription.value = false;
            writeEnabledPreference(false);
            releasePushSubscription(readToken());
            return true;
        }

        if (!isSupported.value) {
            enabled.value = false;
            writeEnabledPreference(false);
            return false;
        }

        const result = await requestPermission();
        if (result !== 'granted') {
            enabled.value = false;
            writeEnabledPreference(false);
            return false;
        }

        enabled.value = true;
        writeEnabledPreference(true);

        if (!canUsePushApi()) {
            hasPushSubscription.value = false;
            return true;
        }

        try {
            hasPushSubscription.value = await subscribeAndStore();
        } catch (error) {
            hasPushSubscription.value = false;
            if (error?.name === 'NotAllowedError') {
                enabled.value = false;
                writeEnabledPreference(false);
                permission.value = readPermission();
                return false;
            }
        }

        return true;
    }

    function markPushSubscriptionReleased() {
        hasPushSubscription.value = false;
    }

    async function showSystemNotification(notification) {
        if (!canShowSystemNotifications.value || !notification || hasPushSubscription.value) {
            return;
        }

        const title = notification.title || 'Notification';
        const options = buildNotificationOptions(notification);
        const link = resolveNotificationLink(notification.link);

        try {
            const shown = await showViaServiceWorker(title, options);
            if (shown) {
                return;
            }
        } catch (_) {
            // fallback below
        }

        try {
            showViaNotificationApi(title, options, link);
        } catch (_) {
            // ignore notification display failures
        }
    }

    if (enabled.value && permission.value !== 'granted') {
        enabled.value = false;
        writeEnabledPreference(false);
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', syncPermissionFromBrowser);
    }

    if (enabled.value && canUsePushApi()) {
        waitForServiceWorker()
            .then((registration) => registration.pushManager.getSubscription())
            .then((subscription) => {
                hasPushSubscription.value = Boolean(subscription);
                if (subscription?.endpoint) {
                    rememberPushEndpoint(subscription.endpoint);
                }
            })
            .catch(() => {
                hasPushSubscription.value = false;
            });
    }

    browserNotificationsInstance = {
        isSupported,
        permission,
        enabled,
        hasPushSubscription,
        canShowSystemNotifications,
        requestPermission,
        setEnabled,
        syncSubscription,
        markPushSubscriptionReleased,
        showSystemNotification,
        syncPermissionFromBrowser
    };

    return browserNotificationsInstance;
}
