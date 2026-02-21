import { fileURLToPath, URL } from 'node:url';

import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vite';

// https://vitejs.dev/config/
export default defineConfig({
    optimizeDeps: {
        noDiscovery: true
    },
    plugins: [
        vue(),
        // PWA plugin: génère le service worker et le manifest
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'robots.txt', 'icons/*.svg'],
            manifest: {
                name: 'CETIG SOFTWARE',
                short_name: 'CETIG',
                description: 'Application PWA pour CETIG SOFTWARE',
                theme_color: '#4e73df',
                start_url: '/',
                display: 'standalone',
                icons: [
                    { src: 'icons/icon-192.svg', sizes: '192x192', type: 'image/svg+xml' },
                    { src: 'icons/icon-512.svg', sizes: '512x512', type: 'image/svg+xml' }
                ]
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
