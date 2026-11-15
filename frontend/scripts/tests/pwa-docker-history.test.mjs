import { test } from 'node:test';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { captureRelease } from '../pwa-release.mjs';

const prepareScript = fileURLToPath(new URL('../../../docker/frontend/prepare-pwa-history.sh', import.meta.url));
const git = (cwd, ...args) => execFileSync('git', ['--no-pager', ...args], { cwd, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
const prepare = (cwd, metadata = '') => execFileSync('sh', [prepareScript], {
    cwd,
    env: { ...process.env, PWA_RELEASE_METADATA: metadata, GIT_TERMINAL_PROMPT: '0' },
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'pipe'],
    timeout: 20000
});

function fixture(t) {
    const root = mkdtempSync(join(tmpdir(), 'pwa-docker-history-'));
    t.after(() => rmSync(root, { recursive: true, force: true }));
    git(root, 'init', '--initial-branch=main');
    git(root, 'config', 'user.email', 'test@example.com');
    git(root, 'config', 'user.name', 'PWA test');
    return root;
}

function commit(cwd, message) {
    git(cwd, '-c', 'commit.gpgsign=false', '-c', 'core.hooksPath=/dev/null', 'commit', '--allow-empty', '-m', message);
    return git(cwd, 'rev-parse', 'HEAD');
}

test('Docker preparation restores mandatory history without moving the deployed revision', (t) => {
    const origin = fixture(t);
    const required = commit(origin, 'Initial release\n\nPWA-Update: required');
    const deployed = commit(origin, 'Optional release');
    const checkout = join(origin, 'checkout');
    git(origin, 'clone', '--depth=1', pathToFileURL(origin).href, checkout);
    assert.equal(git(checkout, 'rev-parse', '--is-shallow-repository'), 'true');
    assert.throws(() => captureRelease({ cwd: checkout, env: {} }), /Shallow/);
    commit(origin, 'Newer remote release\n\nPWA-Update: required');

    prepare(checkout);

    assert.equal(git(checkout, 'rev-parse', '--is-shallow-repository'), 'false');
    assert.equal(git(checkout, 'rev-parse', 'HEAD'), deployed);
    const release = captureRelease({ cwd: checkout, env: {} });
    assert.equal(release.revision, deployed);
    assert.equal(release.generation, 2);
    assert.deepEqual(release.minimum, { revision: required, generation: 1 });
    assert.equal(release.policy, 'optional');
});

test('Docker preparation needs no origin for full checkouts or supplied metadata', (t) => {
    const root = fixture(t);
    commit(root, 'Full checkout');
    prepare(root);
    // Un dépôt absent est acceptable uniquement si la CI fournit les métadonnées.
    prepare(tmpdir(), 'provided-by-ci');
});

test('Docker preparation fails closed when a shallow origin cannot be fetched', (t) => {
    const origin = fixture(t);
    commit(origin, 'Initial release');
    commit(origin, 'Optional release');
    const checkout = join(origin, 'checkout');
    git(origin, 'clone', '--depth=1', pathToFileURL(origin).href, checkout);
    git(checkout, 'remote', 'remove', 'origin');
    assert.throws(() => prepare(checkout));
    assert.equal(git(checkout, 'rev-parse', '--is-shallow-repository'), 'true');
});
