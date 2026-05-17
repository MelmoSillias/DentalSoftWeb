const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8010';
import { emitAppToast } from './toastBus';

const IS_DEV = Boolean(import.meta.env.DEV);

function buildToastDetail({ path, method, status, rawMessage }) {
    if (IS_DEV) {
        return `[${method}] ${path} -> HTTP ${status}. ${rawMessage}`;
    }

    if (status === 401 || status === 403) {
        return 'Votre session a expiré ou vous n etes pas autorise. Veuillez vous reconnecter.';
    }

    if (status === 404) {
        return 'Le service demande est temporairement indisponible.';
    }

    return 'Une erreur est survenue. Veuillez reessayer dans un instant.';
}

export async function apiRequest(path, { method = 'GET', token = '', body, headers = {}, showToast = true } = {}) {
    const requestHeaders = {
        ...headers
    };

    if (body !== undefined && !requestHeaders['Content-Type']) {
        requestHeaders['Content-Type'] = 'application/json';
    }

    if (token) {
        requestHeaders.Authorization = `Bearer ${token}`;
    }

    let response;
    try {
        response = await fetch(`${API_BASE_URL}${path}`, {
            method,
            headers: requestHeaders,
            body: body !== undefined ? JSON.stringify(body) : undefined
        });
    } catch (networkError) {
        if (showToast) {
            emitAppToast({
                severity: 'error',
                summary: 'Reseau',
                detail: IS_DEV
                    ? `[${method}] ${path} -> Echec reseau (${networkError instanceof Error ? networkError.message : 'unknown'})`
                    : 'Connexion au serveur impossible. Verifiez votre reseau puis reessayez.',
                life: 5000
            });
        }

        throw networkError;
    }

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : null;

    if (!response.ok) {
        const errorMessage = payload?.error || payload?.message || `Erreur HTTP ${response.status}`;
        if (showToast) {
            emitAppToast({
                severity: 'error',
                summary: 'Erreur',
                detail: buildToastDetail({
                    path,
                    method,
                    status: response.status,
                    rawMessage: errorMessage
                }),
                life: 5500
            });
        }
        throw new Error(errorMessage);
    }

    return payload;
}

export { API_BASE_URL };
