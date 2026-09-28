import { readonly, ref } from 'vue';

const isOffline = ref(typeof navigator !== 'undefined' && navigator.onLine === false);
const justRestored = ref(false);
const offlineCause = ref(isOffline.value ? 'browser' : null);

let restoredTimer = null;
let probeTimer = null;
let sawOutage = isOffline.value;

function clearRestoredTimer() {
    if (restoredTimer) {
        clearTimeout(restoredTimer);
        restoredTimer = null;
    }
}

function syncDocumentClass() {
    if (typeof document === 'undefined') {
        return;
    }

    document.documentElement.classList.toggle('is-connection-offline', isOffline.value);
    document.documentElement.classList.toggle('is-connection-restored', justRestored.value && !isOffline.value);
}

export function markOffline(cause = 'browser') {
    clearRestoredTimer();
    justRestored.value = false;

    if (!isOffline.value || cause === 'browser') {
        offlineCause.value = cause;
    }

    isOffline.value = true;
    sawOutage = true;
    syncDocumentClass();
}

export function markOnline() {
    if (!isOffline.value) {
        return;
    }

    isOffline.value = false;
    offlineCause.value = null;

    if (!sawOutage) {
        syncDocumentClass();
        return;
    }

    justRestored.value = true;
    syncDocumentClass();
    restoredTimer = setTimeout(() => {
        justRestored.value = false;
        restoredTimer = null;
        syncDocumentClass();
    }, 4500);
}

export function noteTransportSuccess() {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        return;
    }

    markOnline();
}

export function noteHttpFailure(error) {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        markOffline('browser');
        return;
    }

    if (!error || error.response) {
        return;
    }

    if (error.code === 'ECONNABORTED' || error.code === 'ERR_CANCELED') {
        return;
    }

    markOffline('transport');
}

function scheduleReachabilityProbe() {
    if (probeTimer || isOffline.value) {
        return;
    }

    probeTimer = setTimeout(() => {
        probeTimer = null;
        void confirmServerReachable();
    }, 700);
}

async function confirmServerReachable() {
    if (isOffline.value) {
        return;
    }

    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        markOffline('browser');
        return;
    }

    try {
        const { default: http } = await import('@/service/http');
        await http.get('settings/general/public', {
            timeout: 4000,
            validateStatus: () => true,
            networkProbe: true
        });
    } catch {
        markOffline(typeof navigator !== 'undefined' && navigator.onLine === false ? 'browser' : 'transport');
    }
}

export function noteRealtimeInterrupted() {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        markOffline('browser');
        return;
    }

    scheduleReachabilityProbe();
}

if (typeof window !== 'undefined') {
    window.addEventListener('offline', () => markOffline('browser'));
    window.addEventListener('online', () => markOnline());
    syncDocumentClass();
}

export function useNetworkStatus() {
    return {
        isOffline: readonly(isOffline),
        justRestored: readonly(justRestored),
        offlineCause: readonly(offlineCause)
    };
}
