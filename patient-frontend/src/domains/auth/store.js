import { computed, shallowRef } from 'vue';
import { defineStore } from 'pinia';
import * as api from './api.js';
import { createPatientSession } from './session.js';

export const useAuthStore = defineStore('patient-auth', () => {
    const state = shallowRef({ token: '', user: null, version: 0 });
    // Refresh is deliberately tab-scoped, not persisted across browser restarts.
    const session = createPatientSession({ api,
        storage: { getItem: (key) => sessionStorage.getItem(key), setItem: (key, value) => sessionStorage.setItem(key, value), removeItem: (key) => sessionStorage.removeItem(key) },
        legacyStorage: { removeItem: (key) => localStorage.removeItem(key) },
        onChange: (value) => { state.value = value; }
    });
    return {
        token: computed(() => state.value.token), user: computed(() => state.value.user),
        version: computed(() => state.value.version),
        isAuthenticated: computed(() => Boolean(state.value.token && state.value.user)),
        ...session
    };
});
