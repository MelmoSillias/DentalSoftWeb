import assert from 'node:assert/strict';
import test from 'node:test';
import { mkdtemp, mkdir, writeFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { checkArchitecture } from '../scripts/check-architecture.mjs';

test('current application respects module boundaries', async () => {
    assert.deepEqual(await checkArchitecture(fileURLToPath(new URL('..', import.meta.url))), []);
});

test('checker rejects shared-to-domain, cross-domain internals, unresolved lazy imports and HTTP views', async () => {
    const root = await mkdtemp(path.join(tmpdir(), 'patient-architecture-'));
    try {
        const files = {
            'shared/api.js': "export const load = () => import('../domains/auth/private.js');",
            'domains/auth/private.js': 'export const secret = true;',
            'domains/portal/views/Page.vue': "<script>import { secret } from '../../auth/private.js'; fetch('/api/me'); import('./missing.js');</script>",
            'services/old.js': ''
        };
        for (const [name, content] of Object.entries(files)) {
            const file = path.join(root, 'src', name); await mkdir(path.dirname(file), { recursive: true }); await writeFile(file, content);
        }
        const errors = await checkArchitecture(root);
        assert.ok(errors.some((e) => e.includes('shared dépend')));
        assert.ok(errors.some((e) => e.includes('import interne')));
        assert.ok(errors.some((e) => e.includes('import introuvable')));
        assert.ok(errors.some((e) => e.includes('HTTP dans une vue')));
        assert.ok(errors.some((e) => e.includes('Ancien dossier')));
    } finally { await rm(root, { recursive: true, force: true }); }
});
