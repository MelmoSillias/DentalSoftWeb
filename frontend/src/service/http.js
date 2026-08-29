// service/http.js
// Client Axios centralisé avec intercepteurs pour gestion automatique du token et du logout sur 401
import axios from 'axios';
import { apiPrefix } from '@/config';
import { getDeviceMetadata } from '@/utils/deviceFingerprint';

/** Timeout par défaut pour la plupart des requêtes API (20 s). */
export const REQUEST_TIMEOUT_MS = 20000;

/** Timeout étendu pour les listes volumineuses (caisse, exports, etc.). */
export const HEAVY_REQUEST_TIMEOUT_MS = 60000;

/** Timeout pour les uploads de fichiers (images médicales, archives, photos). */
export const UPLOAD_REQUEST_TIMEOUT_MS = 600000;

const isUploadRequest = (config) => {
    if (config?.data instanceof FormData) {
        return true;
    }

    const contentType = config?.headers?.['Content-Type'] ?? config?.headers?.['content-type'] ?? '';
    return typeof contentType === 'string' && contentType.includes('multipart/form-data');
};

const userFacingMessages = {
    slow: 'Connexion au serveur impossible ou bloquee (reseau/CORS). Veuillez reessayer dans un instant.',
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

export const isDeviceNotAllowedError = (error) =>
    error?.response?.status === 403 && error?.response?.data?.error === 'device_not_allowed';

export const getHttpErrorMessage = (error, fallback = userFacingMessages.generic) => {
    const status = error?.response?.status;
    const payloadMessage = extractPayloadMessage(error);

    if (isConnectionIssue(error)) {
        return userFacingMessages.slow;
    }

    if (status === 401) {
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
        } catch (_) {
            // localStorage / device metadata indisponible
        }

        if (isUploadRequest(config)) {
            config.timeout = Math.max(config.timeout ?? 0, UPLOAD_REQUEST_TIMEOUT_MS);
        }

        return config;
    },
    (error) => Promise.reject(error)
);

// Intercepteur réponses: gère 401 -> logout + redirection login
http.interceptors.response.use(
    (response) => response,
    (error) => {
        normalizeHttpError(error);

        if (isDeviceNotAllowedError(error)) {
            Promise.all([loadAuthStore(), loadRouter()])
                .then(([useAuthStore, router]) => {
                    try {
                        useAuthStore()?.setDeviceBlock(
                            error.response?.data?.message,
                            error.response?.data?.status
                        );
                    } catch (_) {
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
        if (error.response && error.response.status === 401) {
            Promise.all([loadAuthStore(), loadRouter()])
                .then(([useAuthStore, router]) => {
                    try {
                        useAuthStore()?.logout();
                    } catch (_) {
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
