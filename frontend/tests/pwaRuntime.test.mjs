import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import { createReleaseGate, fetchRelease, requiresUpdate } from '../src/shared/lib/pwaRelease.mjs';
import { releaseFromHistory } from '../scripts/pwa-release.mjs';

const history = [1, 2, 3].map((n) => ({ revision: String(n).padStart(40, '0'), trailers: n === 2 ? 'required' : '' }));
const old = releaseFromHistory(history.slice(0, 1), 'old');
const latest = releaseFromHistory(history, 'new');
const source = readFileSync(new URL('../src/shared/composables/usePwaUpdate.js', import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '')
    .replaceAll('import.meta.env', 'ENV')
    .replaceAll('export function', 'function');

function harness({ waitingRelease = latest, state = 'installed', saved = null, enabled = true, production = true } = {}) {
    const events = {};
    const timers = [];
    const messages = [];
    let reloads = 0;
    let online = true;
    let calls = 0;
    let value = latest;
    let updateError = null;
    const worker = {
        state,
        postMessage(message, ports) {
            if (message.type === 'GET_PWA_RELEASE') ports[0].postMessage(waitingRelease);
            else messages.push(message);
        }
    };
    const registration = {
        waiting: worker,
        active: null,
        update: async () => {
            if (updateError) throw updateError;
        },
        addEventListener: () => {}
    };
    const window = {
        addEventListener: (name, fn) => {
            events[name] = fn;
        },
        setInterval: (fn) => {
            events.interval = fn;
        },
        location: {
            reload: () => {
                reloads++;
            }
        }
    };
    const context = {
        ENV: { PROD: production, VITE_ENABLE_PWA: enabled, BASE_URL: '/', VITE_APP_RELEASE: old },
        ref: (value) => ({ value }),
        readonly: (value) => value,
        onMounted: () => {},
        logAppError: () => {},
        createReleaseGate,
        fetchRelease,
        requiresUpdate,
        MessageChannel,
        lockForPwaUpdate: () => {},
        setTimeout: (fn) => {
            timers.push(fn);
            return timers.length;
        },
        clearTimeout: () => {},
        window,
        document: {
            visibilityState: 'visible',
            addEventListener: (name, fn) => {
                events[name] = fn;
            }
        },
        navigator: {
            serviceWorker: {
                register: async () => registration,
                addEventListener: (name, fn) => {
                    events[name] = fn;
                },
                controller: worker
            }
        },
        localStorage: {
            getItem: () => saved,
            setItem: (_key, text) => {
                saved = text;
            }
        },
        fetch: async () => {
            calls++;
            if (!online) throw new Error('offline');
            return { ok: true, json: async () => value };
        }
    };
    vm.runInNewContext(source + '\nglobalThis.api = { start, checkForUpdates, view: usePwaUpdate() };', context);
    return {
        ...context.api,
        registration,
        events,
        messages,
        timers,
        get reloads() {
            return reloads;
        },
        get calls() {
            return calls;
        },
        get savedRelease() {
            return JSON.parse(saved);
        },
        setUpdateError: (next) => {
            updateError = next;
        },
        setSavedRelease: (next) => {
            saved = JSON.stringify(next);
        },
        setOnline: (next) => {
            online = next;
        },
        setRemote: (next) => {
            value = next;
        }
    };
}

test('disabled PWA and development do not register, check or restore a persisted gate', () => {
    for (const options of [{ enabled: false }, { production: false }]) {
        const app = harness({ ...options, saved: JSON.stringify(latest) });
        app.start();
        assert.equal(app.calls, 0);
        assert.equal(app.view.mandatory.value, false);
        assert.deepEqual(Object.keys(app.events), []);
    }
});

test('mandatory blocks immediately, remains blocked offline, and retries without reloads', async () => {
    const app = harness();
    await app.checkForUpdates();
    assert.equal(app.view.mandatory.value, true);
    assert.equal(app.view.needRefresh.value, true);
    await app.view.dismissUpdate();
    assert.equal(app.view.needRefresh.value, true);
    app.setOnline(false);
    await app.checkForUpdates();
    assert.equal(app.view.mandatory.value, true);
    assert.match(app.view.errorMessage.value, /connexion/);
    assert.equal(app.reloads, 0);
    assert.equal(app.messages.length, 0);
    app.setOnline(true);
    await app.checkForUpdates();
    assert.equal(app.view.errorMessage.value, '');
});

test('older waiting worker and still installing worker cannot be activated', async () => {
    for (const options of [{ waitingRelease: old }, { state: 'installing' }]) {
        const app = harness(options);
        await app.checkForUpdates();
        assert.equal(app.view.mandatory.value, true);
        assert.equal(app.view.needRefresh.value, false);
        await app.view.applyUpdate();
        assert.equal(app.messages.length, 0);
        assert.equal(app.reloads, 0);
        assert.equal(app.view.installing.value, false);
    }
});

test('eligible installed worker activates only on click; timeout does not reload', async () => {
    const app = harness();
    await Promise.all([app.checkForUpdates(), app.checkForUpdates()]);
    assert.equal(app.calls, 1);
    assert.equal(app.messages.length, 0);
    await app.view.applyUpdate();
    assert.equal(app.messages[0].type, 'SKIP_WAITING');
    app.timers.at(-1)();
    assert.equal(app.view.installing.value, false);
    assert.equal(app.reloads, 0);
});

test('optional can be dismissed, but a later mandatory instruction overrides dismissal', async () => {
    const app = harness();
    app.setRemote({ ...latest, minimum: null });
    await app.checkForUpdates();
    assert.equal(app.view.mandatory.value, false);
    await app.view.dismissUpdate();
    await app.checkForUpdates();
    assert.equal(app.view.needRefresh.value, false);
    app.setRemote(latest);
    await app.checkForUpdates();
    assert.equal(app.view.needRefresh.value, true);
    assert.equal(app.view.mandatory.value, true);
});

test('persisted obligation blocks offline startup; other tab activation reloads only once', async () => {
    const app = harness({ saved: JSON.stringify(latest) });
    app.setOnline(false);
    app.start();
    assert.equal(app.view.mandatory.value, true);
    await app.checkForUpdates();
    await app.events.controllerchange();
    await app.events.controllerchange();
    assert.equal(app.reloads, 1);
});

test('an already installed eligible worker remains installable when the network update check fails', async () => {
    const app = harness({ saved: JSON.stringify(latest) });
    app.setOnline(false);
    app.setUpdateError(new Error('offline SW check'));
    app.start();
    await app.checkForUpdates();
    assert.equal(app.view.needRefresh.value, true);
    await app.view.applyUpdate();
    assert.equal(app.messages[0]?.type, 'SKIP_WAITING');
    assert.equal(app.reloads, 0);
});

test('a stronger threshold invalidates a ready worker even when update() fails', async () => {
    const app = harness();
    await app.checkForUpdates();
    const stronger = releaseFromHistory([...history, { revision: '4'.padStart(40, '0'), trailers: 'required' }], 'stronger');
    app.setRemote(stronger);
    app.setUpdateError(new Error('offline SW check'));
    await app.checkForUpdates();
    assert.equal(app.view.needRefresh.value, false);
    await app.view.applyUpdate();
    assert.equal(app.messages.length, 0);
});

test('a stale tab cannot downgrade a persisted required threshold', async () => {
    const app = harness();
    app.setRemote({ ...latest, minimum: null });
    app.setSavedRelease(latest);
    await app.checkForUpdates();
    assert.equal(app.view.mandatory.value, true);
    assert.equal(app.savedRelease.minimum.generation, latest.minimum.generation);
    app.start();
    await app.checkForUpdates();
    app.setSavedRelease({ ...latest, minimum: null });
    app.events.storage({ key: 'dentalsoft-pwa-release-v1:/', newValue: JSON.stringify({ ...latest, minimum: null }) });
    assert.equal(app.savedRelease.minimum.generation, latest.minimum.generation);
});

test('storage fallback propagates the obligation and focus/online/interval check again', async () => {
    const app = harness();
    app.setRemote({ ...latest, minimum: null });
    app.start();
    await app.checkForUpdates();
    app.events.storage({ key: 'dentalsoft-pwa-release-v1:/', newValue: JSON.stringify(latest) });
    assert.equal(app.view.mandatory.value, true);
    for (const event of ['focus', 'online', 'visibilitychange', 'interval']) {
        const before = app.calls;
        app.events[event]();
        await app.checkForUpdates();
        assert.equal(app.calls, before + 1);
    }
});
