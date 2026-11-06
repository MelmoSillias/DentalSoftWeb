import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchCurrentUser, loginPatient } from '../services/patientPortal';

const TOKEN_KEY = 'patient_portal_token';
const USER_KEY = 'patient_portal_user';
const INVALID_CREDENTIALS = 'Identifiants incorrects.';
const STAFF_ROLES = ['ROLE_ADMIN', 'ROLE_MEDECIN', 'ROLE_RECEPTION', 'ROLE_RECEPTIONNISTE', 'ROLE_SECRETAIRE'];

function patientUser(me, identifier = '') {
    const meUser = me?.user;
    const roles = Array.isArray(meUser?.roles) ? meUser.roles : [];
    if (!roles.includes('ROLE_PATIENT') || roles.some((role) => STAFF_ROLES.includes(role))) {
        throw new Error(INVALID_CREDENTIALS);
    }
    return {
        id: meUser.id ?? null,
        username: meUser.username || identifier,
        roles,
        name: meUser.username || identifier,
        email: null
    };
}

export const useAuthStore = defineStore('auth', () => {
    const token = ref('');
    const user = ref(null);
    const hydrated = ref(false);
    let hydrationPromise;
    let sessionVersion = 0;

    const isAuthenticated = computed(() => Boolean(token.value && user.value));

    async function hydrate() {
        if (hydrated.value) {
            return;
        }

        if (hydrationPromise) return hydrationPromise;
        const version = sessionVersion;
        hydrationPromise = (async () => {
            try {
                // Cached identity is never trusted to restore an authenticated session.
                const savedToken = localStorage.getItem(TOKEN_KEY);
                if (!savedToken) return;
                const me = await fetchCurrentUser(savedToken);
                const connectedUser = patientUser(me);
                if (version !== sessionVersion) return;
                token.value = savedToken;
                user.value = connectedUser;
            } catch {
                if (version === sessionVersion) logout();
            } finally {
                hydrated.value = true;
            }
        })();
        return hydrationPromise;
    }

    async function login({ email, password }) {
        logout();
        const version = sessionVersion;
        const identifier = (email || '').trim();
        if (!identifier || !password) {
            throw new Error('Identifiant et mot de passe requis.');
        }

        let loginResponse;
        try {
            loginResponse = await loginPatient(identifier, password);
        } catch (error) {
            if (error?.status === 401 || error?.status === 403) {
                throw new Error(INVALID_CREDENTIALS);
            }
            throw error;
        }
        const jwtToken = loginResponse?.token;
        if (typeof jwtToken !== 'string' || !jwtToken) {
            throw new Error('Token JWT absent de la réponse.');
        }

        let connectedUser;
        try {
            connectedUser = patientUser(await fetchCurrentUser(jwtToken), identifier);
        } catch (error) {
            if (error?.status === 401 || error?.status === 403) throw new Error(INVALID_CREDENTIALS);
            throw error;
        }
        if (version !== sessionVersion) throw new Error('Connexion annulée.');

        try {
            localStorage.setItem(TOKEN_KEY, jwtToken);
            localStorage.setItem(USER_KEY, JSON.stringify(connectedUser));
        } catch (error) {
            logout();
            throw error;
        }

        token.value = jwtToken;
        user.value = connectedUser;
    }

    function logout() {
        sessionVersion += 1;
        token.value = '';
        user.value = null;
        try {
            localStorage.removeItem(TOKEN_KEY);
            localStorage.removeItem(USER_KEY);
        } catch {
            // In-memory logout must also work when browser storage is unavailable.
        }
    }

    return {
        token,
        user,
        isAuthenticated,
        hydrate,
        login,
        logout
    };
});
