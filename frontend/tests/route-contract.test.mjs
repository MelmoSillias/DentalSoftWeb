import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

// Frozen from the existing router before migration; no runtime dependency on it.
const expected = JSON.parse(readFileSync(new URL('./route-contract.json', import.meta.url), 'utf8'));

test('the refonte preserves public paths, route names, literal redirects and role lists', () => {
    const source = readFileSync(new URL('../src/app/router/index.js', import.meta.url), 'utf8');
    const actual = source.match(/\b(?:path|name|redirect)\s*:\s*["'][^"']*["']|\broles\s*:\s*\[[^\]]*\]/g);
    assert.deepEqual(actual, expected);
});
