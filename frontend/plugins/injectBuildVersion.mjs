import { readFileSync } from 'node:fs';
import { captureRelease } from '../scripts/pwa-release.mjs';
import { validateRelease } from '../src/shared/lib/pwaRelease.mjs';

export function injectBuildVersion() {
    let release = null;
    return {
        name: 'inject-build-version',
        config(_config, { command }) {
            if (command === 'build') {
                release = process.env.PWA_RELEASE_FILE ? validateRelease(JSON.parse(readFileSync(process.env.PWA_RELEASE_FILE, 'utf8'))) : captureRelease();
            }
            return {
                define: {
                    'import.meta.env.VITE_APP_BUILD_ID': JSON.stringify(release?.buildId || 'dev'),
                    'import.meta.env.VITE_APP_RELEASE': JSON.stringify(release)
                }
            };
        },
        transformIndexHtml(html) {
            return html.replaceAll('%APP_BUILD_VERSION%', release?.buildId || 'dev');
        },
        generateBundle() {
            if (!release) return;
            this.emitFile({ type: 'asset', fileName: 'release.json', source: JSON.stringify(release) + '\n' });
            const { revision, generation, lineage, buildId } = release;
            const identity = JSON.stringify({ revision, generation, lineage, buildId });
            this.emitFile({ type: 'asset', fileName: 'pwa-release-worker.js', source: `self.addEventListener('message', event => { if (event.data?.type === 'GET_PWA_RELEASE') event.ports[0]?.postMessage(${identity}); });\n` });
        }
    };
}
