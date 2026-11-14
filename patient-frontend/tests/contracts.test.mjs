import assert from 'node:assert/strict';
import test from 'node:test';
import * as portal from '../src/domains/patient-portal/api.js';
import * as auth from '../src/domains/auth/api.js';

test('all patient services use canonical routes and preserve payloads', async () => {
    const original = globalThis.fetch; const calls = [];
    globalThis.fetch = async (url, options) => {
        assert.equal(options.cache, 'no-store', 'patient API bypasses the browser HTTP cache');
        calls.push({ path: new URL(url, 'http://localhost').pathname, ...options });
        return new Response(JSON.stringify({ items: [] }), { headers: { 'Content-Type': 'application/json' } });
    };
    try {
        for (const [fn, path] of [
            ['fetchPatientDashboard', 'dashboard'], ['fetchPatientConsultations', 'consultations'],
            ['fetchPatientAppointments', 'appointments'], ['fetchPatientPayments', 'payments'],
            ['fetchPatientDocuments', 'billing-documents'], ['fetchPatientAppreciations', 'reviews'], ['fetchPatientProfile', 'profile']
        ]) {
            await portal[fn]('jwt');
            assert.equal(calls.at(-1).path, `/api/patient-portal/me/${path}`);
            assert.equal(calls.at(-1).headers.Authorization, 'Bearer jwt');
        }
        for (const [fn, path] of [['fetchConsultationDetail', 'consultations/7'], ['fetchDocumentDetail', 'billing-documents/7'], ['fetchPaymentReceipt', 'payments/7/receipt']]) {
            await portal[fn]('jwt', 7); assert.equal(calls.at(-1).path, `/api/patient-portal/me/${path}`);
        }
        await portal.submitConsultationAppreciation('jwt', 7, { note: 5 });
        assert.equal(calls.at(-1).path, '/api/patient-portal/me/consultations/7/review');
        assert.equal(calls.at(-1).method, 'POST'); assert.equal(calls.at(-1).body, '{"note":5}');
        await portal.submitAnonymousAppreciation({ message: 'Merci' });
        assert.equal(calls.at(-1).path, '/api/public/review-submissions');
        assert.equal(calls.at(-1).headers.Authorization, undefined);
        await portal.fetchPublicPortalSettings(); assert.equal(calls.at(-1).path, '/api/settings/general/public');
        await auth.loginPatient('patient', 'password');
        assert.equal(calls.at(-1).path, '/api/patient-portal/auth/login');
        assert.deepEqual(JSON.parse(calls.at(-1).body), { email: 'patient', password: 'password' });
        for (const name of ['refreshPatient', 'logoutPatient']) {
            await auth[name]('secret');
            assert.equal(calls.at(-1).path, `/api/patient-portal/auth/${name === 'refreshPatient' ? 'refresh' : 'logout'}`);
            assert.deepEqual(JSON.parse(calls.at(-1).body), { refresh_token: 'secret' });
            assert.equal(calls.at(-1).headers.Authorization, undefined);
        }
        await auth.fetchCurrentUser('jwt'); assert.equal(calls.at(-1).path, '/api/me');
        await auth.updatePassword('jwt', 'old', 'new');
        assert.equal(calls.at(-1).path, '/api/me/password'); assert.equal(calls.at(-1).method, 'PATCH');
        assert.deepEqual(JSON.parse(calls.at(-1).body), { oldPassword: 'old', newPassword: 'new' });
    } finally { globalThis.fetch = original; }
});
