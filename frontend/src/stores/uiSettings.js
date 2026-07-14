import { $t } from '@primeuix/themes';
import { defineStore } from 'pinia';
import { reactive, ref } from 'vue';

const STORAGE_KEYS = {
    themeMode: 'settings.themeMode',
    fontFamily: 'settings.fontFamily',
    fontSize: 'settings.fontSize',
    menuMode: 'settings.layout.menuMode'
};

const DEFAULT_LAYOUT = {
    preset: 'Aura',
    primary: 'sky',
    surface: null,
    darkTheme: false,
    menuMode: 'static'
};

const FONT_FAMILY_MAP = {
    Inter: 'Inter, system-ui, -apple-system, sans-serif',
    Outfit: 'Outfit, system-ui, -apple-system, sans-serif',
    Roboto: 'Roboto, system-ui, -apple-system, sans-serif',
    'Open Sans': '"Open Sans", system-ui, -apple-system, sans-serif',
    Lato: 'Lato, system-ui, -apple-system, sans-serif',
    Poppins: 'Poppins, system-ui, -apple-system, sans-serif',
    System: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
};

const FONT_SIZE_MAP = {
    small: '14px',
    normal: '16px',
    large: '18px'
};

export const useUiSettingsStore = defineStore('uiSettings', () => {
    const initialized = ref(false);

    const themeMode = ref('system');
    const fontFamily = ref('Inter');
    const fontSize = ref('normal');

    const layoutConfig = reactive({ ...DEFAULT_LAYOUT });

    let mediaQuery = null;
    let mediaQueryListener = null;

    const persistAppearance = () => {
        localStorage.setItem(STORAGE_KEYS.themeMode, themeMode.value);
        localStorage.setItem(STORAGE_KEYS.fontFamily, fontFamily.value);
        localStorage.setItem(STORAGE_KEYS.fontSize, fontSize.value);
    };

    const persistLayout = () => {
        localStorage.setItem(STORAGE_KEYS.menuMode, layoutConfig.menuMode);
    };

    const applyThemeMode = (mode = themeMode.value) => {
        const isSystemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        const shouldDark = mode === 'system' ? isSystemDark : mode === 'dark';

        layoutConfig.darkTheme = shouldDark;
        document.documentElement.classList.toggle('app-dark', shouldDark);
    };

    const applyFontSettings = () => {
        const root = document.documentElement;

        root.style.setProperty('--app-font-family', FONT_FAMILY_MAP[fontFamily.value] || FONT_FAMILY_MAP.System);
        root.style.setProperty('--app-font-size', FONT_SIZE_MAP[fontSize.value] || FONT_SIZE_MAP.normal);
        root.style.fontFamily = 'var(--app-font-family)';
        root.style.fontSize = 'var(--app-font-size)';
    };

    const registerSystemThemeListener = () => {
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');

        mediaQueryListener = () => {
            if (themeMode.value === 'system') {
                applyThemeMode('system');
            }
        };

        if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', mediaQueryListener);
        } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(mediaQueryListener);
        }
    };

    const initialize = () => {
        if (initialized.value) {
            return;
        }

        themeMode.value = localStorage.getItem(STORAGE_KEYS.themeMode) || 'system';
        fontFamily.value = localStorage.getItem(STORAGE_KEYS.fontFamily) || 'Inter';
        fontSize.value = localStorage.getItem(STORAGE_KEYS.fontSize) || 'normal';

        layoutConfig.preset = DEFAULT_LAYOUT.preset;
        layoutConfig.primary = DEFAULT_LAYOUT.primary;
        layoutConfig.surface = DEFAULT_LAYOUT.surface;
        layoutConfig.menuMode = localStorage.getItem(STORAGE_KEYS.menuMode) || DEFAULT_LAYOUT.menuMode;

        applyThemeMode(themeMode.value);
        applyFontSettings();
        registerSystemThemeListener();

        initialized.value = true;
    };

    const setThemeMode = (mode) => {
        themeMode.value = mode;
        persistAppearance();
        applyThemeMode(mode);
    };

    const setFontFamily = (family) => {
        fontFamily.value = family;
        persistAppearance();
        applyFontSettings();
    };

    const setFontSize = (size) => {
        fontSize.value = size;
        persistAppearance();
        applyFontSettings();
    };

    const toggleDarkMode = () => {
        setThemeMode(layoutConfig.darkTheme ? 'light' : 'dark');
    };

    const setMenuMode = (mode) => {
        layoutConfig.menuMode = mode;
        persistLayout();
    };

    const setLayoutTheme = ({ presetName, presetValue, presetExt, surfacePalette }) => {
        layoutConfig.preset = presetName;
        $t().preset(presetValue).preset(presetExt).surfacePalette(surfacePalette).use({ useDefaultOptions: true });
    };

    const setPrimaryName = (name) => {
        layoutConfig.primary = name;
    };

    const setSurfaceName = (name) => {
        layoutConfig.surface = name;
    };

    return {
        initialized,
        themeMode,
        fontFamily,
        fontSize,
        layoutConfig,
        initialize,
        applyThemeMode,
        applyFontSettings,
        persistAppearance,
        persistLayout,
        setThemeMode,
        setFontFamily,
        setFontSize,
        toggleDarkMode,
        setMenuMode,
        setLayoutTheme,
        setPrimaryName,
        setSurfaceName
    };
});
