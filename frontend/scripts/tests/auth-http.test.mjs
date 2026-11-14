import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { isMutation, stableValue } from '../../src/shared/api/mutationGuard.mjs';

function setup() {
    let rejectResponse;
    let logoutCount = 0;
    let token = 'new-session';
    let locked = false;
    let sends = 0;
    let networkFailures = 0;
    let mutationIssues = 0;
    const source = readFileSync(new URL('../../src/shared/api/http.js', import.meta.url), 'utf8')
        .replace(/^import .*;\r?$/gm, '')
        .replace(/^const loadAuthStore = .*;\r?$/m, 'const loadAuthStore = mockedAuth;')
        .replace(/^const loadRouter = .*;\r?$/m, 'const loadRouter = mockedRouter;')
        .replaceAll('export const ', 'const ')
        .replace('export default http;', '');
    const router = { currentRoute: { value: { name: 'dashboard' } }, replace: () => {} };
    const auth = {
        logout: () => {
            logoutCount++;
        },
        setDeviceBlock: () => {}
    };
    const axios = {
        defaults: { adapter: 'mock' },
        getAdapter: () => async () => {
            sends++;
        },
        create: () => ({
            interceptors: {
                request: { use: () => {} },
                response: {
                    use: (_, reject) => {
                        rejectResponse = reject;
                    }
                }
            }
        })
    };
    global.localStorage = {
        getItem: () => token,
        removeItem: () => {
            token = null;
        }
    };
    const api = new Function(
        'axios',
        'apiPrefix',
        'noteHttpFailure',
        'noteTransportSuccess',
        'getDeviceMetadata',
        'mockedAuth',
        'mockedRouter',
        'isMutation',
        'stableValue',
        'runMutation',
        'reportMutationIssue',
        'assertPwaRequestsAllowed',
        `${source}\nreturn { getMessage: getHttpErrorMessage, send: guardedTransport };`
    )(
        axios,
        '/api',
        () => {
            networkFailures++;
        },
        () => {},
        () => ({}),
        async () => () => auth,
        async () => router,
        isMutation,
        stableValue,
        (_identity, send) => send('test-key'),
        () => {
            mutationIssues++;
        },
        () => {
            if (locked) throw Object.assign(new Error('Mise à jour obligatoire'), { code: 'PWA_UPDATE_REQUIRED' });
        }
    );
    return {
        ...api,
        reject: (error) => rejectResponse(error),
        logoutCount: () => logoutCount,
        lock: () => {
            locked = true;
        },
        sends: () => sends,
        networkFailures: () => networkFailures,
        mutationIssues: () => mutationIssues
    };
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

test('401 after credential, role or account invalidation logs out the current session', async () => {
    const context = setup();
    await assert.rejects(context.reject({ response: { status: 401 }, config: { url: 'me', headers: { Authorization: 'Bearer new-session' } } }));
    await new Promise(setImmediate);
    assert.equal(context.logoutCount(), 1);
});

test('mandatory PWA lock rejects reads and mutations before the canonical transport', async () => {
    const context = setup();
    await context.send({ method: 'get' });
    assert.equal(context.sends(), 1);
    context.lock();
    for (const method of ['get', 'post', 'patch', 'delete']) {
        await assert.rejects(context.send({ method }), { code: 'PWA_UPDATE_REQUIRED' });
    }
    assert.equal(context.sends(), 1);
});

test('PWA lock errors preserve the session and do not report network or uncertain mutations', async () => {
    const context = setup();
    const error = Object.assign(new Error('Mise à jour obligatoire'), { code: 'PWA_UPDATE_REQUIRED', config: { method: 'post' } });
    await assert.rejects(context.reject(error), { code: 'PWA_UPDATE_REQUIRED' });
    await new Promise(setImmediate);
    assert.equal(context.logoutCount(), 0);
    assert.equal(context.networkFailures(), 0);
    assert.equal(context.mutationIssues(), 0);
    assert.equal(error.message, 'Mise à jour obligatoire');
});
