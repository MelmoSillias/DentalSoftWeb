import { spawnSync } from 'node:child_process';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { captureRelease } from './pwa-release.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
// Capture the actual checkout, not a potentially stale metadata environment override.
const release = captureRelease({ cwd: root, env: { ...process.env, PWA_RELEASE_METADATA: undefined } });
const metadata = Buffer.from(JSON.stringify(release)).toString('base64');
const result = spawnSync('docker', ['build', '-f', 'docker/frontend/Dockerfile', '--build-arg', `PWA_RELEASE_METADATA=${metadata}`, '--build-arg', `PWA_RELEASE_REVISION=${release.revision}`, ...process.argv.slice(2)], {
    cwd: root,
    stdio: 'inherit'
});
if (result.error) throw result.error;
process.exitCode = result.status ?? 1;
