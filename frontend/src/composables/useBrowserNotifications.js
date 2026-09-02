import { ref, computed } from 'vue';
import router from '@/router';

const STORAGE_KEY = 'dentalsoft:desktop-notifications';

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

function readPermission() {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return 'denied';
    }

    return Notification.permission;
}

export function useBrowserNotifications() {
    if (browserNotificationsInstance) {
        return browserNotificationsInstance;
    }

    const permission = ref(readPermission());
    const enabled = ref(readEnabledPreference() && permission.value === 'granted');

    const isSupported = computed(() => typeof window !== 'undefined' && 'Notification' in window);
    const canShowSystemNotifications = computed(() => isSupported.value && enabled.value && permission.value === 'granted');

    async function requestPermission() {
        if (!isSupported.value) {
            return 'denied';
        }

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

    async function setEnabled(value) {
        const nextEnabled = Boolean(value);

        if (!nextEnabled) {
            enabled.value = false;
            writeEnabledPreference(false);
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
        return true;
    }

    function showSystemNotification(notification) {
        if (!canShowSystemNotifications.value || !notification) {
            return;
        }

        const title = notification.title || 'Notification';
        const body = notification.message || '';
        const tag = notification.id != null ? `notif-${notification.id}` : `notif-${Date.now()}`;
        const link = notification.link || null;

        try {
            const systemNotification = new Notification(title, {
                body,
                icon: '/logo.png',
                tag,
                data: { link }
            });

            systemNotification.onclick = () => {
                window.focus();
                systemNotification.close();

                if (link) {
                    router.push(link).catch(() => {
                        // ignore navigation duplicates
                    });
                }
            };
        } catch (_) {
            // ignore notification display failures
        }
    }

    if (enabled.value && permission.value !== 'granted') {
        enabled.value = false;
        writeEnabledPreference(false);
    }

    browserNotificationsInstance = {
        isSupported,
        permission,
        enabled,
        canShowSystemNotifications,
        requestPermission,
        setEnabled,
        showSystemNotification
    };

    return browserNotificationsInstance;
}
