import { apiPrefix } from '@/config';
import cabinetConfig from '@/cabinetConfig';

const buildTimeInternetEnabled = cabinetConfig.internetFeaturesEnabled !== false;

const DEDUPE_MS = 30_000;
const MAX_BODY_BYTES = 4096;
const recentKeys = new Map();

function truncate(value, maxLen) {
    if (value == null) {
        return undefined;
    }
    const str = String(value);
    return str.length <= maxLen ? str : `${str.slice(0, maxLen)}…`;
}

function dedupeKey(payload) {
    return `${payload.context ?? ''}|${payload.message ?? ''}`;
}

function shouldSend(payload) {
    const key = dedupeKey(payload);
    const now = Date.now();
    const last = recentKeys.get(key);
    if (last != null && now - last < DEDUPE_MS) {
        return false;
    }
    recentKeys.set(key, now);
    return true;
}

/**
 * Envoie un rapport d'erreur client au backend (fire-and-forget, sans intercepteur Axios).
 * @param {Record<string, unknown>} payload
 */
export function reportClientError(payload) {
    if (import.meta.env.DEV || !buildTimeInternetEnabled) {
        return;
    }

    if (!payload || typeof payload !== 'object') {
        return;
    }

    const body = {
        message: truncate(payload.message, 500),
        context: truncate(payload.context, 200),
        source: truncate(payload.source, 64),
        route: truncate(payload.route, 500),
        buildId: truncate(payload.buildId, 64),
        status: typeof payload.status === 'number' ? payload.status : undefined,
        code: truncate(payload.code, 64),
        stack: truncate(payload.stack, 2000),
        userAgent: truncate(typeof navigator !== 'undefined' ? navigator.userAgent : '', 256)
    };

    const json = JSON.stringify(body);
    if (json.length > MAX_BODY_BYTES) {
        return;
    }

    if (!shouldSend(body)) {
        return;
    }

    const headers = { 'Content-Type': 'application/json' };
    try {
        const token = localStorage.getItem('token');
        if (token) {
            headers.Authorization = `Bearer ${token}`;
        }
    } catch {
        // ignore
    }

    const url = `${apiPrefix.replace(/\/$/, '')}/client-errors`;

    fetch(url, {
        method: 'POST',
        headers,
        body: json,
        keepalive: true
    }).catch(() => {
        // silent
    });
}
