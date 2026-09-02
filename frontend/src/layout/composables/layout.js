import { computed, reactive } from 'vue';
import { useUiSettingsStore } from '@/stores/uiSettings';

const layoutState = reactive({
    staticMenuDesktopInactive: false,
    overlayMenuActive: false,
    profileSidebarVisible: false,
    configSidebarVisible: false,
    staticMenuMobileActive: false,
    menuHoverActive: false,
    activeMenuItem: null
});

export function useLayout() {
    const uiSettings = useUiSettingsStore();
    const { layoutConfig } = uiSettings;

    const setActiveMenuItem = (item) => {
        layoutState.activeMenuItem = item.value || item;
    };

    const toggleDarkMode = () => {
        if (!document.startViewTransition) {
            uiSettings.toggleDarkMode();

            return;
        }

        document.startViewTransition(() => uiSettings.toggleDarkMode());
    };

    const toggleMenu = () => {
        if (layoutConfig.menuMode === 'overlay') {
            layoutState.overlayMenuActive = !layoutState.overlayMenuActive;
        }

        if (window.innerWidth > 991) {
            layoutState.staticMenuDesktopInactive = !layoutState.staticMenuDesktopInactive;
        } else {
            layoutState.staticMenuMobileActive = !layoutState.staticMenuMobileActive;
        }
    };

    const closeMenu = () => {
        layoutState.overlayMenuActive = false;
        layoutState.staticMenuMobileActive = false;
        layoutState.menuHoverActive = false;
    };

    const isSidebarActive = computed(() => layoutState.overlayMenuActive || layoutState.staticMenuMobileActive);

    const showLayoutMask = computed(() => layoutState.staticMenuMobileActive || (layoutConfig.menuMode === 'overlay' && layoutState.overlayMenuActive));

    const isDarkTheme = computed(() => layoutConfig.darkTheme);

    const isHubNavigation = computed(() => layoutConfig.navigationMode === 'hub');

    const getPrimary = computed(() => layoutConfig.primary);

    const getSurface = computed(() => layoutConfig.surface);

    return {
        layoutConfig,
        layoutState,
        toggleMenu,
        closeMenu,
        isSidebarActive,
        showLayoutMask,
        isDarkTheme,
        isHubNavigation,
        getPrimary,
        getSurface,
        setActiveMenuItem,
        toggleDarkMode
    };
}
