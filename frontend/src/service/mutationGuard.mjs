// One network operation for concurrent identical writes. Ambiguous failures keep
// their key so a manual retry can retrieve the server's committed response.
export const isMutation = (method) => ['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method).toUpperCase());
const RETENTION_MS = 24 * 60 * 60 * 1000;

export function createMutationGuard({ storage, crypto = globalThis.crypto, onPending = () => {}, now = Date.now } = {}) {
    const operations = new Map();
    let pending = 0;
    const notify = (delta) => {
        pending += delta;
        onPending(pending);
    };
    const uuid = () => {
        if (crypto?.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
    };

    return function run(identity, send) {
        const existing = operations.get(identity);
        if (existing?.promise) return existing.promise;
        const record = existing && existing.expires > now() ? existing : { key: uuid(), expires: now() + RETENTION_MS };
        operations.set(identity, record);
        // Report request activity without imposing any global UI lock.
        notify(1);
        record.promise = (async () => {
            await Promise.resolve();
            let storageKey;
            try {
                if (crypto?.subtle && storage) {
                    const hash = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(identity));
                    storageKey = `dentalsoft:mutation:${Array.from(new Uint8Array(hash), (b) => b.toString(16).padStart(2, '0')).join('')}`;
                    try {
                        const saved = JSON.parse(storage.getItem(storageKey) || 'null');
                        if (saved?.expires > now()) {
                            record.key = saved.key;
                            record.expires = saved.expires;
                        }
                        storage.setItem(storageKey, JSON.stringify({ key: record.key, expires: record.expires }));
                    } catch {
                        /* Private mode / storage quota: in-memory protection remains active. */
                    }
                }
                const result = await send(record.key);
                operations.delete(identity);
                try {
                    if (storageKey) storage.removeItem(storageKey);
                } catch {
                    /* optional storage */
                }
                return result;
            } catch (error) {
                const status = error?.response?.status ?? error?.status;
                // A timeout or server error does not prove that the action failed.
                if (status >= 400 && status < 500 && status !== 408 && status !== 425 && status !== 429) {
                    operations.delete(identity);
                    try {
                        if (storageKey) storage.removeItem(storageKey);
                    } catch {
                        /* optional storage */
                    }
                }
                throw error;
            } finally {
                record.promise = null;
                notify(-1);
                for (const [key, value] of operations) {
                    if (!value.promise && value.expires <= now()) operations.delete(key);
                }
            }
        })();
        return record.promise;
    };
}

export function stableValue(value) {
    if (Array.isArray(value)) return value.map(stableValue);
    if (value && Object.getPrototypeOf(value) === Object.prototype) {
        return Object.fromEntries(
            Object.keys(value)
                .sort()
                .map((key) => [key, stableValue(value[key])])
        );
    }
    return value;
}
