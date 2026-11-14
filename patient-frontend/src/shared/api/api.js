import { createApiClient } from './client.js';
import { assertPwaRequestsAllowed } from '../lib/pwaUpdateLock.mjs';
import { noteHttpFailure, noteTransportSuccess } from '../composables/useNetworkStatus.js';
import { emitAppToast } from './toastBus.js';

const env = import.meta.env || {};
export const API_BASE_URL = env.VITE_DOKPLOY === 'true' ? '' : env.VITE_API_BASE_URL || 'http://localhost:8010';
let sessionPolicy = null;
export function configureApiSession(policy) { sessionPolicy = policy; }

export const apiRequest = createApiClient({
    baseUrl: API_BASE_URL,
    assertRequestsAllowed: assertPwaRequestsAllowed,
    session: () => sessionPolicy,
    onSuccess: noteTransportSuccess,
    onNetworkError(error, { path, method, showToast }) {
        noteHttpFailure();
        if (showToast && env.DEV) emitAppToast({ severity: 'warn', summary: 'Connexion internet', detail: `[${method}] ${path} : échec réseau`, life: 5000 });
    },
    onFailure(error, { path, method, showToast }) {
        if (!showToast) return;
        const detail = env.DEV ? `[${method}] ${path} : HTTP ${error.status}. ${error.message}`
            : error.status === 401 ? 'Votre session a expiré. Veuillez vous reconnecter.'
                : error.status === 403 ? 'Vous n’êtes pas autorisé à effectuer cette action.'
                    : error.status === 404 ? 'Le service demandé est temporairement indisponible.'
                        : 'Une erreur est survenue. Veuillez réessayer dans un instant.';
        emitAppToast({ severity: 'error', summary: 'Erreur', detail, life: 5500 });
    }
});
