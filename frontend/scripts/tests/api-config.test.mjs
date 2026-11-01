import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import axios from 'axios';

function loadConfig(env, cabinetConfig = {}) {
    const source = readFileSync(new URL('../../src/config.js', import.meta.url), 'utf8')
        .replace(/^import cabinetConfig from .*;$/m, '')
        .replaceAll('import.meta.env', 'env')
        .replaceAll('export const ', 'const ');
    return new Function('env', 'cabinetConfig', 'window', `${source}\nreturn { apiPrefix, filePrefix };`)(
        env, cabinetConfig, { location: { origin: 'https://dentalsoft-demo.example.com' } }
    );
}

test('Dokploy resolves login and prefixed API calls to the same origin with one /api', () => {
    const { apiPrefix } = loadConfig(
        { VITE_DOKPLOY: 'true', VITE_API_PREFIX: 'https://old-api.example.com/api' },
        { viteApiPrefix: 'https://cabinet-api.example.com/api' }
    );
    const http = axios.create({ baseURL: apiPrefix });
    for (const path of ['login_check', 'me', 'settings/general', 'sms/settings', 'patients', 'patients/overview', 'assurances']) {
        const expected = `https://dentalsoft-demo.example.com/api/${path}`;
        assert.equal(http.getUri({ url: path }), expected);
        assert.equal(http.getUri({ url: `${apiPrefix}/${path}` }), expected);
    }
});

test('Dokploy supports dashboard and report URL construction with query parameters', () => {
    const { apiPrefix } = loadConfig({ VITE_DOKPLOY: 'true' });
    const url = new URL(`${apiPrefix}/dashboard/admin/cards`);
    url.searchParams.set('from', '2026-11-01');
    const http = axios.create({ baseURL: apiPrefix });
    assert.equal(http.getUri({ url: url.toString() }), 'https://dentalsoft-demo.example.com/api/dashboard/admin/cards?from=2026-11-01');
});

test('other deployments retain cabinet, environment and localhost API configuration', () => {
    assert.equal(loadConfig({}, { viteApiPrefix: 'https://cabinet.example.com/api' }).apiPrefix, 'https://cabinet.example.com/api');
    assert.equal(loadConfig({ VITE_API_PREFIX: 'https://api.example.com/api' }).apiPrefix, 'https://api.example.com/api');
    assert.equal(loadConfig({}).apiPrefix, 'http://localhost:8000/api');
    assert.equal(loadConfig({ VITE_DOKPLOY: 'true' }).filePrefix, '');
});
