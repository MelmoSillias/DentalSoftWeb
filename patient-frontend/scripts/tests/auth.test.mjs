import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { computed, ref } from 'vue';
import { createPinia, defineStore, setActivePinia } from 'pinia';

const patient = { user: { id: 1, username: 'patient', roles: ['ROLE_PATIENT', 'ROLE_USER'] } };
const credentials = { email: ' patient ', password: 'password' };

function setup({ login = async () => ({ token: 'jwt' }), me = async () => patient, saved = {} } = {}) {
    const data = new Map(Object.entries(saved));
    globalThis.localStorage = {
        getItem: (key) => data.get(key) ?? null,
        setItem: (key, value) => data.set(key, value),
        removeItem: (key) => data.delete(key)
    };
    setActivePinia(createPinia());
    const source = readFileSync(new URL('../../src/stores/auth.js', import.meta.url), 'utf8')
        .replace(/^import .*;\r?$/gm, '').replace('export const useAuthStore', 'const useAuthStore');
    const store = new Function('computed', 'ref', 'defineStore', 'fetchCurrentUser', 'loginPatient', `${source}\nreturn useAuthStore;`)(
        computed, ref, defineStore, me, login
    )();
    return { store, data };
}

test('patient login commits only a server-verified identity and token', async () => {
    const { store, data } = setup({ login: async (identifier, password) => {
        assert.equal(identifier, 'patient');
        assert.equal(password, 'password');
        return { token: 'jwt' };
    } });
    await store.login(credentials);
    assert.equal(store.isAuthenticated, true);
    assert.equal(data.get('patient_portal_token'), 'jwt');
});

test('staff and mixed accounts never establish a patient session', async () => {
    for (const roles of [['ROLE_ADMIN'], ['ROLE_ADMIN', 'ROLE_PATIENT'], ['ROLE_USER']]) {
        const { store, data } = setup({ me: async () => ({ user: { roles } }) });
        await assert.rejects(store.login(credentials), { message: 'Identifiants incorrects.' });
        assert.equal(store.isAuthenticated, false);
        assert.equal(data.size, 0);
    }
});

test('authentication failures are generic and erase any previous session', async () => {
    const { store, data } = setup({
        login: async () => { throw Object.assign(new Error('account details'), { status: 401 }); },
        saved: { patient_portal_token: 'previous', patient_portal_user: '{}' }
    });
    await assert.rejects(store.login(credentials), { message: 'Identifiants incorrects.' });
    assert.equal(store.isAuthenticated, false);
    assert.equal(data.size, 0);
});

test('missing JWT and profile failures do not leave a partial session', async () => {
    for (const options of [
        { login: async () => ({}) },
        { me: async () => { throw new Error('offline'); } }
    ]) {
        const { store, data } = setup(options);
        await assert.rejects(store.login(credentials));
        assert.equal(store.isAuthenticated, false);
        assert.equal(data.size, 0);
    }
});

test('hydration ignores corrupt or forged cached identities and checks with the server', async () => {
    let calls = 0;
    const { store } = setup({
        saved: { patient_portal_token: 'saved', patient_portal_user: '{invalid json' },
        me: async (token) => { calls++; assert.equal(token, 'saved'); return patient; }
    });
    assert.equal(store.isAuthenticated, false);
    await Promise.all([store.hydrate(), store.hydrate()]);
    assert.equal(calls, 1);
    assert.equal(store.user.username, 'patient');
    assert.equal(store.isAuthenticated, true);
});

test('invalid stored tokens and staff identities are cleared', async () => {
    for (const me of [
        async () => { throw Object.assign(new Error('expired'), { status: 401 }); },
        async () => ({ user: { roles: ['ROLE_ADMIN'] } })
    ]) {
        const { store, data } = setup({ me, saved: { patient_portal_token: 'bad', patient_portal_user: '{}' } });
        await store.hydrate();
        assert.equal(store.isAuthenticated, false);
        assert.equal(data.size, 0);
    }
});

test('logout while login is pending cannot resurrect the session', async () => {
    let resolveProfile;
    const pending = new Promise((resolve) => { resolveProfile = resolve; });
    const { store, data } = setup({ me: () => pending });
    const login = store.login(credentials);
    await Promise.resolve();
    store.logout();
    resolveProfile(patient);
    await assert.rejects(login, { message: 'Connexion annulée.' });
    assert.equal(store.isAuthenticated, false);
    assert.equal(data.size, 0);
});

test('logout while hydration is pending cannot restore the saved session', async () => {
    let resolveProfile;
    const pending = new Promise((resolve) => { resolveProfile = resolve; });
    const { store } = setup({ me: () => pending, saved: { patient_portal_token: 'old' } });
    const hydration = store.hydrate();
    store.logout();
    resolveProfile(patient);
    await hydration;
    assert.equal(store.isAuthenticated, false);
});

test('storage failures do not keep a partially authenticated in-memory session', async () => {
    const { store } = setup();
    globalThis.localStorage.setItem = () => { throw new Error('storage blocked'); };
    await assert.rejects(store.login(credentials));
    assert.equal(store.isAuthenticated, false);
});
