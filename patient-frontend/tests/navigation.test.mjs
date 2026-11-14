import assert from 'node:assert/strict';
import test from 'node:test';
import { createPinia, setActivePinia } from 'pinia';
import { createPortalGuard } from '../src/app/router/guard.js';
import { usePortalSettingsStore } from '../src/domains/patient-portal/stores/portalSettings.js';

const route = (name, requiresAuth = false) => ({ name, fullPath: `/${name}?detail=7`, meta: { requiresAuth, public: !requiresAuth } });
function guard(authenticated, closed) {
    return createPortalGuard({ getAuth: () => ({ isAuthenticated: authenticated, hydrate: async () => {} }), getPortal: () => ({ isPortalClosed: closed, load: async () => {} }) });
}

test('unauthenticated private navigation preserves the deep link', async () => {
    assert.deepEqual(await guard(false, false)(route('consultations', true)), { name: 'login', query: { redirect: '/consultations?detail=7' } });
});
test('authenticated patient can reach all existing private screens', async () => {
    for (const name of ['dashboard', 'consultations', 'appointments', 'payments', 'documents', 'profile']) assert.equal(await guard(true, false)(route(name, true)), true);
    assert.deepEqual(await guard(true, false)(route('login')), { name: 'dashboard' });
});
test('closed portal blocks private screens but keeps login and anonymous review public', async () => {
    for (const authenticated of [true, false]) {
        assert.deepEqual(await guard(authenticated, true)(route('documents', true)), { name: 'portal-closed' });
        assert.equal(await guard(authenticated, true)(route('anonymous-review')), true);
        assert.equal(await guard(authenticated, true)(route('portal-closed')), true);
    }
    assert.equal(await guard(false, true)(route('login')), true);
    assert.deepEqual(await guard(true, true)(route('login')), { name: 'portal-closed' });
});
test('guard waits for restored identity and public settings', async () => {
    const events = [];
    const auth = { isAuthenticated: false, hydrate: async () => { events.push('hydrate'); auth.isAuthenticated = true; } };
    const check = createPortalGuard({ getAuth: () => auth, getPortal: () => ({ isPortalClosed: false, load: async () => { events.push('settings'); } }) });
    assert.equal(await check(route('profile', true)), true);
    assert.deepEqual(events, ['hydrate', 'settings']);
});
test('concurrent settings loads share a request and all wait for portal closure', async () => {
    const original = globalThis.fetch;
    let resolve; let calls = 0;
    globalThis.fetch = () => { calls++; return new Promise((r) => { resolve = r; }); };
    try {
        setActivePinia(createPinia());
        const portal = usePortalSettingsStore();
        const first = portal.load(); const second = portal.load();
        assert.equal(calls, 1);
        resolve(new Response(JSON.stringify({ patientPortalEnabled: false }), { headers: { 'Content-Type': 'application/json' } }));
        await Promise.all([first, second]);
        assert.equal(portal.isPortalClosed, true);
        assert.equal(portal.loaded, true);
    } finally { globalThis.fetch = original; }
});
