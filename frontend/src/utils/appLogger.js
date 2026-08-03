import { reportClientError } from '@/services/clientErrorReport';

const MSG_MAX = 500;
const STACK_MAX = 2000;

function truncate(value, maxLen) {
    if (value == null) {
        return undefined;
    }
    const str = String(value);
    return str.length <= maxLen ? str : `${str.slice(0, maxLen)}…`;
}

function urlWithoutQuery(raw) {
    if (!raw || typeof raw !== 'string') {
        return undefined;
    }
    try {
        if (raw.startsWith('http://') || raw.startsWith('https://')) {
            const u = new URL(raw);
            return `${u.origin}${u.pathname}`;
        }
        const q = raw.indexOf('?');
        return q >= 0 ? raw.slice(0, q) : raw;
    } catch {
        const q = raw.indexOf('?');
        return q >= 0 ? raw.slice(0, q) : raw;
    }
}

/**
 * @param {unknown} error
 * @returns {Record<string, unknown>}
 */
export function sanitizeError(error) {
    if (error == null) {
        return { message: 'unknown' };
    }

    if (typeof error === 'string') {
        return { message: truncate(error, MSG_MAX) };
    }

    const err = /** @type {Record<string, unknown> & { response?: { status?: number }; config?: { url?: string; method?: string }; userMessage?: string }} */ (error);

    const message =
        truncate(err.userMessage, MSG_MAX)
        ?? truncate(err.message, MSG_MAX)
        ?? 'Error';

    const out = {
        name: truncate(err.name, 128),
        message,
        code: truncate(err.code, 64),
        status: err.response?.status,
        url: urlWithoutQuery(err.config?.url),
        method: truncate(err.config?.method, 16)
    };

    if (typeof err.stack === 'string') {
        out.stack = truncate(err.stack, STACK_MAX);
    }

    return out;
}

export function devDebug(...args) {
    if (import.meta.env.DEV) {
        console.debug(...args);
    }
}

let routeResolver = () => undefined;

/** Enregistré depuis main.js après création du router. */
export function setAppLoggerRouteResolver(fn) {
    routeResolver = typeof fn === 'function' ? fn : () => undefined;
}

function inferSource(context) {
    if (typeof context === 'string' && context.startsWith('vue:')) {
        return 'vue.errorHandler';
    }
    if (context === 'unhandledrejection') {
        return 'unhandledrejection';
    }
    return 'catch';
}

/**
 * @param {string} context
 * @param {unknown} error
 * @param {Record<string, unknown} [extra]
 */
export function logAppError(context, error, extra) {
    const sanitized = sanitizeError(error);

    if (import.meta.env.DEV) {
        console.error(context, sanitized, extra ?? '');
        if (error instanceof Error && error.stack) {
            console.error(error.stack);
        }
        return;
    }

    try {
        reportClientError({
            context,
            message: String(sanitized.message ?? 'Error'),
            source: inferSource(context),
            route: routeResolver(),
            buildId: import.meta.env.VITE_APP_BUILD_ID,
            status: typeof sanitized.status === 'number' ? sanitized.status : undefined,
            code: sanitized.code,
            stack: sanitized.stack
        });
    } catch {
        // éviter boucles
    }
}
