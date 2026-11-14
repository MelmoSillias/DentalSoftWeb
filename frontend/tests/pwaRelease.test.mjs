import { test } from 'node:test';
import assert from 'node:assert/strict';
import { captureRelease, releaseFromHistory } from '../scripts/pwa-release.mjs';
import { createReleaseGate, fetchRelease, requiresUpdate, validateRelease } from '../src/utils/pwaRelease.mjs';
import { injectBuildVersion } from '../plugins/injectBuildVersion.mjs';
import vm from 'node:vm';
import { assertPwaRequestsAllowed, lockForPwaUpdate } from '../src/utils/pwaUpdateLock.mjs';

const revision = (n) => n.toString(16).padStart(40, '0');
const history = (...trailers) => trailers.map((value, index) => ({ revision: revision(index + 1), trailers: value }));
const release = (...trailers) => releaseFromHistory(history(...trailers), 'test-build');

test('optional after required preserves the minimum, and a later required raises it', () => {
    const value = release('', 'required', '', 'optional');
    assert.equal(value.policy, 'optional');
    assert.deepEqual(value.minimum, { revision: revision(2), generation: 2 });
    assert.equal(release('', 'required', '', 'required').minimum.generation, 4);
    assert.equal(release('', '').minimum, null);
    assert.throws(() => release('requiredd'), /Invalid/);
    assert.throws(() => release('required\noptional'), /Invalid/);
});

test('threshold compares revisions, not build timestamps; current and newer clients are allowed', () => {
    const remote = release('', 'required', '');
    assert.equal(requiresUpdate(release(''), remote), true);
    assert.equal(requiresUpdate(release('', 'required'), remote), false);
    assert.equal(requiresUpdate(remote, remote), false);
    assert.equal(requiresUpdate(null, remote), true);
    assert.equal(requiresUpdate({}, remote), true);
    assert.equal(requiresUpdate({ ...remote, generation: 2, revision: revision(99) }, remote), true);
    assert.equal(requiresUpdate({ ...remote, lineage: revision(99) }, remote), true);
    assert.equal(requiresUpdate(null, release('')), false);
});

test('known mandatory state cannot be dismissed by an older/optional response', () => {
    const gate = createReleaseGate(release(''));
    assert.equal(gate.accept(release('', 'required')), true);
    assert.equal(gate.accept(release('')), true);
    assert.equal(gate.mandatory, true);
    assert.throws(() => gate.accept({}), /Invalid/);
    assert.equal(gate.mandatory, true);
});

test('capture rejects shallow/missing history and mismatching source revision', () => {
    assert.throws(() => captureRelease({ env: {}, gitReader: () => 'true' }), /Shallow/);
    assert.throws(
        () =>
            captureRelease({
                env: {},
                gitReader: () => {
                    throw new Error('no git');
                }
            }),
        /Supply PWA_RELEASE_METADATA/
    );
    const gitReader = (...args) => (args[0] === 'rev-parse' ? 'false' : `${revision(1)}\x1frequired\n\x1e\n${revision(2)}\x1f\x1e`);
    assert.equal(captureRelease({ env: {}, gitReader }).minimum.generation, 1);
    assert.throws(() => captureRelease({ env: { PWA_RELEASE_REVISION: revision(9) }, gitReader }), /does not match/);
});

test('Docker metadata works without git, validates schema and expected source hash', () => {
    const value = release('', 'required', '');
    const env = { PWA_RELEASE_METADATA: Buffer.from(JSON.stringify(value)).toString('base64'), PWA_RELEASE_REVISION: value.revision };
    assert.deepEqual(
        captureRelease({
            env,
            gitReader: () => {
                throw new Error('must not call git');
            }
        }),
        value
    );
    assert.throws(() => captureRelease({ env: { ...env, PWA_RELEASE_REVISION: revision(9) } }), /does not match/);
    assert.throws(() => validateRelease({ ...value, minimum: { revision: revision(1), generation: 99 } }), /minimum/);
});

test('metadata fetch uses no-store and rejects HTML, HTTP failures and invalid metadata', async () => {
    const value = release('');
    assert.deepEqual(
        await fetchRelease(async (url, options) => {
            assert.equal(url, '/release.json');
            assert.equal(options.cache, 'no-store');
            assert.ok(options.signal);
            return { ok: true, json: async () => value };
        }, '/release.json'),
        value
    );
    await assert.rejects(
        fetchRelease(async () => ({ ok: false, status: 503 }), '/release.json'),
        /503/
    );
    await assert.rejects(
        fetchRelease(async () => ({ ok: true, json: async () => ({}) }), '/release.json'),
        /Invalid/
    );
    await assert.rejects(
        fetchRelease(async () => {
            throw new Error('offline');
        }, '/release.json'),
        /offline/
    );
});

test('document HTTP lock permanently suspends requests without touching storage', () => {
    assert.doesNotThrow(assertPwaRequestsAllowed);
    lockForPwaUpdate();
    assert.throws(assertPwaRequestsAllowed, { code: 'PWA_UPDATE_REQUIRED' });
    lockForPwaUpdate();
    assert.throws(assertPwaRequestsAllowed, { code: 'PWA_UPDATE_REQUIRED' });
});

test('plugin emits standalone metadata and an installed worker identity responder', () => {
    const plugin = injectBuildVersion();
    plugin.config({}, { command: 'build' });
    const assets = [];
    plugin.generateBundle.call({ emitFile: (asset) => assets.push(asset) });
    const metadata = JSON.parse(assets.find((asset) => asset.fileName === 'release.json').source);
    let listener;
    vm.runInNewContext(assets.find((asset) => asset.fileName === 'pwa-release-worker.js').source, {
        self: {
            addEventListener: (_event, callback) => {
                listener = callback;
            }
        }
    });
    let identity;
    listener({
        data: { type: 'GET_PWA_RELEASE' },
        ports: [
            {
                postMessage: (value) => {
                    identity = value;
                }
            }
        ]
    });
    assert.equal(identity.buildId, metadata.buildId);
    assert.equal(identity.revision, metadata.revision);
    assert.ok(plugin.transformIndexHtml('%APP_BUILD_VERSION%').includes(metadata.revision));
});
