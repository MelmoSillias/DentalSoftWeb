import { computed, ref } from 'vue';
import { defineStore } from 'pinia';
import { fetchCurrentUser, loginPatient } from '../services/patientPortal';

const TOKEN_KEY = 'patient_portal_token';
const USER_KEY = 'patient_portal_user';

export const useAuthStore = defineStore('auth', () => {
    const token = ref('');
    const user = ref(null);
    const hydrated = ref(false);

    const isAuthenticated = computed(() => Boolean(token.value));

    function hydrate() {
        if (hydrated.value) {
            return;
        }

        token.value = localStorage.getItem(TOKEN_KEY) || '';

        const rawUser = localStorage.getItem(USER_KEY);
        user.value = rawUser ? JSON.parse(rawUser) : null;
        hydrated.value = true;
    }

    async function login({ email, password }) {
        const identifier = (email || '').trim();
        if (!identifier || !password) {
            throw new Error('Identifiant et mot de passe requis.');
        }

        const loginResponse = await loginPatient(identifier, password);
        const jwtToken = loginResponse?.token;
        if (!jwtToken) {
            throw new Error('Token JWT absent de la réponse.');
        }

        const me = await fetchCurrentUser(jwtToken);
        const meUser = me?.user || {};
        const employee = me?.employee || null;

        const connectedUser = {
            id: meUser?.id || null,
            username: meUser?.username || identifier,
            roles: Array.isArray(meUser?.roles) ? meUser.roles : [],
            name: employee ? `${employee?.nom || ''} ${employee?.prenom || ''}`.trim() : meUser?.username || identifier,
            email: employee?.email || null
        };

        token.value = jwtToken;
        user.value = connectedUser;

        localStorage.setItem(TOKEN_KEY, jwtToken);
        localStorage.setItem(USER_KEY, JSON.stringify(connectedUser));
    }

    function logout() {
        token.value = '';
        user.value = null;
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
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
