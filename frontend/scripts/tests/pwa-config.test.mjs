import assert from 'node:assert/strict';
import test from 'node:test';
import configure from '../../vite.config.mjs';

test('production builds enable PWA by default, with an explicit false opt-out', () => {
    const previous = process.env.VITE_ENABLE_PWA;
    try {
        for (const [value, enabled] of [
            [undefined, true],
            ['', true],
            ['true', true],
            ['false', false]
        ]) {
            if (value === undefined) delete process.env.VITE_ENABLE_PWA;
            else process.env.VITE_ENABLE_PWA = value;
            const config = configure({ command: 'build', mode: 'production' });
            assert.equal(config.define['import.meta.env.VITE_ENABLE_PWA'], JSON.stringify(enabled));
            assert.equal(
                config.plugins.flat(Infinity).some((plugin) => plugin?.name === 'vite-plugin-pwa'),
                enabled
            );
        }
        delete process.env.VITE_ENABLE_PWA;
        const development = configure({ command: 'serve', mode: 'development' });
        assert.equal(development.define['import.meta.env.VITE_ENABLE_PWA'], 'false');
        assert.equal(
            development.plugins.flat(Infinity).some((plugin) => plugin?.name === 'vite-plugin-pwa'),
            false
        );
    } finally {
        if (previous === undefined) delete process.env.VITE_ENABLE_PWA;
        else process.env.VITE_ENABLE_PWA = previous;
    }
});
