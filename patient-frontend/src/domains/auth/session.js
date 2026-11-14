export const SESSION_KEY = 'patient_portal_session_v2';
const CANCELLED = 'Session modifiée. Veuillez vous reconnecter.';

export function patientIdentity(token, payload, identifier = '') {
    let claims;
    try { claims = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/'))); } catch { throw new Error('Token patient invalide.'); }
    const user = payload?.user;
    const roles = Array.isArray(user?.roles) ? user.roles : [];
    // Only a UI sanity check: signature, credentials and authorization are checked by the API.
    if (claims.realm !== 'patient' || !roles.includes('ROLE_PATIENT') || roles.some((role) => !['ROLE_PATIENT', 'ROLE_USER'].includes(role))) {
        throw new Error('Identifiants incorrects.');
    }
    return { id: user.id ?? null, username: user.username || identifier, name: user.username || identifier, roles, email: user.email || null };
}

export function createPatientSession({ api, storage, legacyStorage, onChange = () => {} }) {
    let state = { token: '', refreshToken: '', user: null, version: 0 };
    let hydration;
    let hydrated = false;
    let rotation;
    const publish = () => onChange({ ...state });
    const current = (snapshot) => snapshot.version === state.version;
    const save = () => storage.setItem(SESSION_KEY, JSON.stringify({ token: state.token, refreshToken: state.refreshToken }));
    function clear() {
        state = { token: '', refreshToken: '', user: null, version: state.version + 1 };
        hydrated = true;
        try { storage.removeItem(SESSION_KEY); } catch { /* Local logout must work with blocked storage. */ }
        for (const key of ['patient_portal_token', 'patient_portal_user']) {
            try { legacyStorage?.removeItem(key); } catch { /* Legacy identity is never trusted. */ }
        }
        publish();
    }
    async function revoke(secret) {
        if (!secret) return true;
        try { await api.logoutPatient(secret); return true; } catch { return false; }
    }
    async function logout() {
        const secret = state.refreshToken;
        clear();
        return revoke(secret);
    }
    function pair(payload) {
        const token = payload?.access_token || payload?.token;
        const refreshToken = payload?.refresh_token;
        if (typeof token !== 'string' || !token || typeof refreshToken !== 'string' || !refreshToken) throw new Error('Session patient incomplète.');
        return { token, refreshToken };
    }
    async function refresh(failedToken = state.token) {
        if (failedToken !== state.token) return state.token;
        if (rotation?.version === state.version) return rotation.promise;
        const snapshot = { ...state };
        if (!snapshot.refreshToken) { clear(); throw new Error('Veuillez vous reconnecter.'); }
        const promise = (async () => {
            let next;
            try {
                next = pair(await api.refreshPatient(snapshot.refreshToken));
                if (!current(snapshot)) { await revoke(next.refreshToken); throw new Error(CANCELLED); }
                // Realm must also remain patient after rotation.
                patientIdentity(next.token, { user: state.user || { roles: ['ROLE_PATIENT'] } });
                state = { ...state, ...next };
                save();
                publish();
                return next.token;
            } catch (error) {
                if (next && current(snapshot)) void revoke(next.refreshToken);
                                if (current(snapshot) && (error.status === 401 || error.status === 403 || next)) clear();
                throw error;
            }
        })();
        rotation = { version: snapshot.version, promise };
        try { return await promise; } finally { if (rotation?.promise === promise) rotation = null; }
    }
    async function login({ email, password }) {
        const previous = state.refreshToken;
        clear();
        void revoke(previous);
        const snapshot = { ...state };
        const identifier = (email || '').trim();
        if (!identifier || !password) throw new Error('Identifiant et mot de passe requis.');
        let next;
        try {
            next = pair(await api.loginPatient(identifier, password));
            if (!current(snapshot)) throw new Error(CANCELLED);
            const user = patientIdentity(next.token, await api.fetchCurrentUser(next.token), identifier);
            if (!current(snapshot)) throw new Error(CANCELLED);
            state = { ...state, ...next, user };
            save();
            publish();
        } catch (error) {
            if (next) void revoke(next.refreshToken);
            if (current(snapshot)) clear();
            if (error.status === 401) throw new Error('Identifiants incorrects.');
            throw error;
        }
    }
    async function hydrate() {
        if (hydrated) return;
        if (hydration) return hydration;
        const snapshot = { ...state };
        hydration = (async () => {
            try {
                const raw = storage.getItem(SESSION_KEY);
                if (!raw) { if (current(snapshot)) clear(); return; }
                const saved = JSON.parse(raw);
                if (!saved.token || !saved.refreshToken) { clear(); return; }
                state = { ...state, token: saved.token, refreshToken: saved.refreshToken };
                let me;
                try { me = await api.fetchCurrentUser(state.token); }
                catch (error) {
                    if (!current(snapshot)) return;
                    if (error.status !== 401) throw error;
                    await refresh();
                    me = await api.fetchCurrentUser(state.token);
                }
                if (!current(snapshot)) return;
                state = { ...state, user: patientIdentity(state.token, me) };
                hydrated = true;
                publish();
            } catch (error) {
                if (!current(snapshot) || error.code === 'PWA_UPDATE_REQUIRED') return;
                if (error.status === 401 || error.status === 403 || !error.status && !(error instanceof TypeError)) clear();
                else { state = { ...state, token: '', user: null }; publish(); }
            } finally { hydration = null; }
        })();
        return hydration;
    }
    async function changePassword(oldPassword, newPassword) {
        const snapshot = { ...state };
        await api.updatePassword(snapshot.token, oldPassword, newPassword);
        if (current(snapshot)) await logout();
    }
    return { login, logout, hydrate, refresh, changePassword,
        snapshot: () => ({ ...state }), isCurrent: current,
        invalidate(snapshot) { if (current(snapshot)) clear(); }
    };
}
