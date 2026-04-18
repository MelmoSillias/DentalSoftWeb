import { fileURLToPath, URL } from 'node:url';

import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vite';

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
        port: 5180, // Remplacez 3000 par le port souhaité
        strictPort: true, // optionnel : empêche de passer au port suivant si celui-ci est occupé 
    },
    plugins: [
        vue(),
        // PWA plugin: génère le service worker et le manifest
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'robots.txt', 'icons/*.svg'],
            manifest: {
                name: 'DENTALSOFT - ORODENT',
                short_name: 'DENTALSOFT',
                description: 'Application de gestion de cabinet dentaire - ORODENT',
                theme_color: '#5ad6f5',
                start_url: '/',
                display: 'standalone',
                icons: [
                    { src: 'logo.png', sizes: '512x512', type: 'image/png' }, 
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
