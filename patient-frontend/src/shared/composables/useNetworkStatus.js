import { readonly, ref } from 'vue';

const isOffline = ref(typeof navigator !== 'undefined' && navigator.onLine === false);
const justRestored = ref(false);
const offlineCause = ref(isOffline.value ? 'browser' : null);

let restoredTimer = null;
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

export function noteHttpFailure() {
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
        markOffline('browser');
        return;
    }

    markOffline('transport');
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
