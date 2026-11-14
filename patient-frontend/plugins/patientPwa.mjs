// Only the public application shell is cached. API/medical files never enter CacheStorage.
export function patientPwaOptions(cabinet) {
    const pwa = cabinet.pwa || {};
    const logo = cabinet.logo || 'logo.png';
    const icons = pwa.icons || [{ src: logo, sizes: '512x512', type: 'image/png', purpose: 'any' }];
    const privatePath = /(?:^|\/)(?:api|uploads|upload_files|assurances|health|files|receipts|prints)(?:[/?]|$)/;
    const includeAssets = [...new Set(['favicon.ico', ...icons.map((icon) => icon.src)])];
    for (const asset of includeAssets) {
        if (typeof asset !== 'string' || !/^[\w./-]+\.(?:png|svg|ico|webp|jpe?g)$/i.test(asset) || asset.includes('..') || asset.startsWith('//') || privatePath.test(asset)) {
            throw new Error(`Invalid public PWA asset: ${asset}`);
        }
    }
    return {
        registerType: 'prompt',
        injectRegister: false,
        includeAssets,
        devOptions: { enabled: false },
        workbox: {
            cleanupOutdatedCaches: true,
            clientsClaim: true,
            // No skipWaiting: only the explicit update action may activate a waiting SW.
            globPatterns: ['index.html', 'assets/**/*.{js,css,woff,woff2,ttf,png,svg,ico,jpg,jpeg,webp}'],
            globIgnores: ['**/release.json', '**/pwa-release-worker.js'],
            runtimeCaching: [],
            navigateFallback: 'index.html',
            navigateFallbackDenylist: [privatePath, /\.(?:pdf|dcm|dicom|zip)(?:\?|$)/i, /(?:^|\/)\.well-known(?:[/?]|$)/, /(?:^|\/)(?:release\.json|pwa-release-worker\.js)(?:\?|$)/],
            importScripts: ['pwa-release-worker.js']
        },
        manifest: {
            id: '/',
            name: pwa.name || `${cabinet.displayName || 'DentalSoft'} — Espace patient`,
            short_name: pwa.shortName || cabinet.brandName || 'Espace patient',
            description: pwa.description || 'Votre espace patient : rendez-vous, consultations et suivi dentaire.',
            theme_color: pwa.themeColor || '#0ea5e9',
            background_color: pwa.backgroundColor || '#ffffff',
            start_url: pwa.startUrl || '/',
            display: pwa.display || 'standalone',
            lang: 'fr',
            icons
        }
    };
}
