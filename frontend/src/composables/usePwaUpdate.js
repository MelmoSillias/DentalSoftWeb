import { registerSW } from 'virtual:pwa-register';
import { onMounted, readonly, ref } from 'vue';
import { logAppError, devDebug } from '@/utils/appLogger';

const UPDATE_CHECK_INTERVAL_MS = 30 * 60 * 1000;

const needRefresh = ref(false);
const offlineReady = ref(false);

let updateSW = null;
let registered = false;
let checkTimerId = null;
let registrationRef = null;

function checkForUpdates() {
    if (!registrationRef || typeof registrationRef.update !== 'function') {
        return;
    }

    registrationRef.update().catch((error) => {
        logAppError('pwa.registration.update', error);
    });
}

function syncWaitingState() {
    if (registrationRef?.waiting) {
        needRefresh.value = true;
    }
}

function onVisibilityOrFocus() {
    if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        return;
    }

    syncWaitingState();
    checkForUpdates();
}

function ensureRegistered() {
    if (registered || typeof window === 'undefined' || !('serviceWorker' in navigator)) {
        return;
    }

    registered = true;

    updateSW = registerSW({
        immediate: true,
        onNeedRefresh() {
            needRefresh.value = true;
        },
        onOfflineReady() {
            offlineReady.value = true;
            devDebug('Application prête hors-ligne.');
        },
        onRegisteredSW(_swUrl, registration) {
            registrationRef = registration || null;
            syncWaitingState();
            checkForUpdates();
        },
        onRegisterError(error) {
            logAppError('pwa.registerSW', error);
        }
    });

    if (checkTimerId == null) {
        checkTimerId = window.setInterval(checkForUpdates, UPDATE_CHECK_INTERVAL_MS);
    }

    document.addEventListener('visibilitychange', onVisibilityOrFocus);
    window.addEventListener('focus', onVisibilityOrFocus);
}

/**
 * Enregistre le service worker (une seule fois) et expose l'état de mise à jour PWA.
 */
export function usePwaUpdate() {
    onMounted(() => {
        ensureRegistered();
    });

    async function applyUpdate() {
        if (typeof updateSW !== 'function') {
            window.location.reload();
            return;
        }

        try {
            await updateSW(true);
        } catch (error) {
            logAppError('pwa.applyUpdate', error);
            window.location.reload();
        }
    }

    function dismissUpdate() {
        needRefresh.value = false;
    }

    return {
        needRefresh: readonly(needRefresh),
        offlineReady: readonly(offlineReady),
        applyUpdate,
        dismissUpdate
    };
}
