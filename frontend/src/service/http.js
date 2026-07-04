// service/http.js
// Client Axios centralisé avec intercepteurs pour gestion automatique du token et du logout sur 401
import axios from 'axios';
import { apiPrefix } from '@/config';
import { useAuthStore } from '@/stores/auth';
import router from '@/router';
import { getDeviceMetadata } from '@/utils/deviceFingerprint';

/** Timeout par défaut pour la plupart des requêtes API (20 s). */
export const REQUEST_TIMEOUT_MS = 20000;

/** Timeout étendu pour les listes volumineuses (caisse, exports, etc.). */
export const HEAVY_REQUEST_TIMEOUT_MS = 60000;

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

const http = axios.create({
    baseURL: apiPrefix,
    timeout: REQUEST_TIMEOUT_MS
});

// Intercepteur requêtes: ajoute Authorization si token présent
http.interceptors.request.use(
    (config) => {
        config.headers = config.headers || {};

        try {
            const auth = useAuthStore();
            const token = auth?.token || localStorage.getItem('token');
            if (token) {
                config.headers.Authorization = `Bearer ${token}`;
            }

            const device = getDeviceMetadata();
            config.headers['X-Device-Id'] = device.id;
            config.headers['X-Device-Name'] = device.name;
            config.headers['X-Device-Type'] = device.type;
        } catch (_) {
            // Pinia pas initialisé encore, fallback localStorage déjà géré
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
            try {
                const auth = useAuthStore();
                auth?.setDeviceBlock(
                    error.response?.data?.message,
                    error.response?.data?.status
                );
            } catch (_) {
                // Pinia pas initialisé
            }
            if (router.currentRoute.value.name !== 'devicePending') {
                router.replace({ name: 'devicePending' });
            }
            return Promise.reject(error);
        }

        // Si réponse HTTP et statut 401 => token invalide/expiré
        if (error.response && error.response.status === 401) {
            try {
                const auth = useAuthStore();
                auth?.logout();
            } catch (_) {
                localStorage.removeItem('token');
            }
            if (router.currentRoute.value.name !== 'login') {
                router.replace({ name: 'login' });
            }
        }
        return Promise.reject(error);
    }
);

export default http;
