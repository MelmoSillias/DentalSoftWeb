// The application injects session policy; transport never imports a store or router.
export function createApiClient({ baseUrl = '', fetchImpl = (...args) => globalThis.fetch(...args), session = () => null, onFailure = () => {}, onSuccess = () => {}, onNetworkError = () => {}, assertRequestsAllowed = () => {} } = {}) {
    return async function request(path, { method = 'GET', token = '', body, headers = {}, showToast = true, authenticated = Boolean(token) } = {}) {
        assertRequestsAllowed();
        if (!path.startsWith('/api/') || path.startsWith('//')) throw new Error('URL API locale requise.');
        method = method.toUpperCase();
        const policy = authenticated ? session() : null;
        const snapshot = policy?.snapshot();
        // A caller holding credentials from another session must not borrow the new user's token.
        if (snapshot && token && token !== snapshot.token) throw new Error('Session modifiée.');
        const access = snapshot?.token || token;
        const send = async (bearer) => {
            assertRequestsAllowed();
            let response;
            try {
                response = await fetchImpl(`${baseUrl}${path}`, {
                    method,
                    cache: 'no-store',
                    headers: { ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}), ...headers, ...(bearer ? { Authorization: `Bearer ${bearer}` } : {}) },
                    body: body === undefined ? undefined : JSON.stringify(body)
                });
            } catch (error) { onNetworkError(error, { path, method, showToast }); throw error; }
            assertRequestsAllowed();
            const payload = response.status !== 204 && (response.headers.get('content-type') || '').includes('application/json') ? await response.json() : null;
            return { response, payload };
        };
        let result = await send(access);
        assertRequestsAllowed();
        if (policy && !policy.isCurrent(snapshot)) throw new Error('Session modifiée.');
        // Never replay a mutation: its first execution may already have had effects.
        if (result.response.status === 401 && policy && method === 'GET') {
            const renewed = await policy.refresh(access);
            if (!policy.isCurrent(snapshot)) throw new Error('Session modifiée.');
            result = await send(renewed);
        }
        assertRequestsAllowed();
        if (policy && !policy.isCurrent(snapshot)) throw new Error('Session modifiée.');
        if (!result.response.ok) {
            const error = new Error(result.payload?.error || result.payload?.message || `Erreur HTTP ${result.response.status}`);
            error.status = result.response.status;
            if (error.status === 401 && policy) policy.invalidate(snapshot);
            onFailure(error, { path, method, showToast });
            throw error;
        }
        onSuccess();
        return result.payload;
    };
}
