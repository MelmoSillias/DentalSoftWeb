export const APP_TOAST_EVENT = 'patient-frontend:toast';

export function emitAppToast(payload) {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(APP_TOAST_EVENT, { detail: payload }));
}
