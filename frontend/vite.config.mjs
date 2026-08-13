import { fileURLToPath, URL } from 'node:url';

import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { defineConfig } from 'vite';
import cabinetConfig from './src/generated/cabinet-config.generated.js';
import { injectBuildVersion } from './plugins/injectBuildVersion.mjs';

const cabinetPwa = cabinetConfig.pwa || {};

const manifestIconSrcs = (cabinetPwa.icons || [])
    .map((icon) => icon?.src)
    .filter((src) => typeof src === 'string' && src.trim() !== '');

// Exclude glob patterns (e.g. icons/*.svg): Workbox precaches exact URLs and fails on 404.
const staticIncludeAssets = (cabinetPwa.includeAssets || ['favicon.ico', 'robots.txt'])
    .filter((entry) => typeof entry === 'string' && !entry.includes('*'));

const pwaIncludeAssets = [...new Set([...staticIncludeAssets, ...manifestIconSrcs])];

// https://vitejs.dev/config/
// VITE_DROP_CONSOLE=false : build prod avec consoles (diagnostic temporaire)
export default defineConfig(({ command }) => ({
    optimizeDeps: {
        noDiscovery: true,
        include: ['qrcode'],
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
        injectBuildVersion(),
        vue(),
        // PWA plugin: génère le service worker et le manifest
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: pwaIncludeAssets,
            workbox: {
                cleanupOutdatedCaches: true,
                clientsClaim: true,
                skipWaiting: true,
                navigateFallback: 'index.html',
                navigateFallbackDenylist: [/^\/api/],
            },
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
    },
    esbuild: {
        drop:
            command === 'build' && process.env.VITE_DROP_CONSOLE !== 'false'
                ? ['console', 'debugger']
                : []
    },
    build: {
        rollupOptions: {
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
}));
