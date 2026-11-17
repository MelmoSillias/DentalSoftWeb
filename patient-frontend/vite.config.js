import { fileURLToPath, URL } from 'node:url';
import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';
import cabinetConfig from './src/generated/cabinet-config.generated.js';
import { injectBuildVersion } from './plugins/injectBuildVersion.mjs';
import { patientPwaOptions } from './plugins/patientPwa.mjs';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, fileURLToPath(new URL('.', import.meta.url)), 'VITE_');
    // Production builds are installable by default; only the exact string false opts out.
    const enablePwa = env.VITE_ENABLE_PWA !== 'false';
    return {
        define: { 'import.meta.env.VITE_ENABLE_PWA': JSON.stringify(enablePwa) },
        plugins: [injectBuildVersion(), vue(), tailwindcss(), enablePwa && VitePWA(patientPwaOptions(cabinetConfig))],
        resolve: { alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) } }
    };
});
