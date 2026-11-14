import assert from 'node:assert/strict';
import { linkSync, mkdirSync, mkdtempSync, rmSync, symlinkSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';
import { assertIndependentTree, assertNoOriginalReferences, assertViteIsolation, isWithin } from '../check-isolation.mjs';

const goodConfig = () => ({ server: { port: 5181, strictPort: true }, preview: { port: 4181, strictPort: true } });

test('ports stricts et HMR sur le serveur dev', () => {
    assertViteIsolation(goodConfig());
    assertViteIsolation({ ...goodConfig(), server: { ...goodConfig().server, hmr: { port: 5181, clientPort: 5181 } } });
    for (const section of ['server', 'preview']) {
        for (const override of [{ port: 5180 }, { strictPort: false }]) {
            const config = goodConfig();
            Object.assign(config[section], override);
            assert.throws(() => assertViteIsolation(config));
        }
    }
    for (const hmr of [{ port: 5180 }, { clientPort: 5180 }, { host: 'production.example' }, { server: {} }]) {
        const config = goodConfig();
        config.server.hmr = hmr;
        assert.throws(() => assertViteIsolation(config));
    }
});

test('références locales Unix, Windows et lock imbriqué', () => {
    const original = path.resolve('patient-frontend');
    for (const reference of ['file:../patient-frontend', 'link:../../patient-frontend/package', '..\\patient-frontend\\package', path.join(original, 'src'), '../archive/frontend/src', '..\\archive\\frontend\\src', '../frontend-refonte/src']) {
        assert.throws(() => assertNoOriginalReferences({ packages: { dependency: { resolved: reference } } }, original));
    }
    assertNoOriginalReferences({ name: 'dentalsoft-frontend', resolved: './src', local: path.resolve('frontend/node_modules/vite') }, original);
    assertNoOriginalReferences({ resolved: '../patient-frontend-tools/package' }, original);
});

test('un préfixe de nom ne constitue pas un chemin enfant', () => {
    assert.equal(isWithin(path.resolve('patient-frontend'), path.resolve('patient-frontend-tools')), false);
    assert.equal(isWithin(path.resolve('patient-frontend'), path.resolve('frontend')), false);
    assert.equal(isWithin(path.resolve('frontend'), path.resolve('frontend/src')), true);
});

function fixture(t) {
    const base = mkdtempSync(path.join(os.tmpdir(), 'dentalsoft-isolation-'));
    t.after(() => rmSync(base, { recursive: true, force: true }));
    const original = path.join(base, 'patient-frontend');
    const copy = path.join(base, 'frontend');
    mkdirSync(original);
    mkdirSync(copy);
    writeFileSync(path.join(original, 'file.txt'), 'original');
    writeFileSync(path.join(copy, 'file.txt'), 'copie');
    return { original, copy };
}

test('accepte un frontend indépendant du portail patient', (t) => {
    const { original, copy } = fixture(t);
    assertIndependentTree(copy, original);
});

test('rejette une jonction node_modules vers original avant exclusion', (t) => {
    const { original, copy } = fixture(t);
    symlinkSync(original, path.join(copy, 'node_modules'), process.platform === 'win32' ? 'junction' : 'dir');
    assert.throws(() => assertIndependentTree(copy, original), /Lien hors frontend/);
});

test('rejette une racine liée à original', (t) => {
    const { original, copy } = fixture(t);
    const alias = path.join(copy, 'alias');
    symlinkSync(original, alias, process.platform === 'win32' ? 'junction' : 'dir');
    assert.throws(() => assertIndependentTree(alias, original), /frontend pointe/);
});

test('rejette un lien physique vers le fichier homologue', (t) => {
    const { original, copy } = fixture(t);
    rmSync(path.join(copy, 'file.txt'));
    linkSync(path.join(original, 'file.txt'), path.join(copy, 'file.txt'));
    assert.throws(() => assertIndependentTree(copy, original), /Lien physique/);
});
