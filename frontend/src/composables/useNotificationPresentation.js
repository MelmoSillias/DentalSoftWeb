import { onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useMercureNotifications } from '@/composables/useMercureNotifications';
import { useBrowserNotifications } from '@/composables/useBrowserNotifications';

let presentationInstance = null;

function isTabActiveForInAppPresentation() {
    if (typeof document === 'undefined') {
        return true;
    }

    return !document.hidden && document.hasFocus();
}

export function useNotificationPresentation() {
    if (presentationInstance) {
        return presentationInstance;
    }

    const auth = useAuthStore();
    const { onNotificationReceived } = useMercureNotifications();
    const browserNotifications = useBrowserNotifications();

    function shouldShowInApp() {
        return isTabActiveForInAppPresentation();
    }

    function shouldShowSystemNotification() {
        if (auth.user?.notificationsEnabled === false) {
            return false;
        }

        return !isTabActiveForInAppPresentation() && browserNotifications.canShowSystemNotifications.value;
    }

    function handleNotificationPresentation(notification) {
        if (auth.user?.notificationsEnabled === false) {
            return;
        }

        if (shouldShowInApp()) {
            return;
        }

        if (shouldShowSystemNotification()) {
            browserNotifications.showSystemNotification(notification);
        }
    }

    const unsubscribe = onNotificationReceived(handleNotificationPresentation);

    onUnmounted(() => {
        unsubscribe();
        presentationInstance = null;
    });

    presentationInstance = {
        shouldShowInApp,
        shouldShowSystemNotification
    };

    return presentationInstance;
}
