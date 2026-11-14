import { ref, watch } from 'vue';

const STORAGE_KEY = 'dentalsoft.dossier.layoutMode';

export function useDossierLayout() {
    const layoutMode = ref('classic');

    const loadLayoutMode = () => {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored === 'tabs' || stored === 'classic') {
                layoutMode.value = stored;
            }
        } catch {
            layoutMode.value = 'classic';
        }
    };

    const persistLayoutMode = (mode) => {
        try {
            localStorage.setItem(STORAGE_KEY, mode);
        } catch {
            // ignore storage errors
        }
    };

    const toggleLayoutMode = () => {
        layoutMode.value = layoutMode.value === 'classic' ? 'tabs' : 'classic';
        persistLayoutMode(layoutMode.value);
    };

    const isTabsLayout = () => layoutMode.value === 'tabs';

    watch(layoutMode, (mode) => {
        persistLayoutMode(mode);
    });

    loadLayoutMode();

    return {
        layoutMode,
        toggleLayoutMode,
        isTabsLayout
    };
}
