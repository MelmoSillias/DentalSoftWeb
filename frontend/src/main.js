import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import { registerSW } from 'virtual:pwa-register';
import { createApp } from 'vue';
import App from './App.vue';
import AppToast from '@/components/AppToast.vue';
import router from './router';
import { useUiSettingsStore } from '@/stores/uiSettings';
import cabinetConfig from '@/cabinetConfig';
import { frLocale } from '@/locales/primevue-fr';
import { devDebug, logAppError, setAppLoggerRouteResolver } from '@/utils/appLogger';

// Défensive: wrappe l'ajout/suppression de listeners sur matchMedia
// pour éviter que des listeners tiers (ex: PrimeVue) lèvent des exceptions
// non capturées (ex: alignOverlay accédant à des refs supprimées).
if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
    try {
        const sample = window.matchMedia('(orientation: portrait)');
        const proto = Object.getPrototypeOf(sample);
        const origAdd = proto.addEventListener;
        const origRemove = proto.removeEventListener;

        if (origAdd && origRemove) {
            proto.addEventListener = function (type, listener, options) {
                const wrapped = function (e) {
                    try {
                        return listener.call(this, e);
                    } catch (err) {
                        if (import.meta.env.DEV) {
                            logAppError('matchMedia.listener', err);
                        }
                    }
                };
                // store mapping to allow proper removal
                if (!this.__wrappedListeners) this.__wrappedListeners = new WeakMap();
                this.__wrappedListeners.set(listener, wrapped);
                return origAdd.call(this, type, wrapped, options);
            };

            proto.removeEventListener = function (type, listener, options) {
                if (this.__wrappedListeners && this.__wrappedListeners.has(listener)) {
                    const wrapped = this.__wrappedListeners.get(listener);
                    this.__wrappedListeners.delete(listener);
                    return origRemove.call(this, type, wrapped, options);
                }
                return origRemove.call(this, type, listener, options);
            };
        }

        // backward-compat for addListener/removeListener
        if (proto.addListener && proto.removeListener) {
            const origAddListener = proto.addListener;
            const origRemoveListener = proto.removeListener;
            proto.addListener = function (listener) {
                const wrapped = function (e) {
                    try {
                        return listener.call(this, e);
                    } catch (err) {
                        if (import.meta.env.DEV) {
                            logAppError('matchMedia.addListener', err);
                        }
                    }
                };
                if (!this.__wrappedListeners) this.__wrappedListeners = new WeakMap();
                this.__wrappedListeners.set(listener, wrapped);
                return origAddListener.call(this, wrapped);
            };
            proto.removeListener = function (listener) {
                if (this.__wrappedListeners && this.__wrappedListeners.has(listener)) {
                    const wrapped = this.__wrappedListeners.get(listener);
                    this.__wrappedListeners.delete(listener);
                    return origRemoveListener.call(this, wrapped);
                }
                return origRemoveListener.call(this, listener);
            };
        }

        // no EventSource polyfill here; we use fetch-event-source in composable
    } catch (e) {
        if (import.meta.env.DEV) {
            logAppError('matchMedia.wrapper', e);
        }
    }
}

import '@/assets/styles.scss';
import '@/assets/tourguide.scss';

if (import.meta.env.DEV) {
    const originalWarn = console.warn;
    console.warn = (...args) => {
        const first = args[0] ? String(args[0]) : '';

        const shouldSilence =
            first.includes('onMounted is called when there is no active component instance to be associated with') ||
            first.includes('Deprecated since v4. Use Select component instead.') ||
            first.includes('Deprecated since v4. Use DatePicker component instead.');

        if (shouldSilence) {
            return;
        }

        originalWarn(...args);
    };
}

if (typeof document !== 'undefined') {
    if (cabinetConfig.appTitle) {
        document.title = cabinetConfig.appTitle;
    }

    const themeColorMeta = document.querySelector('meta[name="theme-color"]');
    if (themeColorMeta && cabinetConfig?.pwa?.themeColor) {
        themeColorMeta.setAttribute('content', cabinetConfig.pwa.themeColor);
    }
}

const SkyPreset = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{sky.50}',
            100: '{sky.100}',
            200: '{sky.200}',
            300: '{sky.300}',
            400: '{sky.400}',
            500: '{sky.500}',
            600: '{sky.600}',
            700: '{sky.700}',
            800: '{sky.800}',
            900: '{sky.900}',
            950: '{sky.950}'
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{sky.500}',
                    inverseColor: '#ffffff',
                    hoverColor: '{sky.600}',
                    activeColor: '{sky.700}'
                },
                highlight: {
                    background: '{sky.500}',
                    focusBackground: '{sky.600}',
                    textColor: '#ffffff',
                    focusTextColor: '#ffffff'
                }
            },
            dark: {
                primary: {
                    color: '{sky.400}',
                    inverseColor: '#000000',
                    hoverColor: '{sky.300}',
                    activeColor: '{sky.200}'
                },
                highlight: {
                    background: '{sky.400}',
                    focusBackground: '{sky.300}',
                    textColor: '#000000',
                    focusTextColor: '#000000'
                }
            }
        }
    }
});

const app = createApp(App);

app.config.errorHandler = (err, instance, info) => {
    logAppError(`vue:${info}`, err, { component: instance?.$options?.name });
};

if (typeof window !== 'undefined') {
    window.addEventListener('unhandledrejection', (event) => {
        logAppError('unhandledrejection', event.reason);
    });
}

app.use(PrimeVue, {
    ripple: true,
    locale: frLocale,
    theme: {
        preset: SkyPreset,
        options: {
            prefix: 'p',
            darkModeSelector: '.app-dark',
            cssLayer: false
        }
    }
});

const pinia = createPinia();
app.use(pinia);

const uiSettings = useUiSettingsStore(pinia);
uiSettings.initialize();

app.use(router);
setAppLoggerRouteResolver(() => router.currentRoute.value?.fullPath);
router.onError((error) => {
    logAppError('router.chunk', error);
});
app.use(ToastService);
app.use(ConfirmationService);
app.component('AppToast', AppToast);

const updateSW = registerSW({
    immediate: true,
    onNeedRefresh() {
        // Mise à jour silencieuse : pas de vidage manuel du cache navigateur.
        updateSW(true);
    },
    onOfflineReady() {
        devDebug('Application prête hors-ligne.');
    }
});

app.mount('#app');
