import { useUiSettingsStore } from '@/stores/uiSettings';

/**
 * Home route for the current workstation navigation preference.
 * @returns {{ name: 'navigation-hub' | 'dashboard' }}
 */
export function getHomeRoute() {
    const uiSettings = useUiSettingsStore();
    if (!uiSettings.initialized) {
        uiSettings.initialize();
    }
    return uiSettings.layoutConfig.navigationMode === 'hub' ? { name: 'navigation-hub' } : { name: 'dashboard' };
}
