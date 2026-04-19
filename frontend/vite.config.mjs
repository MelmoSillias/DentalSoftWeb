import { fileURLToPath, URL } from 'node:url';

import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vite';
import cabinetConfig from './src/generated/cabinet-config.generated.js';

const cabinetPwa = cabinetConfig.pwa || {};

// https://vitejs.dev/config/
export default defineConfig({
    optimizeDeps: {
        noDiscovery: true,
        esbuildOptions: {
            logOverride: {
                'missing-source-map': 'silent'
            }
        }
    },
    server: {
        sourcemapIgnoreList: (sourcePath) =>
            sourcePath.includes('/node_modules/@microsoft/fetch-event-source/')
            || sourcePath.includes('\\node_modules\\@microsoft\\fetch-event-source\\'),
        port: 5180,
        
    },
    plugins: [
        vue(),
        // PWA plugin: génère le service worker et le manifest
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: cabinetPwa.includeAssets || ['favicon.ico', 'robots.txt', 'icons/*.svg'],
            manifest: {
                name: cabinetPwa.name || 'DENTALSOFT',
                short_name: cabinetPwa.shortName || 'DENTALSOFT',
                description: cabinetPwa.description || 'Application de gestion de cabinet dentaire',
                theme_color: cabinetPwa.themeColor || '#5ad6f5',
                background_color: cabinetPwa.backgroundColor || '#ffffff',
                start_url: cabinetPwa.startUrl || '/',
                display: cabinetPwa.display || 'standalone',
                icons: cabinetPwa.icons || [{ src: 'logo.png', sizes: '512x512', type: 'image/png' }]
            }
        }),
        Components({
            resolvers: [PrimeVueResolver()]
        })
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./src', import.meta.url))
        }
    }
});
