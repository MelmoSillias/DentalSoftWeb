import test from 'node:test';
import assert from 'node:assert/strict';
import { patientPwaOptions } from '../plugins/patientPwa.mjs';
import { createApiClient } from '../src/shared/api/client.js';
import { apiRequest } from '../src/shared/api/api.js';
import { lockForPwaUpdate } from '../src/shared/lib/pwaUpdateLock.mjs';
import { createPatientSession, SESSION_KEY } from '../src/domains/auth/session.js';

const updateError = () => Object.assign(new Error('Required update'), { code: 'PWA_UPDATE_REQUIRED' });

test('PWA precaches only shell assets; navigation excludes API, medical files and metadata', () => {
    const options = patientPwaOptions({ displayName: 'Cabinet test', brandName: 'Test', logo: 'logo.png' });
    assert.equal(options.registerType, 'prompt');
    assert.equal(options.injectRegister, false);
    assert.equal(options.workbox.skipWaiting, undefined);
    assert.deepEqual(options.workbox.runtimeCaching, []);
    assert.deepEqual(options.workbox.globPatterns, ['index.html', 'assets/**/*.{js,css,woff,woff2,ttf,png,svg,ico,jpg,jpeg,webp}']);
    assert.ok(options.workbox.globIgnores.includes('**/release.json'));
    for (const url of ['/api', '/api/patient-portal/me/profile', '/uploads/private.pdf', '/upload_files/private.png', '/assurances/a.pdf', '/health', '/.well-known/mercure', '/release.json', '/pwa-release-worker.js', '/prints/receipt', '/documents/a.pdf', '/api?x=1', '/release.json?v=2', '/uploads?path=x', '/private.pdf?x=1']) {
        assert.ok(options.workbox.navigateFallbackDenylist.some((pattern) => pattern.test(url)), url);
    }
    for (const url of ['/login', '/dashboard', '/documents', '/consultations']) {

        assert.ok(options.workbox.navigateFallbackDenylist.every((pattern) => !pattern.test(url)), url);
    }
    assert.equal(options.manifest.name, 'Cabinet test — Espace patient');
    assert.equal(options.manifest.short_name, 'Test');
    assert.equal(options.manifest.icons[0].src, 'logo.png');
    assert.throws(() => patientPwaOptions({ pwa: { icons: [{ src: '/uploads/private.png' }] } }), /Invalid public PWA asset/);
    const branded = patientPwaOptions({ pwa: { name: 'Portail personnalisé', themeColor: '#123456', icons: [{ src: 'icon.png', sizes: '512x512' }] } });
    assert.equal(branded.manifest.name, 'Portail personnalisé');
    assert.equal(branded.manifest.theme_color, '#123456');
    assert.ok(branded.includeAssets.includes('icon.png'));
});

test('HTTP lock applies again before a retry and does not trigger network error notifications', async () => {
    let blocked = false; let calls = 0; let networkFailures = 0;
    const request = createApiClient({ assertRequestsAllowed: () => { if (blocked) throw updateError(); },
        onNetworkError: () => { networkFailures++; },
        session: () => ({ snapshot: () => ({ token: 'old' }), isCurrent: () => true,
            refresh: async () => { blocked = true; return 'new'; } }),
        fetchImpl: async () => { calls++; return new Response('{}', { status: 401, headers: { 'Content-Type': 'application/json' } }); } });
    await assert.rejects(request('/api/me', { token: 'old' }), { code: 'PWA_UPDATE_REQUIRED' });
    assert.equal(calls, 1); assert.equal(networkFailures, 0);
});

test('HTTP lock discards data from a request already in flight', async () => {
    let blocked = false; let finish;
    const pending = new Promise((r) => { finish = r; });
    const request = createApiClient({ assertRequestsAllowed: () => { if (blocked) throw updateError(); }, fetchImpl: () => pending });
    const result = request('/api/patient-portal/me/payments', { token: 'patient' });
    blocked = true;
    finish(new Response('{"items":["private"]}', { headers: { 'Content-Type': 'application/json' } }));
    await assert.rejects(result, { code: 'PWA_UPDATE_REQUIRED' });
});

test('persisted mandatory lock does not erase saved authentication while hydrating', async () => {
    const saved = JSON.stringify({ token: 'saved', refreshToken: 'saved-refresh' });
    const data = new Map([[SESSION_KEY, saved]]);
    const session = createPatientSession({ storage: { getItem: (k) => data.get(k), removeItem: (k) => data.delete(k) },
        api: { fetchCurrentUser: async () => { throw updateError(); } } });
    await session.hydrate();
    assert.equal(data.get(SESSION_KEY), saved);
    assert.equal(session.snapshot().user, null);
});

test('real shared API blocks all methods, public/login/refresh/logout too, before fetch', async () => {
    const original = globalThis.fetch; let calls = 0;
    globalThis.fetch = async () => { calls++; throw new Error('must not send'); };
    try {
        lockForPwaUpdate();
        for (const method of ['GET', 'POST', 'PATCH', 'DELETE']) {
            for (const path of ['/api/patient-portal/me/dashboard', '/api/patient-portal/auth/login', '/api/patient-portal/auth/refresh', '/api/patient-portal/auth/logout', '/api/settings/general/public']) {
                await assert.rejects(apiRequest(path, { method }), { code: 'PWA_UPDATE_REQUIRED' });
            }
        }
        assert.equal(calls, 0);
    } finally { globalThis.fetch = original; }
});
