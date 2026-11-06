import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

function setup() {
    let rejectResponse;
    let logoutCount = 0;
    let token = 'new-session';
    const source = readFileSync(new URL('../../src/service/http.js', import.meta.url), 'utf8')
        .replace(/^import .*;\r?$/gm, '')
        .replace(/^const loadAuthStore = .*;\r?$/m, 'const loadAuthStore = mockedAuth;')
        .replace(/^const loadRouter = .*;\r?$/m, 'const loadRouter = mockedRouter;')
        .replaceAll('export const ', 'const ').replace('export default http;', '');
    const router = { currentRoute: { value: { name: 'dashboard' } }, replace: () => {} };
    const auth = { logout: () => { logoutCount++; }, setDeviceBlock: () => {} };
    const axios = { create: () => ({ interceptors: {
        request: { use: () => {} }, response: { use: (_, reject) => { rejectResponse = reject; } }
    } }) };
    globalThis.localStorage = { getItem: () => token, removeItem: () => { token = null; } };
    const getMessage = new Function('axios', 'apiPrefix', 'noteHttpFailure', 'noteTransportSuccess', 'getDeviceMetadata', 'mockedAuth', 'mockedRouter',
        `${source}\nreturn getHttpErrorMessage;`)(axios, '/api', () => {}, () => {}, () => ({}),
        async () => () => auth, async () => router);
    return { getMessage, reject: (error) => rejectResponse(error), logoutCount: () => logoutCount };
}

test('login 401 reports incorrect credentials without triggering global session logout', async () => {
    const context = setup();
    const error = { response: { status: 401, data: { message: 'details' } }, config: { url: 'login_check' } };
    assert.equal(context.getMessage(error), 'Identifiants incorrects.');
    await assert.rejects(context.reject(error));
    await new Promise(setImmediate);
    assert.equal(context.logoutCount(), 0);
});

test('a delayed 401 from an old token does not log out a new session', async () => {
    const context = setup();
    await assert.rejects(context.reject({ response: { status: 401 }, config: { url: 'me', headers: { Authorization: 'Bearer old-session' } } }));
    await new Promise(setImmediate);
    assert.equal(context.logoutCount(), 0);
});

test('401 for the current token logs out the current session', async () => {
    const context = setup();
    await assert.rejects(context.reject({ response: { status: 401 }, config: { url: 'me', headers: { Authorization: 'Bearer new-session' } } }));
    await new Promise(setImmediate);
    assert.equal(context.logoutCount(), 1);
});
