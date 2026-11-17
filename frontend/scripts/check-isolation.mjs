import assert from 'node:assert/strict';
import { existsSync, lstatSync, readdirSync, readFileSync, realpathSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const rootDir = fileURLToPath(new URL('../', import.meta.url));
const ignoredDirectories = new Set(['.git', 'node_modules', 'dist', '.vite', 'coverage']);

export function isWithin(parent, target) {
    const relative = path.relative(parent, target);
    return relative === '' || (!path.isAbsolute(relative) && relative !== '..' && !relative.startsWith(`..${path.sep}`));
}

export function assertViteIsolation(config) {
    assert.equal(config.server.port, 5181, 'Le port dev doit être 5181');
    assert.equal(config.server.strictPort, true, 'Le port dev doit être strict');
    assert.equal(config.preview.port, 4181, 'Le port preview doit être 4181');
    assert.equal(config.preview.strictPort, true, 'Le port preview doit être strict');
    const hmr = config.server.hmr;
    if (hmr && typeof hmr === 'object') {
        assert.ok(!hmr.host && !hmr.server, 'HMR ne doit pas utiliser un hôte ou serveur externe');
        for (const key of ['port', 'clientPort']) {
            assert.ok(hmr[key] === undefined || hmr[key] === 5181, `HMR ${key} doit utiliser 5181`);
        }
    }
}

export function assertNoOriginalReferences(value, originalDir, label = 'configuration') {
    if (typeof value === 'string') {
        const normalized = value.replace(/\\/g, '/');
        const original = originalDir.replace(/\\/g, '/').replace(/\/$/, '');
        const forbidden = new Set([path.basename(originalDir).toLowerCase(), 'archive', 'frontend-refonte']);
        assert.ok(
            !normalized
                .toLowerCase()
                .split(/[/\s'":]+/)
                .some((segment) => forbidden.has(segment)),
            `${label} référence un frontend voisin ou une archive`
        );
        assert.ok(!normalized.toLowerCase().includes(`${original.toLowerCase()}/`) && normalized.toLowerCase() !== original.toLowerCase(), `${label} référence le portail patient`);
    } else if (value && typeof value === 'object') {
        for (const [key, child] of Object.entries(value)) {
            assertNoOriginalReferences(key, originalDir, label);
            assertNoOriginalReferences(child, originalDir, `${label}.${key}`);
        }
    }
}

export function assertIndependentTree(root, originalDir) {
    const actualRoot = realpathSync(root);
    const actualOriginal = existsSync(originalDir) ? realpathSync(originalDir) : path.resolve(originalDir);
    assert.ok(!isWithin(actualOriginal, actualRoot), 'Le frontend pointe vers le portail patient');

    function visit(directory) {
        for (const entry of readdirSync(directory, { withFileTypes: true })) {
            const filename = path.join(directory, entry.name);
            const info = lstatSync(filename);
            // Contrôler les liens avant les exclusions, notamment node_modules et dist.
            if (info.isSymbolicLink()) {
                const target = realpathSync(filename);
                assert.ok(isWithin(actualRoot, target) && !isWithin(actualOriginal, target), `Lien hors frontend : ${filename}`);
                continue;
            }
            if (info.isDirectory()) {
                if (!ignoredDirectories.has(entry.name)) visit(filename);
            } else if (info.isFile()) {
                const counterpart = path.join(actualOriginal, path.relative(root, filename));
                if (info.nlink > 1 && existsSync(counterpart)) {
                    const other = statSync(counterpart);
                    assert.ok(!(info.ino !== 0 && info.dev === other.dev && info.ino === other.ino), `Lien physique avec le portail patient : ${filename}`);
                }
            }
        }
    }
    visit(root);
}

export async function checkIsolation(root = rootDir) {
    const original = path.resolve(root, '../patient-frontend');
    assertIndependentTree(root, original);
    for (const filename of ['package.json', 'package-lock.json']) {
        const data = JSON.parse(readFileSync(path.join(root, filename), 'utf8'));
        assert.equal(data.name, 'dentalsoft-frontend', `${filename} : nom du package incorrect`);
        assertNoOriginalReferences(data, original, filename);
        if (filename === 'package-lock.json') {
            assert.equal(data.packages[''].name, 'dentalsoft-frontend', 'Nom racine du lock incorrect');
        }
    }
    assertNoOriginalReferences(readFileSync(path.join(root, 'vite.config.mjs'), 'utf8'), original, 'vite.config.mjs');

    // Charger Vite localement : aucun repli sur les dépendances d'un frontend voisin.
    const viteEntry = path.join(root, 'node_modules/vite/dist/node/index.js');
    assert.ok(existsSync(viteEntry), 'Vite local absent : exécuter npm ci dans frontend avant ce contrôle');
    assert.ok(isWithin(realpathSync(root), realpathSync(viteEntry)), 'Vite doit être installé dans frontend');
    const { resolveConfig } = await import(pathToFileURL(viteEntry).href);
    for (const [command, mode] of [
        ['serve', 'development'],
        ['build', 'production']
    ]) {
        // Le runner évite les fichiers temporaires produits par le bundler de configuration.
        const config = await resolveConfig({ root, configFile: path.join(root, 'vite.config.mjs'), configLoader: 'runner', mode }, command);
        assertViteIsolation(config);
        for (const alias of config.resolve.alias) {
            assertNoOriginalReferences(alias.replacement, original, 'alias Vite');
        }
    }
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    checkIsolation()
        .then(() => {
            console.log('Isolation OK : ports stricts, dépendances locales et séparation du portail patient.');
        })
        .catch((error) => {
            console.error(`Isolation échouée : ${error.message}`);
            process.exitCode = 1;
        });
}
