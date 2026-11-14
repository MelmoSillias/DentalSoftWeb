import assert from 'node:assert/strict';
import test from 'node:test';
import { createPatientSession, SESSION_KEY } from '../../src/domains/auth/session.js';

const jwt = (realm = 'patient', extra = '') => `e30.${Buffer.from(JSON.stringify({ realm, extra })).toString('base64url')}.signature`;
const pair = (extra = '') => ({ access_token: jwt('patient', extra), refresh_token: `refresh${extra}` });
const patient = { user: { id: 1, username: 'patient', roles: ['ROLE_PATIENT', 'ROLE_USER'] } };
const credentials = { email: ' patient ', password: 'password' };
const deferred = () => { let resolve; const promise = new Promise((r) => { resolve = r; }); return { promise, resolve }; };
const http = (status) => Object.assign(new Error(`HTTP ${status}`), { status });
function setup(overrides = {}, saved) {
    const data = new Map(saved ? [[SESSION_KEY, JSON.stringify(saved)]] : []);
    const storage = { getItem: (k) => data.get(k), setItem: (k, v) => data.set(k, v), removeItem: (k) => data.delete(k) };
    const revoked = [];
    const api = { loginPatient: async (email) => { assert.equal(email, 'patient'); return pair(); },
        fetchCurrentUser: async () => patient, refreshPatient: async () => pair('new'),
        logoutPatient: async (secret) => revoked.push(secret), updatePassword: async () => {}, ...overrides };
    const session = createPatientSession({ api, storage });
    return { session, data, storage, revoked };
}

test('login persists a pair only after server identity verification', async () => {
    const { session, data } = setup();
    await session.login(credentials);
    assert.equal(session.snapshot().user.username, 'patient');
    assert.equal(JSON.parse(data.get(SESSION_KEY)).refreshToken, 'refresh');
});

test('staff, mixed roles, wrong realm and old JWT cannot establish a session', async () => {
    for (const roles of [['ROLE_ADMIN'], ['ROLE_PATIENT', 'ROLE_ADMIN'], ['ROLE_USER']]) {
        const { session, data } = setup({ fetchCurrentUser: async () => ({ user: { roles } }) });
        await assert.rejects(session.login(credentials));
        assert.equal(session.snapshot().user, null); assert.equal(data.size, 0);
    }
    for (const access_token of [jwt('app'), 'old.jwt']) {
        const { session } = setup({ loginPatient: async () => ({ access_token, refresh_token: 'secret' }) });
        await assert.rejects(session.login(credentials));
        assert.equal(session.snapshot().user, null);
    }
});

test('401 login is generic; missing token and profile failures leave no partial identity', async () => {
    for (const overrides of [
        { loginPatient: async () => { throw http(401); } },
        { loginPatient: async () => ({}) },
        { fetchCurrentUser: async () => { throw new TypeError('offline'); } }
    ]) {
        const { session, data } = setup(overrides);
        await assert.rejects(session.login(credentials));
        assert.equal(session.snapshot().user, null); assert.equal(data.size, 0);
    }
});

test('hydration checks server identity once, never cached identity', async () => {
    let calls = 0;
    const { session } = setup({ fetchCurrentUser: async () => { calls++; return patient; } }, { token: jwt(), refreshToken: 'saved', user: { roles: ['ROLE_ADMIN'] } });
    await Promise.all([session.hydrate(), session.hydrate()]);
    assert.equal(calls, 1); assert.equal(session.snapshot().user.username, 'patient');
});

test('expired restored JWT rotates and validates profile', async () => {
    const { session } = setup({ fetchCurrentUser: async (token) => { if (token === jwt()) throw http(401); return patient; } }, { token: jwt(), refreshToken: 'saved' });
    await session.hydrate();
    assert.equal(session.snapshot().token, jwt('patient', 'new'));
    assert.equal(session.snapshot().user.username, 'patient');
});

test('concurrent refresh shares a single rotation', async () => {
    let calls = 0; const gate = deferred();
    const { session } = setup({ refreshPatient: async () => { calls++; return gate.promise; } });
    await session.login(credentials);
    const a = session.refresh(); const b = session.refresh();
    gate.resolve(pair('new'));
    assert.deepEqual(await Promise.all([a, b]), [jwt('patient', 'new'), jwt('patient', 'new')]);
    assert.equal(calls, 1);
    assert.equal(await session.refresh(jwt()), jwt('patient', 'new'));
    assert.equal(calls, 1);
});

test('logout immediately clears local state even if revocation fails', async () => {
    const { session, data } = setup({ logoutPatient: async () => { throw new TypeError('offline'); } });
    await session.login(credentials);
    const result = session.logout();
    assert.equal(session.snapshot().user, null); assert.equal(data.size, 0);
    assert.equal(await result, false);
});

test('late login or profile cannot resurrect a logged-out session', async () => {
    for (const stage of ['loginPatient', 'fetchCurrentUser']) {
        const gate = deferred();
        const { session, data } = setup({ [stage]: () => gate.promise });
        const pending = session.login(credentials);
        await Promise.resolve();
        await session.logout();
        gate.resolve(stage === 'loginPatient' ? pair() : patient);
        await assert.rejects(pending);
        assert.equal(session.snapshot().user, null); assert.equal(data.size, 0);
    }
});

test('late hydration cannot resurrect a logged-out session', async () => {
    const gate = deferred();
    const { session } = setup({ fetchCurrentUser: () => gate.promise }, { token: jwt(), refreshToken: 'saved' });
    const pending = session.hydrate();
    await session.logout(); gate.resolve(patient); await pending;
    assert.equal(session.snapshot().user, null);
});

test('late rotation is revoked and cannot replace a newer login', async () => {
    const gate = deferred();
    const { session, revoked } = setup({ refreshPatient: () => gate.promise });
    await session.login(credentials);
    const pending = session.refresh();
    await session.logout(); await session.login(credentials);
    gate.resolve(pair('late'));
    await assert.rejects(pending);
    assert.equal(session.snapshot().token, jwt());
    assert.ok(revoked.includes('refreshlate'));
});

test('invalidated credentials clear session on refresh 401/403, not network errors', async () => {
    for (const status of [401, 403, 429]) {
        const { session } = setup({ refreshPatient: async () => { throw http(status); } });
        await session.login(credentials); await assert.rejects(session.refresh());
        assert.equal(Boolean(session.snapshot().user), status === 429);
    }
});

test('password success invalidates local tokens; failure preserves session', async () => {
    const { session, revoked } = setup();
    await session.login(credentials); await session.changePassword('old', 'new');
    assert.equal(session.snapshot().user, null); assert.deepEqual(revoked, ['refresh']);
    const failed = setup({ updatePassword: async () => { throw http(400); } }).session;
    await failed.login(credentials); await assert.rejects(failed.changePassword('bad', 'new'));
    assert.ok(failed.snapshot().user);
});

test('blocked storage does not leave a partially authenticated session', async () => {
    const { session, storage } = setup();
    storage.setItem = () => { throw new Error('blocked'); };
    await assert.rejects(session.login(credentials));
    assert.equal(session.snapshot().user, null);
});
