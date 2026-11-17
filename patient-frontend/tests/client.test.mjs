import assert from 'node:assert/strict';
import test from 'node:test';
import { createApiClient } from '../src/shared/api/client.js';
import { createPatientSession } from '../src/domains/auth/session.js';

const response = (status, payload = {}) => new Response(status === 204 ? null : JSON.stringify(payload), { status, headers: { 'Content-Type': 'application/json' } });
function setup(method = 'GET', statuses = [401, 200]) {
    const calls = []; let refreshes = 0; let invalidations = 0; let active = true;
    const policy = { snapshot: () => ({ token: 'old', version: 1 }), isCurrent: () => active,
        refresh: async () => { refreshes++; return 'new'; }, invalidate: () => { invalidations++; } };
    const request = createApiClient({ session: () => policy, fetchImpl: async (url, options) => { calls.push({ url, ...options }); return response(statuses.shift() ?? 200); } });
    return { request: () => request('/api/patient-portal/me/dashboard', { method, token: 'old', body: method === 'POST' ? { note: 'review' } : undefined }), calls,
        counts: () => ({ refreshes, invalidations }), expire: () => { active = false; }, policy };
}

test('401 GET refreshes once, retries original URL with new token', async () => {
    const s = setup(); await s.request();
    assert.equal(s.calls.length, 2);
    assert.equal(s.calls[0].url, s.calls[1].url);
    assert.equal(s.calls[1].headers.Authorization, 'Bearer new');
    assert.deepEqual(s.counts(), { refreshes: 1, invalidations: 0 });
});
test('POST is never replayed; 401 invalidates session', async () => {
    const s = setup('POST'); await assert.rejects(s.request(), { status: 401 });
    assert.equal(s.calls.length, 1); assert.equal(s.calls[0].body, JSON.stringify({ note: 'review' }));
    assert.deepEqual(s.counts(), { refreshes: 0, invalidations: 1 });
});
test('403 device/portal denial does not trigger refresh or logout', async () => {
    const s = setup('GET', [403]); await assert.rejects(s.request(), { status: 403 });
    assert.deepEqual(s.counts(), { refreshes: 0, invalidations: 0 });
});
test('second 401 stops retry and invalidates', async () => {
    const s = setup('GET', [401, 401]); await assert.rejects(s.request(), { status: 401 });
    assert.equal(s.calls.length, 2); assert.equal(s.counts().invalidations, 1);
});
test('late success cannot expose data after logout', async () => {
    let resolve;
    const gate = new Promise((r) => { resolve = r; });
    let current = true;
    const request = createApiClient({ fetchImpl: () => gate, session: () => ({ snapshot: () => ({}), isCurrent: () => current }) });
    const pending = request('/api/patient-portal/me/payments', { token: 'old' });
    current = false; resolve(response(200, { items: ['private'] }));
    await assert.rejects(pending, /Session modifiée/);
});
test('credentials from a previous session cannot borrow the current bearer', async () => {
    let calls = 0;
    const request = createApiClient({ fetchImpl: async () => { calls++; return response(200); },
        session: () => ({ snapshot: () => ({ token: 'new-user' }) }) });
    await assert.rejects(request('/api/patient-portal/me/reviews', { method: 'POST', token: 'previous-user', body: { comment: 'old data' } }), /Session modifiée/);
    assert.equal(calls, 0);
});

test('concurrent failing reads share the actual session rotation', async () => {
    const jwt = (extra) => `e30.${Buffer.from(JSON.stringify({ realm: 'patient', extra })).toString('base64url')}.sig`;
    const original = jwt('old'); const renewed = jwt('new');
    let rotations = 0;
    const data = new Map();
    const session = createPatientSession({ storage: { setItem: (k, v) => data.set(k, v), removeItem: (k) => data.delete(k) },
        api: { loginPatient: async () => ({ access_token: original, refresh_token: 'first' }),
            fetchCurrentUser: async () => ({ user: { roles: ['ROLE_PATIENT'] } }),
            refreshPatient: async () => { rotations++; await new Promise((r) => setTimeout(r, 5)); return { access_token: renewed, refresh_token: 'second' }; },
            logoutPatient: async () => {} } });
    await session.login({ email: 'patient', password: 'password' });
    const calls = [];
    const request = createApiClient({ session: () => session, fetchImpl: async (url, options) => {
        calls.push(options.headers.Authorization);
        return response(options.headers.Authorization === `Bearer ${original}` ? 401 : 200, { items: [] });
    } });
    await Promise.all(['dashboard', 'consultations', 'payments'].map((name) => request(`/api/patient-portal/me/${name}`, { token: original })));
    assert.equal(rotations, 1); assert.equal(calls.length, 6);
    assert.equal(session.snapshot().token, renewed);
    assert.equal(JSON.parse([...data.values()][0]).refreshToken, 'second');
});

test('204 public response does not trigger session callbacks', async () => {
    const request = createApiClient({ fetchImpl: async () => response(204), session: () => { throw new Error('public request'); } });
    assert.equal(await request('/api/patient-portal/auth/logout', { method: 'POST' }), null);
});
test('rejects external URLs before sending bearer credentials', async () => {
    const request = createApiClient({ fetchImpl: () => { throw new Error('must not fetch'); } });
    await assert.rejects(request('https://other.test/api/me', { token: 'secret' }), /URL API locale/);
});
