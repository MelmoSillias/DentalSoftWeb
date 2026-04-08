import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

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
        if (!email || !password) {
            throw new Error('Email et mot de passe requis.');
        }

        const fakeToken = `patient-${Date.now()}`;
        const fakeUser = {
            id: 1,
            name: 'Patient Demo',
            email
        };

        token.value = fakeToken;
        user.value = fakeUser;

        localStorage.setItem(TOKEN_KEY, fakeToken);
        localStorage.setItem(USER_KEY, JSON.stringify(fakeUser));
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
