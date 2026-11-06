import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

function setup(http) {
    const data = new Map();
    globalThis.localStorage = { getItem: (key) => data.get(key), setItem: (key, value) => data.set(key, value), removeItem: (key) => data.delete(key) };
    const source = readFileSync(new URL('../../src/stores/auth.js', import.meta.url), 'utf8')
        .replace(/^import .*;\r?$/gm, '').replace('export const useAuthStore', 'const useAuthStore');
    const options = new Function('defineStore', 'http', 'isDeviceNotAllowedError', `${source}\nreturn useAuthStore;`)(
        (_, options) => options, http, () => false
    );
    const store = options.state();
    for (const [name, action] of Object.entries(options.actions)) store[name] = action.bind(store);
    // Keep unrelated realtime and push teardown out of these authentication tests.
    store.logout = () => { store.sessionVersion++; store.loading = false; store.token = null; store.user = null; data.delete('token'); };
    return { store, data };
}

test('admin login rejects a response without a JWT', async () => {
    const { store, data } = setup({ post: async () => ({ data: {} }) });
    await assert.rejects(store.login('admin', 'password'));
    assert.equal(store.token, null);
    assert.equal(data.size, 0);
    assert.equal(store.loading, false);
});

test('logout during an admin login prevents late JWT persistence', async () => {
    let resolve;
    const response = new Promise((done) => { resolve = done; });
    const { store, data } = setup({ post: () => response });
    const login = store.login('admin', 'password');
    store.logout();
    resolve({ data: { token: 'late' } });
    await assert.rejects(login);
    assert.equal(data.size, 0);
    assert.equal(store.token, null);
});

test('a stale profile error cannot log out a newer admin session', async () => {
    let reject;
    const response = new Promise((_, fail) => { reject = fail; });
    const { store } = setup({ get: () => response });
    store.token = 'old';
    const profile = store.fetchUser();
    store.token = 'new';
    reject({ response: { status: 401 } });
    await assert.rejects(profile);
    assert.equal(store.token, 'new');
});

test('a stale profile success cannot restore a logged-out admin identity', async () => {
    let resolve;
    const response = new Promise((done) => { resolve = done; });
    const { store } = setup({ get: () => response });
    store.token = 'old';
    const profile = store.fetchUser();
    store.logout();
    resolve({ data: { user: { username: 'admin' } } });
    await assert.rejects(profile);
    assert.equal(store.user, null);
});

test('token validation checks the response status and ignores obsolete responses', async () => {
    const { store } = setup({ get: async () => ({ data: { status: 'invalid' } }) });
    store.token = 'old';
    assert.equal(await store.validateToken(), false);
    assert.equal(store.token, null);

    let resolve;
    const response = new Promise((done) => { resolve = done; });
    const { store: current } = setup({ get: () => response });
    current.token = 'old';
    const validation = current.validateToken();
    current.token = 'new';
    resolve({ data: { status: 'invalid' } });
    assert.equal(await validation, false);
    assert.equal(current.token, 'new');
});
