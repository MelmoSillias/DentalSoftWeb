// service/http.js
// Client Axios centralisé avec intercepteurs pour gestion automatique du token et du logout sur 401
import axios from 'axios';
import { apiPrefix } from '@/config';
import { noteHttpFailure, noteTransportSuccess } from '@/composables/useNetworkStatus';
import { getDeviceMetadata } from '@/utils/deviceFingerprint';
import { beginMutationPreparation, reportMutationIssue, runMutation } from '@/composables/useMutationStatus';
import { isMutation, stableValue } from './mutationGuard.mjs';

/** Timeout par défaut pour la plupart des requêtes API (20 s). */
export const REQUEST_TIMEOUT_MS = 20000;

/** Timeout étendu pour les listes volumineuses (caisse, exports, etc.). */
export const HEAVY_REQUEST_TIMEOUT_MS = 60000;

/** Timeout pour les uploads de fichiers (images médicales, archives, photos). */
export const UPLOAD_REQUEST_TIMEOUT_MS = 600000;

const transport = axios.getAdapter(axios.defaults.adapter);
const fileIdentities = new WeakMap();
let nextFileIdentity = 0;

async function mutationBody(data) {
    if (data instanceof FormData) {
        const entries = [];
        for (const [name, value] of data.entries()) {
            if (value instanceof Blob) {
                // Hash actual bytes: different files with identical names must stay distinct.
                if (!globalThis.crypto?.subtle) {
                    if (!fileIdentities.has(value)) fileIdentities.set(value, ++nextFileIdentity);
                    entries.push([name, 'local-file', fileIdentities.get(value)]);
                    continue;
                }
                const hash = await crypto.subtle.digest('SHA-256', await value.arrayBuffer());
                entries.push([name, value.name, value.type, Array.from(new Uint8Array(hash))]);
            } else entries.push([name, value]);
        }
        return entries;
    }
    if (typeof data === 'string') {
        try {
            return stableValue(JSON.parse(data));
        } catch {
            return data;
        }
    }
    return stableValue(data ?? null);
}

async function guardedTransport(config) {
    if (!isMutation(config.method)) return transport(config);
    const finish = beginMutationPreparation();
    try {
        const body = await mutationBody(config.data);
        const identity = JSON.stringify([config.headers.Authorization || '', config.method, config.baseURL, config.url, stableValue(config.params), body]);
        return await runMutation(identity, (key) => {
            config.headers.set('Idempotency-Key', key);
            return transport(config);
        });
    } finally {
        finish();
    }
}

const isUploadRequest = (config) => {
    if (config?.data instanceof FormData) {
        return true;
    }

    const contentType = config?.headers?.['Content-Type'] ?? config?.headers?.['content-type'] ?? '';
    return typeof contentType === 'string' && contentType.includes('multipart/form-data');
};

const isLoginRequest = (config) => /(?:^|\/)login_check(?:\?|$)/.test(config?.url || '');

const userFacingMessages = {
    slow: "Connexion au serveur impossible. Vérifiez votre connexion internet : il ne s'agit pas d'une erreur de l'application.",
    unauthorized: 'Votre session a expire. Veuillez vous reconnecter.',
    forbidden: 'Vous n avez pas l autorisation necessaire pour cette action.',
    unavailable: 'Le service est temporairement indisponible. Veuillez reessayer dans un instant.',
    missing: 'Le service demande est temporairement indisponible.',
    generic: 'Une erreur est survenue. Veuillez reessayer dans un instant.'
};

const extractPayloadMessage = (error) => {
    const payload = error?.response?.data;
    if (!payload) {
        return '';
    }

    if (typeof payload === 'string') {
        return payload.trim();
    }

    return payload.error || payload.message || payload.detail || '';
};

const isConnectionIssue = (error) => {
    if (!error) {
        return false;
    }

    if (!error.response) {
        return true;
    }

    return ['ECONNABORTED', 'ERR_NETWORK', 'ETIMEDOUT'].includes(error.code);
};

export const isDeviceNotAllowedError = (error) => error?.response?.status === 403 && error?.response?.data?.error === 'device_not_allowed';

export const getHttpErrorMessage = (error, fallback = userFacingMessages.generic) => {
    const status = error?.response?.status;
    const payloadMessage = extractPayloadMessage(error);

    if (isMutation(error?.config?.method) && !isLoginRequest(error.config) && (!error.response || status >= 500)) {
        return 'La confirmation du serveur n’a pas été reçue. Vérifiez les données enregistrées avant de réessayer. Si nécessaire, relancez la même action sans modifier les informations.';
    }

    if (isConnectionIssue(error)) {
        if (typeof navigator !== 'undefined' && navigator.onLine === false) {
            return "Votre connexion internet est coupée. Ce n'est pas une erreur de l'application.";
        }

        if (error?.code === 'ECONNABORTED' || error?.code === 'ETIMEDOUT') {
            return 'Le serveur met trop de temps à répondre. Veuillez réessayer dans un instant.';
        }

        return userFacingMessages.slow;
    }

    if (status === 401) {
        if (isLoginRequest(error.config)) return 'Identifiants incorrects.';
        return userFacingMessages.unauthorized;
    }

    if (status === 403) {
        return userFacingMessages.forbidden;
    }

    if (status === 404) {
        return userFacingMessages.missing;
    }

    if (status === 422 || status === 409 || status === 400) {
        return payloadMessage || fallback;
    }

    if (status >= 500) {
        return userFacingMessages.unavailable;
    }

    return payloadMessage || fallback;
};

const normalizeHttpError = (error) => {
    const userMessage = getHttpErrorMessage(error);
    error.userMessage = userMessage;
    error.rawMessage = error.message;
    error.isConnectionIssue = isConnectionIssue(error);
    error.message = userMessage;
    return error;
};

/** Lazy imports to avoid circular deps: http ↔ auth ↔ router ↔ AppLayout ↔ smsService */
const loadAuthStore = () => import('@/stores/auth').then((m) => m.useAuthStore());
const loadRouter = () => import('@/router').then((m) => m.default);

const http = axios.create({
    baseURL: apiPrefix,
    adapter: guardedTransport,
    timeout: REQUEST_TIMEOUT_MS
});

// Intercepteur requêtes: ajoute Authorization si token présent
http.interceptors.request.use(
    (config) => {
        config.headers = config.headers || {};

        try {
            const token = localStorage.getItem('token');
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }

            const device = getDeviceMetadata();
            config.headers['X-Device-Id'] = device.id;
            config.headers['X-Device-Name'] = device.name;
            config.headers['X-Device-Type'] = device.type;
        } catch {
            // localStorage / device metadata indisponible
        }

        if (isUploadRequest(config)) {
            config.timeout = Math.max(config.timeout ?? 0, UPLOAD_REQUEST_TIMEOUT_MS);
        } else if (isMutation(config.method) && config.timeout === REQUEST_TIMEOUT_MS) {
            config.timeout = HEAVY_REQUEST_TIMEOUT_MS;
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// Intercepteur réponses: gère 401 -> logout + redirection login
http.interceptors.response.use(
    (response) => {
        noteTransportSuccess();
        return response;
    },
    (error) => {
        normalizeHttpError(error);
        if (isMutation(error?.config?.method) && !isLoginRequest(error.config) && (!error.response || error.response.status >= 500)) {
            reportMutationIssue(error);
        }

        if (!error?.config?.networkProbe) {
            noteHttpFailure(error);
        }

        if (isDeviceNotAllowedError(error)) {
            Promise.all([loadAuthStore(), loadRouter()])
                .then(([useAuthStore, router]) => {
                    try {
                        useAuthStore()?.setDeviceBlock(error.response?.data?.message, error.response?.data?.status);
                    } catch {
                        // Pinia pas initialisé
                    }
                    if (router.currentRoute.value.name !== 'devicePending') {
                        router.replace({ name: 'devicePending' });
                    }
                })
                .catch(() => {});
            return Promise.reject(error);
        }

        // Si réponse HTTP et statut 401 => token invalide/expiré
        if (error.response && error.response.status === 401 && !isLoginRequest(error.config)) {
            const failedAuthorization = error.config?.headers?.Authorization;
            Promise.all([loadAuthStore(), loadRouter()])
                .then(([useAuthStore, router]) => {
                    if (failedAuthorization !== `Bearer ${localStorage.getItem('token')}`) return;
                    try {
                        useAuthStore()?.logout();
                    } catch {
                        localStorage.removeItem('token');
                    }
                    if (router.currentRoute.value.name !== 'login') {
                        router.replace({ name: 'login' });
                    }
                })
                .catch(() => {
                    localStorage.removeItem('token');
                });
        }
        return Promise.reject(error);
    }
);

export default http;
