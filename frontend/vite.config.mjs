import { fileURLToPath, URL } from 'node:url';

import { PrimeVueResolver } from '@primevue/auto-import-resolver';
import vue from '@vitejs/plugin-vue';
import Components from 'unplugin-vue-components/vite';
import { VitePWA } from 'vite-plugin-pwa';
import { createLogger, defineConfig, loadEnv } from 'vite';
import cabinetConfig from './src/generated/cabinet-config.generated.js';
import { injectBuildVersion } from './plugins/injectBuildVersion.mjs';

const cabinetPwa = cabinetConfig.pwa || {};

const manifestIconSrcs = (cabinetPwa.icons || []).map((icon) => icon?.src).filter((src) => typeof src === 'string' && src.trim() !== '');

// Exclude glob patterns (e.g. icons/*.svg): Workbox precaches exact URLs and fails on 404.
const staticIncludeAssets = (cabinetPwa.includeAssets || ['favicon.ico', 'robots.txt']).filter((entry) => typeof entry === 'string' && !entry.includes('*'));

const pwaIncludeAssets = [...new Set([...staticIncludeAssets, ...manifestIconSrcs])];

const logger = createLogger();
const originalWarn = logger.warn.bind(logger);
logger.warn = (msg, options) => {
    if (typeof msg === 'string' && msg.includes('dynamic import will not move module into another chunk')) {
        return;
    }

    originalWarn(msg, options);
};

// https://vitejs.dev/config/
// VITE_DROP_CONSOLE=false : build prod avec consoles (diagnostic temporaire)
export default defineConfig(({ command, mode }) => {
    const env = loadEnv(mode, fileURLToPath(new URL('.', import.meta.url)), 'VITE_');
    const enablePwa = command === 'build' && env.VITE_ENABLE_PWA !== 'false';

    return {
        customLogger: logger,
        define: {
            'import.meta.env.VITE_ENABLE_PWA': JSON.stringify(enablePwa)
        },
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
            port: 5181,
            strictPort: true,
            // HMR utilise le même serveur dev strict (5181).
            sourcemapIgnoreList: (sourcePath) => sourcePath.includes('/node_modules/@microsoft/fetch-event-source/') || sourcePath.includes('\\node_modules\\@microsoft\\fetch-event-source\\')
        },
        preview: {
            port: 4181,
            strictPort: true
        },
        plugins: [
            injectBuildVersion(),
            vue(),

            enablePwa &&
                VitePWA({
                    // Activation explicite depuis usePwaUpdate, jamais à l'insu de l'utilisateur.
                    registerType: 'prompt',
                    injectRegister: false,
                    includeAssets: pwaIncludeAssets,
                    workbox: {
                        cleanupOutdatedCaches: true,
                        globIgnores: ['**/release.json', '**/pwa-release-worker.js'],
                        clientsClaim: true,
                        // skipWaiting volontairement absent : activation seulement après confirmation.
                        navigateFallback: 'index.html',
                        navigateFallbackDenylist: process.env.VITE_DOKPLOY === 'true' ? [/^\/(api|uploads|upload_files|assurances|health)(\/|$)/, /^\/\.well-known\//, /^\/release\.json$/] : [/^\/api/, /^\/release\.json$/],
                        importScripts: ['notification-handler.js', 'pwa-release-worker.js']
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
                dirs: ['src/shared/ui', 'src/domains', 'src/app/layout'],
                resolvers: [PrimeVueResolver()]
            })
        ],
        resolve: {
            alias: {
                '@': fileURLToPath(new URL('./src', import.meta.url))
            }
        },
        esbuild: {
            drop: command === 'build' && process.env.VITE_DROP_CONSOLE !== 'false' ? ['console', 'debugger'] : []
        },
        build: {
            rollupOptions: {
                onwarn(warning, warn) {
                    if (warning.code === 'INVALID_ANNOTATION') {
                        return;
                    }

                    warn(warning);
                },
                output: {
                    entryFileNames: 'assets/[name]-[hash].js',
                    chunkFileNames: 'assets/[name]-[hash].js',
                    assetFileNames: 'assets/[name]-[hash][extname]'
                }
            }
        }
    };
});
