import { readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

function readPackageVersion() {
    try {
        const pkg = JSON.parse(
            readFileSync(path.resolve(__dirname, '../package.json'), 'utf8')
        );
        return pkg.version || '0.0.0';
    } catch {
        return '0.0.0';
    }
}

/**
 * Injecte un identifiant de build dans index.html et import.meta.env à chaque `vite build`.
 * Permet le cache long sur les assets hashés tout en forçant le rechargement de l'entrée HTML.
 */
export function injectBuildVersion() {
    let buildId = 'dev';

    return {
        name: 'inject-build-version',
        config(_config, { command }) {
            if (command === 'build') {
                buildId = `${readPackageVersion()}-${Date.now().toString(36)}`;
            } else {
                buildId = 'dev';
            }

            return {
                define: {
                    'import.meta.env.VITE_APP_BUILD_ID': JSON.stringify(buildId),
                },
            };
        },
        transformIndexHtml(html) {
            return html.replaceAll('%APP_BUILD_VERSION%', buildId);
        },
    };
}
